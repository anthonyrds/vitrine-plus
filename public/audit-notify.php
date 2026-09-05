<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const NOTIFY_TO = 'vitrineplus@hotmail.com';

const MAX_WEBSITE_LENGTH = 500;
const MAX_CATEGORIES_LENGTH = 5000;

/*
|--------------------------------------------------------------------------
| RÉPONSE JSON
|--------------------------------------------------------------------------
*/

function respond(
    bool $success,
    string $message = '',
    array $extra = []
): void {
    http_response_code($success ? 200 : 400);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| NETTOYAGE
|--------------------------------------------------------------------------
*/

function cleanValue(
    string $value,
    int $maxLength = 1000
): string {
    $value = trim($value);

    $value = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $value
    ) ?? '';

    if (function_exists('mb_substr')) {
        return mb_substr(
            $value,
            0,
            $maxLength,
            'UTF-8'
        );
    }

    return substr(
        $value,
        0,
        $maxLength
    );
}

/*
|--------------------------------------------------------------------------
| MÉTHODE HTTP
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    respond(
        false,
        'Méthode non autorisée.'
    );
}

/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION
|--------------------------------------------------------------------------
*/

$website = cleanValue(
    (string) (
        $_POST['website'] ?? ''
    ),
    MAX_WEBSITE_LENGTH
);

$score = cleanValue(
    (string) (
        $_POST['score'] ?? ''
    ),
    20
);

$pagesAnalyzed = cleanValue(
    (string) (
        $_POST['pagesAnalyzed'] ?? ''
    ),
    20
);

$pagesDiscovered = cleanValue(
    (string) (
        $_POST['pagesDiscovered'] ?? ''
    ),
    20
);

$responseTime = cleanValue(
    (string) (
        $_POST['responseTime'] ?? ''
    ),
    30
);

$categoriesRaw = cleanValue(
    (string) (
        $_POST['categories'] ?? ''
    ),
    MAX_CATEGORIES_LENGTH
);

/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($website === '') {
    respond(
        false,
        'Site non renseigné.'
    );
}

if (!filter_var($website, FILTER_VALIDATE_URL)) {
    respond(
        false,
        'Adresse du site invalide.'
    );
}

$websiteParts = parse_url($website);

if (
    !$websiteParts ||
    empty($websiteParts['host'])
) {
    respond(
        false,
        'Adresse du site invalide.'
    );
}

$websiteHost = strtolower(
    (string) $websiteParts['host']
);

$websiteHost = preg_replace(
    '/^www\./i',
    '',
    $websiteHost
) ?? $websiteHost;

/*
|--------------------------------------------------------------------------
| VALIDATION DES CHIFFRES
|--------------------------------------------------------------------------
*/

if (
    $score === '' ||
    !is_numeric($score) ||
    (float) $score < 0 ||
    (float) $score > 100
) {
    $score = 'N/A';
}

if (
    $pagesAnalyzed === '' ||
    !is_numeric($pagesAnalyzed) ||
    (int) $pagesAnalyzed < 0
) {
    $pagesAnalyzed = 'N/A';
}

if (
    $pagesDiscovered === '' ||
    !is_numeric($pagesDiscovered) ||
    (int) $pagesDiscovered < 0
) {
    $pagesDiscovered = 'N/A';
}

if (
    $responseTime === '' ||
    !is_numeric($responseTime) ||
    (float) $responseTime < 0
) {
    $responseTime = 'N/A';
}

/*
|--------------------------------------------------------------------------
| CATÉGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

if ($categoriesRaw !== '') {
    $decoded = json_decode(
        $categoriesRaw,
        true
    );

    if (is_array($decoded)) {
        foreach ($decoded as $key => $category) {
            if (
                !is_string($key) ||
                !is_array($category)
            ) {
                continue;
            }

            $allowedKeys = [
                'seo',
                'structure',
                'mobile',
                'content',
                'performance',
                'social',
                'conversion',
            ];

            if (
                !in_array(
                    $key,
                    $allowedKeys,
                    true
                )
            ) {
                continue;
            }

            $categoryScore = $category['score'] ?? null;

            if (
                !is_numeric($categoryScore)
            ) {
                continue;
            }

            $categoryScore = max(
                0,
                min(
                    100,
                    (int) round(
                        (float) $categoryScore
                    )
                )
            );

            $categories[$key] = $categoryScore;
        }
    }
}

/*
|--------------------------------------------------------------------------
| CONFIGURATION SMTP
|--------------------------------------------------------------------------
|
| Même configuration que audit-lead.php.
|
*/

