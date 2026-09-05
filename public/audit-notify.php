<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const NOTIFY_TO = 'vitrineplus@hotmail.com';
const DATA_DIR = __DIR__ . '/vitrine-data';
const LOG_FILE = DATA_DIR . '/audits.jsonl';
const RATE_FILE = DATA_DIR . '/audit-rate.json';

function respond(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function cleanValue(string $value, int $max = 2000): string
{
    $value = trim($value);

    $value = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $value
    ) ?? '';

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

function jsonWrite(string $file, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return file_put_contents(
        $file,
        $json . PHP_EOL,
        FILE_APPEND | LOCK_EX
    ) !== false;
}

/**
 * Envoie un email via le SMTP IONOS déjà utilisé par Vitrine+.
 */
function sendSmtpEmail(
    string $to,
    string $subject,
    string $body
): bool {

    $configFile = __DIR__ . '/../vitrine-mail-config.php';

    if (!is_file($configFile)) {
        return false;
    }

    $config = require $configFile;

    $host = (string) ($config['smtp_host'] ?? '');
    $port = (int) ($config['smtp_port'] ?? 465);
    $username = (string) ($config['smtp_username'] ?? '');
    $password = (string) ($config['smtp_password'] ?? '');
    $fromEmail = (string) ($config['from_email'] ?? '');
    $fromName = (string) ($config['from_name'] ?? 'Vitrine+');

    if (
        $host === '' ||
        $username === '' ||
        $password === '' ||
        $fromEmail === ''
    ) {
        return false;
    }

    $socket = @fsockopen(
        'ssl://' . $host,
        $port,
        $errno,
        $errstr,
        10
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);

    $read = function () use ($socket): string {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (
                strlen($line) < 4 ||
                $line[3] !== '-'
            ) {
                break;
            }
        }

        return $response;
    };

    $send = function (string $command) use (
        $socket,
        $read
    ): bool {

        fwrite($socket, $command . "\r\n");

        $response = $read();

        if ($response === '') {
            return false;
        }

        $code = (int) substr($response, 0, 3);

        return $code >= 200 && $code < 400;
    };

    $read();

    if (!$send('EHLO vitrineplus.fr')) {
        fclose($socket);
        return false;
    }

    if (!$send('AUTH LOGIN')) {
        fclose($socket);
        return false;
    }

    if (!$send(base64_encode($username))) {
        fclose($socket);
        return false;
    }

    if (!$send(base64_encode($password))) {
        fclose($socket);
        return false;
    }

    if (!$send('MAIL FROM:<' . $fromEmail . '>')) {
        fclose($socket);
        return false;
    }

    if (!$send('RCPT TO:<' . $to . '>')) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");

    $dataResponse = $read();

    if ((int) substr($dataResponse, 0, 3) !== 354) {
        fclose($socket);
        return false;
    }

    $safeSubject = str_replace(
        ["\r", "\n"],
        '',
        $subject
    );

    $safeFromName = str_replace(
        ["\r", "\n"],
        '',
        $fromName
    );

    $headers =
        'From: ' .
        $safeFromName .
        ' <' .
        $fromEmail .
        ">\r\n" .

        'To: ' .
        $to .
        "\r\n" .

        'Subject: ' .
        $safeSubject .
        "\r\n" .

        "MIME-Version: 1.0\r\n" .

        "Content-Type: text/plain; charset=UTF-8\r\n" .

        "Content-Transfer-Encoding: 8bit\r\n";

    $message =
        $headers .
        "\r\n" .
        $body .
        "\r\n.";

    fwrite($socket, $message . "\r\n");

    $finalResponse = $read();

    $send('QUIT');

    fclose($socket);

    $code = (int) substr(
        $finalResponse,
        0,
        3
    );

    return $code >= 200 && $code < 400;
}

