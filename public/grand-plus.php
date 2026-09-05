<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$configFile = __DIR__ . '/vitrine-mail-config.php';

if (!is_file($configFile)) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Configuration email indisponible.'
    ]);

    exit;
}

$config = require $configFile;

$toEmail = $config['to_email'] ?? 'vitrineplus@hotmail.com';

/*
|--------------------------------------------------------------------------
| OUTILS
|--------------------------------------------------------------------------
*/

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function cleanText(mixed $value, int $max = 500): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }

    return $value;
}

function sendSmtpMail(
    array $config,
    string $subject,
    string $body,
    string $replyTo = ''
): bool {
    $host = $config['smtp_host'] ?? '';
    $port = (int) ($config['smtp_port'] ?? 465);
    $username = $config['smtp_username'] ?? '';
    $password = $config['smtp_password'] ?? '';
    $fromEmail = $config['from_email'] ?? $username;
    $fromName = $config['from_name'] ?? 'Vitrine+';
    $toEmail = $config['to_email'] ?? '';

    if (
        $host === '' ||
        $username === '' ||
        $password === '' ||
        $fromEmail === '' ||
        $toEmail === ''
    ) {
        return false;
    }

    $transport = $port === 465
        ? 'ssl://' . $host
        : $host;

    $socket = @fsockopen(
        $transport,
        $port,
        $errno,
        $errstr,
        10
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);

    $readResponse = function () use ($socket): string {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $response;
    };

    $expect = function (array $codes) use (
        $socket,
        $readResponse
    ): bool {
        $response = $readResponse();

        $code = (int) substr($response, 0, 3);

        return in_array($code, $codes, true);
    };

    $write = function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    if (!$expect([220])) {
        fclose($socket);
        return false;
    }

    $write('EHLO vitrineplus.fr');

    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('AUTH LOGIN');

    if (!$expect([334])) {
        fclose($socket);
        return false;
    }

    $write(base64_encode($username));

    if (!$expect([334])) {
        fclose($socket);
        return false;
    }

    $write(base64_encode($password));

    if (!$expect([235])) {
        fclose($socket);
        return false;
    }

    $write('MAIL FROM:<' . $fromEmail . '>');

    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('RCPT TO:<' . $toEmail . '>');

    if (!$expect([250, 251])) {
        fclose($socket);
        return false;
    }

    $write('DATA');

    if (!$expect([354])) {
        fclose($socket);
        return false;
    }

    $safeSubject = str_replace(
        ["\r", "\n"],
        '',
        $subject
    );

    $headers =
        'From: ' .
        $fromName .
        ' <' .
        $fromEmail .
        ">\r\n" .
        'To: ' .
        $toEmail .
        "\r\n" .
        'Subject: ' .
        $safeSubject .
        "\r\n" .
        'MIME-Version: 1.0' .
        "\r\n" .
        'Content-Type: text/plain; charset=UTF-8' .
        "\r\n";

    if ($replyTo !== '' &&
        filter_var($replyTo, FILTER_VALIDATE_EMAIL)
    ) {
        $headers .=
            'Reply-To: ' .
            $replyTo .
            "\r\n";
    }

    $message =
        $headers .
        "\r\n" .
        $body .
        "\r\n.";

    $write($message);

    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('QUIT');

    fclose($socket);

    return true;
}

/*
|--------------------------------------------------------------------------
| LECTURE JSON
|--------------------------------------------------------------------------
*/

$raw = file_get_contents('php://input');

if ($raw === false || trim($raw) === '') {
    respond([
        'success' => false,
        'message' => 'Données manquantes.'
    ], 422);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    respond([
        'success' => false,
        'message' => 'Données invalides.'
    ], 422);
}

/*
|--------------------------------------------------------------------------
| ANTI-SPAM HONEYPOT
|--------------------------------------------------------------------------
*/

$honeypot = cleanText(
    $data['websiteCheck'] ?? '',
    100
);

if ($honeypot !== '') {
    respond([
        'success' => true
    ]);
}

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

$name = cleanText($data['name'] ?? '', 120);
$company = cleanText($data['company'] ?? '', 160);
$email = cleanText($data['email'] ?? '', 180);
$phone = cleanText($data['phone'] ?? '', 80);
$website = cleanText($data['website'] ?? '', 300);
$sector = cleanText($data['sector'] ?? '', 100);

$consent = !empty($data['consent']);
$marketing = !empty($data['marketing']);

$problems = $data['problems'] ?? [];

if (!is_array($problems)) {
    $problems = [];
}

$problems = array_values(
    array_filter(
        array_map(
            fn ($item) => cleanText($item, 180),
            $problems
        )
    )
);

if (
    $name === '' ||
    $company === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $phone === '' ||
    $sector === '' ||
    !$consent
) {
    respond([
        'success' => false,
        'message' => 'Veuillez compléter les informations obligatoires.'
    ], 422);
}

