<?php

header('Content-Type: application/json; charset=utf-8');

function respond($success, $message = '', $extra = [])
{
    http_response_code($success ? 200 : 400);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Méthode non autorisée.');
}

/*
|--------------------------------------------------------------------------
| Récupération des données
|--------------------------------------------------------------------------
*/

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$website = trim($_POST['website'] ?? '');
$score = trim($_POST['score'] ?? '');

$recommendations = [];

for ($i = 1; $i <= 3; $i++) {
    $value = trim($_POST["recommendation_$i"] ?? '');

    if ($value !== '') {
        $recommendations[] = $value;
    }
}

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($name === '') {
    respond(false, 'Votre nom est obligatoire.');
}

if (strlen($name) > 120) {
    respond(false, 'Le nom est trop long.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Adresse e-mail invalide.');
}

if (strlen($email) > 180) {
    respond(false, 'L’adresse e-mail est trop longue.');
}

if ($phone !== '' && strlen($phone) > 50) {
    respond(false, 'Le numéro de téléphone est trop long.');
}

if ($website !== '' && strlen($website) > 500) {
    respond(false, 'L’adresse du site est trop longue.');
}

if ($score !== '' && (!is_numeric($score) || $score < 0 || $score > 100)) {
    $score = '';
}

/*
|--------------------------------------------------------------------------
| Configuration SMTP
|--------------------------------------------------------------------------
*/

$configFile = __DIR__ . '/vitrine-mail-config.php';

if (!file_exists($configFile)) {
    respond(false, 'Configuration e-mail introuvable.');
}

$config = require $configFile;

$host = $config['smtp_host'] ?? '';
$port = (int) ($config['smtp_port'] ?? 465);
$username = $config['smtp_username'] ?? '';
$password = $config['smtp_password'] ?? '';
$fromEmail = $config['from_email'] ?? '';
$fromName = $config['from_name'] ?? 'Vitrine+';
$toEmail = $config['to_email'] ?? '';

if (
    $host === '' ||
    $username === '' ||
    $password === '' ||
    $fromEmail === '' ||
    $toEmail === ''
) {
    respond(false, 'Configuration SMTP incomplète.');
}

/*
|--------------------------------------------------------------------------
| Fonctions SMTP
|--------------------------------------------------------------------------
*/

function smtpRead($socket)
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtpCommand($socket, $command, $expectedCodes = [])
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }

    $response = smtpRead($socket);

    if (!empty($expectedCodes)) {
        $code = substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new Exception(
                'Réponse SMTP inattendue : ' . $response
            );
        }
    }

    return $response;
}

/*
|--------------------------------------------------------------------------
| Construction du message
|--------------------------------------------------------------------------
*/

$subject = 'Nouveau lead audit Vitrine+ — ' . $name;

$bodyLines = [
    'NOUVEAU LEAD — AUDIT DIGITAL VITRINE+',
    '',
    'Nom : ' . $name,
    'E-mail : ' . $email,
    'Téléphone : ' . ($phone !== '' ? $phone : 'Non renseigné'),
    'Site analysé : ' . ($website !== '' ? $website : 'Non renseigné'),
    'Score : ' . ($score !== '' ? $score . '/100' : 'Non renseigné'),
    '',
    'PRIORITÉS IDENTIFIÉES PAR L’AUDIT',
    '',
];

if (!empty($recommendations)) {
    foreach ($recommendations as $index => $recommendation) {
        $bodyLines[] = ($index + 1) . '. ' . $recommendation;
    }
} else {
    $bodyLines[] = 'Aucune recommandation transmise.';
}

$bodyLines[] = '';
$bodyLines[] = 'Ce prospect a demandé une analyse personnalisée depuis l’outil Audit de vitrineplus.fr.';
$bodyLines[] = '';
$bodyLines[] = 'Vitrine+';

$messageBody = implode("\r\n", $bodyLines);

$encodedSubject = '=?UTF-8?B?' .
    base64_encode($subject) .
    '?=';

$headers = [
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'To: <' . $toEmail . '>',
    'Reply-To: ' . $email,
    'Subject: ' . $encodedSubject,
    'Date: ' . date(DATE_RFC2822),
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$message = implode("\r\n", $headers)
    . "\r\n\r\n"
    . $messageBody;

/*
|--------------------------------------------------------------------------
| Connexion SMTP IONOS
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
    respond(
        false,
        'Impossible de contacter le serveur e-mail.'
    );
}

stream_set_timeout($socket, 20);

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
        'MAIL FROM:<' . $fromEmail . '>',
        ['250']
    );

    smtpCommand(
        $socket,
        'RCPT TO:<' . $toEmail . '>',
        ['250', '251']
    );

    smtpCommand(
        $socket,
        'DATA',
        ['354']
    );

    fwrite($socket, $message . "\r\n.\r\n");

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
        'Votre demande a bien été envoyée.'
    );

} catch (Throwable $e) {

    fclose($socket);

    respond(
        false,
        'Votre demande n’a pas pu être envoyée. Réessayez dans quelques instants.'
    );
}