/*
|--------------------------------------------------------------------------
| POST uniquement
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
) {
    respond([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ], 405);
}

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

$website = cleanValue(
    (string) ($_POST['website'] ?? ''),
    2000
);

$score = (int) (
    $_POST['score'] ?? 0
);

$pagesAnalyzed = (int) (
    $_POST['pagesAnalyzed'] ?? 0
);

$pagesDiscovered = (int) (
    $_POST['pagesDiscovered'] ?? 0
);

$responseTime = cleanValue(
    (string) ($_POST['responseTime'] ?? ''),
    50
);

$categoriesRaw = (string) (
    $_POST['categories'] ?? ''
);

$categories = [];

if ($categoriesRaw !== '') {
    $decoded = json_decode(
        $categoriesRaw,
        true
    );

    if (is_array($decoded)) {
        $categories = $decoded;
    }
}

if ($website === '') {
    respond([
        'success' => false,
        'message' => 'Site manquant.'
    ], 422);
}

/*
|--------------------------------------------------------------------------
| RATE LIMIT
|--------------------------------------------------------------------------
|
| Une notification maximum par IP toutes les 60 secondes.
| L'IP n'est jamais enregistrée dans le journal des audits.
|--------------------------------------------------------------------------
*/

$ip = (string) (
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
);

$ipHash = hash(
    'sha256',
    $ip . '|VitrinePlusAudit'
);

$now = time();

$rateData = [];

if (is_file(RATE_FILE)) {
    $contents = @file_get_contents(
        RATE_FILE
    );

    if ($contents !== false) {
        $decodedRate = json_decode(
            $contents,
            true
        );

        if (is_array($decodedRate)) {
            $rateData = $decodedRate;
        }
    }
}

$lastNotification = (int) (
    $rateData[$ipHash] ?? 0
);

if (
    $lastNotification > 0 &&
    ($now - $lastNotification) < 60
) {
    respond([
        'success' => true,
        'notified' => false,
        'rateLimited' => true
    ]);
}

$rateData[$ipHash] = $now;

/*
|--------------------------------------------------------------------------
| NETTOYAGE DU RATE LIMIT
|--------------------------------------------------------------------------
*/

foreach ($rateData as $key => $timestamp) {
    if (
        !is_int($timestamp) ||
        ($now - $timestamp) > 3600
    ) {
        unset($rateData[$key]);
    }
}

if (!is_dir(DATA_DIR)) {
    @mkdir(
        DATA_DIR,
        0755,
        true
    );
}

@file_put_contents(
    RATE_FILE,
    json_encode(
        $rateData,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ),
    LOCK_EX
);

/*
|--------------------------------------------------------------------------
| JOURNAL
|--------------------------------------------------------------------------
*/

$categorySummary = [];

foreach ($categories as $key => $category) {
    if (!is_array($category)) {
        continue;
    }

    $categoryScore = (int) (
        $category['score'] ?? 0
    );

    $categoryLabel = cleanValue(
        (string) (
            $category['label'] ??
            ucfirst((string) $key)
        ),
        100
    );

    $categorySummary[$key] = [
        'label' => $categoryLabel,
        'score' => max(
            0,
            min(100, $categoryScore)
        )
    ];
}

$event = [
    'timestamp' => date(
        'c'
    ),
    'website' => $website,
    'score' => max(
        0,
        min(100, $score)
    ),
    'pagesAnalyzed' => max(
        0,
        $pagesAnalyzed
    ),
    'pagesDiscovered' => max(
        0,
        $pagesDiscovered
    ),
    'responseTime' => $responseTime,
    'categories' => $categorySummary
];

jsonWrite(
    LOG_FILE,
    $event
);

/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

$subject =
    '🔎 Nouveau audit Vitrine+ — ' .
    $score .
    '/100';

$lines = [
    'NOUVEL AUDIT VITRINE+',
    '',
    'Un visiteur vient de lancer un audit sur Vitrine+.',
    '',
    'Site analysé : ' . $website,
    'Score global : ' . $score . '/100',
    'Pages analysées : ' . $pagesAnalyzed,
    'Pages découvertes : ' . $pagesDiscovered,
    'Temps d’analyse : ' . $responseTime . ' s',
    '',
    'SCORES',
];

foreach ($categorySummary as $category) {
    $lines[] =
        $category['label'] .
        ' : ' .
        $category['score'] .
        '/100';
}

$lines[] = '';
$lines[] = 'Date : ' . date(
    'd/m/Y à H:i:s'
);
$lines[] = '';
$lines[] = 'Vitrine+ — Votre entreprise. En mieux.';

$emailSent = sendSmtpEmail(
    NOTIFY_TO,
    $subject,
    implode("\n", $lines)
);

respond([
    'success' => true,
    'notified' => $emailSent
]);