$configFile = __DIR__ . '/vitrine-mail-config.php';

if (!file_exists($configFile)) {
    /*
     * L'audit doit continuer même si la notification
     * ne peut pas être envoyée.
     */
    respond(
        true,
        'Audit terminé.',
        [
            'notified' => false,
        ]
    );
}

$config = require $configFile;

$host = $config['smtp_host'] ?? '';
$port = (int) (
    $config['smtp_port'] ?? 465
);

$username = $config['smtp_username'] ?? '';
$password = $config['smtp_password'] ?? '';

$fromEmail = $config['from_email'] ?? '';
$fromName = $config['from_name'] ?? 'Vitrine+';

/*
|--------------------------------------------------------------------------
| VÉRIFICATION CONFIGURATION
|--------------------------------------------------------------------------
*/

if (
    $host === '' ||
    $username === '' ||
    $password === '' ||
    $fromEmail === ''
) {
    respond(
        true,
        'Audit terminé.',
        [
            'notified' => false,
        ]
    );
}

/*
|--------------------------------------------------------------------------
| SMTP READ
|--------------------------------------------------------------------------
*/

function smtpRead($socket): string
{
    $response = '';

    while (
        ($line = fgets($socket, 515))
        !== false
    ) {
        $response .= $line;

        if (
            isset($line[3]) &&
            $line[3] === ' '
        ) {
            break;
        }
    }

    return $response;
}

/*
|--------------------------------------------------------------------------
| SMTP COMMAND
|--------------------------------------------------------------------------
*/

function smtpCommand(
    $socket,
    ?string $command,
    array $expectedCodes = []
): string {
    if ($command !== null) {
        fwrite(
            $socket,
            $command . "\r\n"
        );
    }

    $response = smtpRead($socket);

    if (!empty($expectedCodes)) {
        $code = substr(
            $response,
            0,
            3
        );

        if (
            !in_array(
                $code,
                $expectedCodes,
                true
            )
        ) {
            throw new Exception(
                'Réponse SMTP inattendue.'
            );
        }
    }

    return $response;
}

/*
|--------------------------------------------------------------------------
| CATÉGORIES DANS L'E-MAIL
|--------------------------------------------------------------------------
*/

$categoryLabels = [
    'seo' => 'SEO',
    'structure' => 'Structure',
    'mobile' => 'Mobile',
    'content' => 'Contenu',
    'performance' => 'Performance',
    'social' => 'Partage social',
    'conversion' => 'Conversion',
];

$categoryLines = [];

foreach (
    $categoryLabels as $key => $label
) {
    if (
        isset($categories[$key])
    ) {
        $categoryLines[] =
            $label .
            ' : ' .
            $categories[$key] .
            '/100';
    }
}

if (empty($categoryLines)) {
    $categoryLines[] =
        'Aucune donnée disponible.';
}

/*
|--------------------------------------------------------------------------
| INFORMATIONS COMPLÉMENTAIRES
|--------------------------------------------------------------------------
*/

$ip = cleanValue(
    (string) (
        $_SERVER['REMOTE_ADDR'] ?? ''
    ),
    100
);

$userAgent = cleanValue(
    (string) (
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ),
    500
);

/*
 * On ne met pas l'adresse IP dans le sujet.
 * Elle reste uniquement dans le corps du message.
 */

/*
|--------------------------------------------------------------------------
| SUJET
|--------------------------------------------------------------------------
*/

$subject =
    '🔎 Nouvel audit Vitrine+ — ' .
    $websiteHost .
    ' — ' .
    $score .
    '/100';

/*
|--------------------------------------------------------------------------
| CORPS DU MAIL
|--------------------------------------------------------------------------
*/