/*
|--------------------------------------------------------------------------
| NORMALISATION SITE
|--------------------------------------------------------------------------
*/

if ($website !== '') {
    if (
        !preg_match(
            '#^https?://#i',
            $website
        )
    ) {
        $website = 'https://' . $website;
    }

    if (
        !filter_var(
            $website,
            FILTER_VALIDATE_URL
        )
    ) {
        respond([
            'success' => false,
            'message' => 'L’adresse de votre site semble incorrecte.'
        ], 422);
    }
}

/*
|--------------------------------------------------------------------------
| MOIS DE PARTICIPATION
|--------------------------------------------------------------------------
*/

$timezone = new DateTimeZone('Europe/Paris');

$now = new DateTimeImmutable(
    'now',
    $timezone
);

$monthKey = $now->format('Y-m');

/*
|--------------------------------------------------------------------------
| STOCKAGE
|--------------------------------------------------------------------------
*/

$dataDirectory = __DIR__ . '/vitrine-data';

if (!is_dir($dataDirectory)) {
    if (!@mkdir($dataDirectory, 0750, true)) {
        respond([
            'success' => false,
            'message' => 'Impossible d’enregistrer votre participation.'
        ], 500);
    }
}

$storageFile =
    $dataDirectory .
    '/grand-plus-' .
    $monthKey .
    '.json';

$lockFile =
    $dataDirectory .
    '/grand-plus.lock';

$lockHandle = @fopen(
    $lockFile,
    'c'
);

if (!$lockHandle) {
    respond([
        'success' => false,
        'message' => 'Impossible d’enregistrer votre participation.'
    ], 500);
}

if (!flock($lockHandle, LOCK_EX)) {
    fclose($lockHandle);

    respond([
        'success' => false,
        'message' => 'Impossible d’enregistrer votre participation.'
    ], 500);
}

$participants = [];

if (is_file($storageFile)) {
    $existing = json_decode(
        (string) file_get_contents($storageFile),
        true
    );

    if (is_array($existing)) {
        $participants = $existing;
    }
}

/*
|--------------------------------------------------------------------------
| EMPÊCHER LES DOUBLONS DANS LE MÊME MOIS
|--------------------------------------------------------------------------
*/

$emailNormalized = strtolower($email);

foreach ($participants as $participant) {
    if (
        isset($participant['email']) &&
        strtolower((string) $participant['email']) ===
        $emailNormalized
    ) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);

        respond([
            'success' => true,
            'alreadyRegistered' => true
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PARTICIPATION
|--------------------------------------------------------------------------
*/

$participant = [
    'id' => bin2hex(random_bytes(12)),
    'createdAt' => $now->format(DateTimeInterface::ATOM),
    'month' => $monthKey,
    'name' => $name,
    'company' => $company,
    'email' => $email,
    'phone' => $phone,
    'website' => $website,
    'sector' => $sector,
    'problems' => $problems,
    'marketingConsent' => $marketing,
    'consentAt' => $now->format(DateTimeInterface::ATOM),
    'ipHash' => hash(
        'sha256',
        ($_SERVER['REMOTE_ADDR'] ?? '') .
        '|' .
        ($_SERVER['HTTP_USER_AGENT'] ?? '')
    ),
];

$participants[] = $participant;

$encoded = json_encode(
    $participants,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if (
    $encoded === false ||
    file_put_contents(
        $storageFile,
        $encoded,
        LOCK_EX
    ) === false
) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    respond([
        'success' => false,
        'message' => 'Impossible d’enregistrer votre participation.'
    ], 500);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

/*
|--------------------------------------------------------------------------
| EMAIL VITRINE+
|--------------------------------------------------------------------------
*/

$subject =
    'Nouveau participant — Le Grand + — ' .
    $company;

$body =
    "NOUVELLE PARTICIPATION — LE GRAND +\n\n" .

    "Mois : " .
    $monthKey .
    "\n\n" .

    "Nom : " .
    $name .
    "\n" .

    "Entreprise : " .
    $company .
    "\n" .

    "Email : " .
    $email .
    "\n" .

    "Téléphone : " .
    $phone .
    "\n" .

    "Site : " .
    ($website !== '' ? $website : 'Non renseigné') .
    "\n" .

    "Secteur : " .
    $sector .
    "\n\n" .

    "Points à améliorer :\n" .
    (
        count($problems)
            ? '- ' . implode("\n- ", $problems)
            : 'Non renseigné'
    ) .
    "\n\n" .

    "Consentement au règlement : OUI\n" .

    "Prospection Vitrine+ : " .
    ($marketing ? 'OUI' : 'NON') .
    "\n\n" .

    "ID participation : " .
    $participant['id'] .
    "\n" .

    "Date : " .
    $participant['createdAt'] .
    "\n";

$emailSent = sendSmtpMail(
    $config,
    $subject,
    $body,
    $email
);

respond([
    'success' => true,
    'emailSent' => $emailSent
]);