$bodyLines = [
    'NOUVEL AUDIT DIGITAL — VITRINE+',
    '',
    'Un visiteur vient de lancer un audit sur Vitrine+.',
    '',
    'SITE ANALYSÉ',
    '────────────────────────────',
    $website,
    '',
    'RÉSULTAT',
    '────────────────────────────',
    'Score global : ' . $score . '/100',
    'Pages analysées : ' . $pagesAnalyzed,
    'Pages découvertes : ' . $pagesDiscovered,
    'Temps d’analyse : ' . $responseTime . ' s',
    '',
    'SCORES PAR CATÉGORIE',
    '────────────────────────────',
];

foreach ($categoryLines as $line) {
    $bodyLines[] = $line;
}

$bodyLines[] = '';
$bodyLines[] = 'INFORMATIONS TECHNIQUES';
$bodyLines[] = '────────────────────────────';

if ($ip !== '') {
    $bodyLines[] =
        'Adresse IP : ' . $ip;
}

if ($userAgent !== '') {
    $bodyLines[] =
        'Navigateur : ' . $userAgent;
}

$bodyLines[] = '';
$bodyLines[] =
    'Cet e-mail est une notification interne générée automatiquement par l’outil Audit de Vitrine+.';
$bodyLines[] = '';
$bodyLines[] = 'Vitrine+';

$messageBody = implode(
    "\r\n",
    $bodyLines
);

/*
|--------------------------------------------------------------------------
| ENCODAGE DU SUJET
|--------------------------------------------------------------------------
*/

$encodedSubject =
    '=?UTF-8?B?' .
    base64_encode($subject) .
    '?=';

/*
|--------------------------------------------------------------------------
| HEADERS
|--------------------------------------------------------------------------
*/

$headers = [
    'From: ' .
        $fromName .
        ' <' .
        $fromEmail .
        '>',

    'To: <' .
        NOTIFY_TO .
        '>',

    'Subject: ' .
        $encodedSubject,

    'Date: ' .
        date(DATE_RFC2822),

    'MIME-Version: 1.0',

    'Content-Type: text/plain; charset=UTF-8',

    'Content-Transfer-Encoding: 8bit',
];

$message =
    implode(
        "\r\n",
        $headers
    ) .
    "\r\n\r\n" .
    $messageBody;

/*
|--------------------------------------------------------------------------
| CONNEXION SMTP IONOS
|--------------------------------------------------------------------------
*/

$socket = @fsockopen(
    'ssl://' . $host,
    $port,
    $errno,
    $errstr,
    20
);

if (!$socket) {
    /*
     * IMPORTANT :
     * l'échec de l'e-mail ne doit pas
     * faire échouer l'audit.
     */
    respond(
        true,
        'Audit terminé.',
        [
            'notified' => false,
        ]
    );
}

stream_set_timeout(
    $socket,
    20
);

/*
|--------------------------------------------------------------------------
| ENVOI
|--------------------------------------------------------------------------
*/

try {
    smtpCommand(
        $socket,
        null,
        ['220']
    );

    smtpCommand(
        $socket,
        'EHLO vitrineplus.fr',
        ['250']
    );

    smtpCommand(
        $socket,
        'AUTH LOGIN',
        ['334']
    );

    smtpCommand(
        $socket,
        base64_encode($username),
        ['334']
    );

    smtpCommand(
        $socket,
        base64_encode($password),
        ['235']
    );

    smtpCommand(
        $socket,
        'MAIL FROM:<' .
        $fromEmail .
        '>',
        ['250']
    );

    smtpCommand(
        $socket,
        'RCPT TO:<' .
        NOTIFY_TO .
        '>',
        ['250', '251']
    );

    smtpCommand(
        $socket,
        'DATA',
        ['354']
    );

    fwrite(
        $socket,
        $message .
        "\r\n.\r\n"
    );

    smtpCommand(
        $socket,
        null,
        ['250']
    );

    smtpCommand(
        $socket,
        'QUIT',
        ['221']
    );

    fclose($socket);

    respond(
        true,
        'Audit terminé.',
        [
            'notified' => true,
        ]
    );

} catch (Throwable $e) {

    if (is_resource($socket)) {
        fclose($socket);
    }

    /*
     * L'audit a déjà réussi.
     * Une panne SMTP ne doit jamais
     * modifier le résultat côté visiteur.
     */
    respond(
        true,
        'Audit terminé.',
        [
            'notified' => false,
        ]
    );
}