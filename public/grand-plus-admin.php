<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE GRAND + — ADMINISTRATION
|--------------------------------------------------------------------------
|
| Administration privée des participations.
|
| Architecture volontairement compatible IONOS :
| - aucune session PHP
| - authentification HTTP Basic
| - données persistantes dans /vitrine-data/grand-plus
| - aucun framework
| - aucun appel externe
|
|--------------------------------------------------------------------------
*/

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';
const PARTICIPATIONS_FILE = DATA_DIR . '/participations.json';
const WINNER_FILE = __DIR__ . '/grand-plus-winner.json';
const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

const DEFAULT_TO_EMAIL = 'vitrineplus@hotmail.com';
const DEFAULT_FROM_NAME = 'Vitrine+';

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

function load_config(): array
{
    if (!file_exists(CONFIG_FILE)) {
        http_response_code(500);
        exit('Configuration Vitrine+ introuvable.');
    }

    $config = require CONFIG_FILE;

    if (!is_array($config)) {
        http_response_code(500);
        exit('Configuration Vitrine+ invalide.');
    }

    return $config;
}

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

function load_admin_credentials(): array
{
    $config = load_config();

    $username = trim(
        (string) ($config['grand_plus_admin_user'] ?? '')
    );

    $password = (string) (
        $config['grand_plus_admin_password'] ?? ''
    );

    if ($username === '' || $password === '') {
        http_response_code(500);

        exit(
            'Les identifiants administrateur du Grand + ne sont pas configurés.'
        );
    }

    return [
        'username' => $username,
        'password' => $password,
    ];
}

function require_auth(): void
{
    $credentials = load_admin_credentials();

    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        !isset($_SERVER['PHP_AUTH_PW'])
    ) {
        header(
            'WWW-Authenticate: Basic realm="Le Grand + — Administration"'
        );

        http_response_code(401);

        exit('Authentification requise.');
    }

    $user = (string) $_SERVER['PHP_AUTH_USER'];
    $password = (string) $_SERVER['PHP_AUTH_PW'];

    if (
        !hash_equals($credentials['username'], $user) ||
        !hash_equals($credentials['password'], $password)
    ) {
        header(
            'WWW-Authenticate: Basic realm="Le Grand + — Administration"'
        );

        http_response_code(401);

        exit('Identifiants incorrects.');
    }
}

require_auth();

/*
|--------------------------------------------------------------------------
| UTILITAIRES
|--------------------------------------------------------------------------
*/

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function read_json_file(
    string $file,
    mixed $default
): mixed {
    if (!file_exists($file)) {
        return $default;
    }

    $content = @file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return $default;
    }

    $decoded = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    return $decoded;
}

function write_json_file(
    string $file,
    mixed $data
): bool {
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    $directory = dirname($file);

    if (!is_dir($directory)) {
        if (!@mkdir($directory, 0750, true)) {
            if (!is_dir($directory)) {
                return false;
            }
        }
    }

    return @file_put_contents(
        $file,
        $json,
        LOCK_EX
    ) !== false;
}

function redirect_admin(string $message = ''): never
{
    $url = 'grand-plus-admin.php';

    if ($message !== '') {
        $url .= '?message=' . rawurlencode($message);
    }

    header('Location: ' . $url, true, 303);

    exit;
}

function current_month_key(): string
{
    return date('Y-m');
}

function current_month_label(): string
{
    $months = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    $month = (int) date('n');
    $year = date('Y');

    return $months[$month] . ' ' . $year;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
|
| Aucun stockage en session.
| Le token est calculé à partir du mot de passe admin
| et de l'heure courante.
|
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    $credentials = load_admin_credentials();

    $hour = date('Y-m-d-H');

    return hash_hmac(
        'sha256',
        $hour,
        $credentials['password']
    );
}

function csrf_is_valid(string $token): bool
{
    if ($token === '') {
        return false;
    }

    $credentials = load_admin_credentials();

    $currentHour = date('Y-m-d-H');

    $previousHour = date(
        'Y-m-d-H',
        strtotime('-1 hour')
    );

    $currentToken = hash_hmac(
        'sha256',
        $currentHour,
        $credentials['password']
    );

    $previousToken = hash_hmac(
        'sha256',
        $previousHour,
        $credentials['password']
    );

    return hash_equals($currentToken, $token)
        || hash_equals($previousToken, $token);
}

/*
|--------------------------------------------------------------------------
| WINNER
|--------------------------------------------------------------------------
*/

function load_winner(): ?array
{
    $winner = read_json_file(
        WINNER_FILE,
        null
    );

    return is_array($winner)
        ? $winner
        : null;
}

function save_winner(array $winner): bool
{
    return write_json_file(
        WINNER_FILE,
        $winner
    );
}

function clear_winner(): bool
{
    if (!file_exists(WINNER_FILE)) {
        return true;
    }

    return @unlink(WINNER_FILE);
}

/*
|--------------------------------------------------------------------------
| SMTP
|--------------------------------------------------------------------------
|
| Utilisation du serveur SMTP configuré dans
| vitrine-mail-config.php.
|
|--------------------------------------------------------------------------
*/

function smtp_read($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (
            strlen($line) >= 4 &&
            $line[3] === ' '
        ) {
            break;
        }
    }

    return $response;
}

function smtp_command(
    $socket,
    string $command,
    int $expectedCode
): bool {
    fwrite(
        $socket,
        $command . "\r\n"
    );

    $response = smtp_read($socket);

    $code = (int) substr(
        trim($response),
        0,
        3
    );

    return $code === $expectedCode;
}

function smtp_send_mail(
    string $to,
    string $subject,
    string $html,
    array $config
): bool {
    $host = (string) (
        $config['smtp_host'] ?? 'smtp.ionos.fr'
    );

    $port = (int) (
        $config['smtp_port'] ?? 465
    );

    $username = (string) (
        $config['smtp_username'] ?? ''
    );

    $password = (string) (
        $config['smtp_password'] ?? ''
    );

    $fromEmail = (string) (
        $config['from_email'] ?? $username
    );

    $fromName = (string) (
        $config['from_name'] ?? DEFAULT_FROM_NAME
    );

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
        15
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout(
        $socket,
        15
    );

    $greeting = smtp_read($socket);

    if ((int) substr(
        trim($greeting),
        0,
        3
    ) !== 220) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        'EHLO vitrineplus.fr',
        250
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        'AUTH LOGIN',
        334
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        base64_encode($username),
        334
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        base64_encode($password),
        235
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        'MAIL FROM:<' . $fromEmail . '>',
        250
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        'RCPT TO:<' . $to . '>',
        250
    )) {
        fclose($socket);
        return false;
    }

    if (!smtp_command(
        $socket,
        'DATA',
        354
    )) {
        fclose($socket);
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' .
        base64_encode($subject) .
        '?=';

    $encodedFromName = '=?UTF-8?B?' .
        base64_encode($fromName) .
        '?=';

    $headers = [];

    $headers[] =
        'From: ' .
        $encodedFromName .
        ' <' .
        $fromEmail .
        '>';

    $headers[] =
        'To: <' .
        $to .
        '>';

    $headers[] =
        'Subject: ' .
        $encodedSubject;

    $headers[] =
        'MIME-Version: 1.0';

    $headers[] =
        'Content-Type: text/html; charset=UTF-8';

    $headers[] =
        'Content-Transfer-Encoding: 8bit';

    $message =
        implode(
            "\r\n",
            $headers
        ) .
        "\r\n\r\n" .
        $html .
        "\r\n.\r\n";

    fwrite(
        $socket,
        $message
    );

    $response = smtp_read($socket);

    $success =
        (int) substr(
            trim($response),
            0,
            3
        ) === 250;

    smtp_command(
        $socket,
        'QUIT',
        221
    );

    fclose($socket);

    return $success;
}

/*
|--------------------------------------------------------------------------
| E-MAIL GAGNANT
|--------------------------------------------------------------------------
*/

function send_winner_email(
    array $participant,
    array $config
): bool {
    $email = normalize_email(
        (string) (
            $participant['email'] ?? ''
        )
    );

    if (
        $email === '' ||
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $name = h(
        (string) (
            $participant['name'] ?? ''
        )
    );

    $company = h(
        (string) (
            $participant['company'] ?? ''
        )
    );

    $month = h(
        (string) (
            $participant['month_label'] ??
            current_month_label()
        )
    );

    $subject =
        'Félicitations — vous avez remporté Le Grand + de Vitrine+';

    $html = '
    <!doctype html>
    <html lang="fr">
    <body style="
        margin:0;
        padding:40px 20px;
        background:#080808;
        color:#f5f5f2;
        font-family:Arial,Helvetica,sans-serif;
    ">
        <div style="
            max-width:620px;
            margin:0 auto;
            padding:40px;
            background:#111111;
            border:1px solid #292929;
        ">
            <div style="
                color:#c8a45d;
                font-size:12px;
                font-weight:bold;
                letter-spacing:3px;
                text-transform:uppercase;
            ">
                Vitrine+
            </div>

            <h1 style="
                margin:25px 0 20px;
                font-size:42px;
                line-height:1;
                color:#ffffff;
            ">
                Vous avez gagné.
            </h1>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Bonjour ' . $name . ',
            </p>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Nous avons le plaisir de vous annoncer que
                votre entreprise <strong style="color:#ffffff;">
                ' . $company . '
                </strong> a été tirée au sort et remporte
                <strong style="color:#c8a45d;">
                Le Grand +
                </strong> pour le mois de ' . $month . '.
            </p>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Vitrine+ prendra contact avec vous afin de
                lancer ensemble la refonte de votre site internet,
                entièrement offerte dans le cadre du Grand +.
            </p>

            <div style="
                margin:30px 0;
                padding:22px;
                border:1px solid #3a3a3a;
                background:#0b0b0b;
            ">
                <div style="
                    color:#888888;
                    font-size:11px;
                    text-transform:uppercase;
                    letter-spacing:2px;
                ">
                    Votre gain
                </div>

                <div style="
                    margin-top:8px;
                    color:#c8a45d;
                    font-size:25px;
                    font-weight:bold;
                ">
                    Refonte complète de votre site
                </div>
            </div>

            <p style="
                color:#888888;
                font-size:14px;
                line-height:1.6;
            ">
                Merci d’avoir participé au Grand +.
            </p>

            <p style="
                color:#ffffff;
                font-size:15px;
                font-weight:bold;
            ">
                L’équipe Vitrine+
            </p>
        </div>
    </body>
    </html>
    ';

    return smtp_send_mail(
        $email,
        $subject,
        $html,
        $config
    );
}

/*
|--------------------------------------------------------------------------
| E-MAIL NON GAGNANT
|--------------------------------------------------------------------------
*/

function send_non_winner_email(
    array $participant,
    array $config
): bool {
    $email = normalize_email(
        (string) (
            $participant['email'] ?? ''
        )
    );

    if (
        $email === '' ||
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $name = h(
        (string) (
            $participant['name'] ?? ''
        )
    );

    $company = h(
        (string) (
            $participant['company'] ?? ''
        )
    );

    $subject =
        'Le Grand + — Résultat du tirage Vitrine+';

    $html = '
    <!doctype html>
    <html lang="fr">
    <body style="
        margin:0;
        padding:40px 20px;
        background:#080808;
        color:#f5f5f2;
        font-family:Arial,Helvetica,sans-serif;
    ">
        <div style="
            max-width:620px;
            margin:0 auto;
            padding:40px;
            background:#111111;
            border:1px solid #292929;
        ">
            <div style="
                color:#c8a45d;
                font-size:12px;
                font-weight:bold;
                letter-spacing:3px;
                text-transform:uppercase;
            ">
                Vitrine+
            </div>

            <h1 style="
                margin:25px 0 20px;
                font-size:38px;
                line-height:1;
                color:#ffffff;
            ">
                Merci pour votre participation.
            </h1>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Bonjour ' . $name . ',
            </p>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Merci à vous et à <strong style="color:#ffffff;">
                ' . $company . '
                </strong> d’avoir participé au Grand + de Vitrine+.
            </p>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Le tirage de ce mois-ci a désormais été effectué
                et votre participation n’a malheureusement pas été
                sélectionnée cette fois-ci.
            </p>

            <p style="
                color:#cccccc;
                font-size:17px;
                line-height:1.7;
            ">
                Mais le Grand + revient chaque mois.
                Vous pourrez donc participer à nouveau lors d’une
                prochaine édition.
            </p>

            <p style="
                color:#888888;
                font-size:14px;
                line-height:1.6;
            ">
                Merci encore pour votre confiance.
            </p>

            <p style="
                color:#ffffff;
                font-size:15px;
                font-weight:bold;
            ">
                L’équipe Vitrine+
            </p>
        </div>
    </body>
    </html>
    ';

    return smtp_send_mail(
        $email,
        $subject,
        $html,
        $config
    );
}

/*
|--------------------------------------------------------------------------
| ACTIONS ADMIN
|--------------------------------------------------------------------------
*/

$actionMessage = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!csrf_is_valid($token)) {
        http_response_code(403);
        exit('Jeton de sécurité invalide ou expiré.');
    }

    $action = (string) (
        $_POST['action'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | TIRAGE
    |--------------------------------------------------------------------------
    */

    if ($action === 'select_winner') {

        $participations = read_json_file(
            PARTICIPATIONS_FILE,
            []
        );

        if (!is_array($participations)) {
            $participations = [];
        }

        $currentMonth = current_month_key();

        $eligibleIndexes = [];

        foreach ($participations as $index => $participant) {

            if (!is_array($participant)) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $currentMonth
            ) {
                continue;
            }

            $status = (string) (
                $participant['status'] ?? 'pending'
            );

            if ($status === 'pending') {
                $eligibleIndexes[] = $index;
            }
        }

        if (count($eligibleIndexes) === 0) {

            $existingWinner = load_winner();

            if ($existingWinner !== null) {
                redirect_admin(
                    'Un gagnant est déjà enregistré pour ce mois.'
                );
            }

            redirect_admin(
                'Aucun participant éligible pour le tirage.'
            );
        }

        $winnerIndex = $eligibleIndexes[
            random_int(
                0,
                count($eligibleIndexes) - 1
            )
        ];

        $winnerParticipant =
            $participations[$winnerIndex];

        /*
        |--------------------------------------------------------------------------
        | STATUTS
        |--------------------------------------------------------------------------
        */

        foreach ($participations as $index => &$participant) {

            if (!is_array($participant)) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $currentMonth
            ) {
                continue;
            }

            if ($index === $winnerIndex) {
                $participant['status'] = 'winner';
            } else {
                $participant['status'] = 'not_winner';
            }
        }

        unset($participant);

        /*
        |--------------------------------------------------------------------------
        | SAUVEGARDE DES PARTICIPATIONS
        |--------------------------------------------------------------------------
        */

        if (!write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        )) {
            redirect_admin(
                'Erreur : impossible de sauvegarder les statuts.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SAUVEGARDE DU GAGNANT
        |--------------------------------------------------------------------------
        */

        $winnerData = [
            'month_key' => $currentMonth,
            'month_label' => current_month_label(),
            'selected_at' => date(
                'c'
            ),
            'participation_id' => (string) (
                $winnerParticipant['id'] ?? ''
            ),
            'name' => (string) (
                $winnerParticipant['name'] ?? ''
            ),
            'company' => (string) (
                $winnerParticipant['company'] ?? ''
            ),
            'email' => (string) (
                $winnerParticipant['email'] ?? ''
            ),
        ];

        if (!save_winner($winnerData)) {

            /*
            | On ne revient pas sur les statuts si le winner file
            | ne peut pas être écrit.
            */

            redirect_admin(
                'Attention : les statuts ont été enregistrés, mais le fichier gagnant n’a pas pu être créé.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | E-MAILS
        |--------------------------------------------------------------------------
        */

        $config = load_config();

        $winnerEmailSent =
            send_winner_email(
                $winnerParticipant,
                $config
            );

        $nonWinnerEmailsSent = 0;

        foreach ($participations as $index => $participant) {

            if (!is_array($participant)) {
                continue;
            }

            if ($index === $winnerIndex) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $currentMonth
            ) {
                continue;
            }

            if (
                ($participant['status'] ?? '') !==
                'not_winner'
            ) {
                continue;
            }

            /*
            | Seulement les participants ayant accepté
            | les communications marketing.
            |
            | Le mail de résultat reste ici volontairement
            | limité au cadre du Grand +.
            */

            if (
                !empty(
                    $participant['marketing_consent']
                )
            ) {
                if (
                    send_non_winner_email(
                        $participant,
                        $config
                    )
                ) {
                    $nonWinnerEmailsSent++;
                }
            }
        }

        $company = (string) (
            $winnerParticipant['company'] ?? ''
        );

        $winnerStatus = $winnerEmailSent
            ? 'e-mail gagnant envoyé'
            : 'e-mail gagnant non envoyé';

        redirect_admin(
            'Gagnant enregistré : ' .
            $company .
            ' — ' .
            $winnerStatus .
            ' — ' .
            $nonWinnerEmailsSent .
            ' e-mail(s) non-gagnant envoyé(s).'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RESET
    |--------------------------------------------------------------------------
    */

    if ($action === 'reset_winner') {

        $participations = read_json_file(
            PARTICIPATIONS_FILE,
            []
        );

        if (!is_array($participations)) {
            $participations = [];
        }

        $currentMonth = current_month_key();

        foreach ($participations as &$participant) {

            if (!is_array($participant)) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $currentMonth
            ) {
                continue;
            }

            unset($participant['status']);
        }

        unset($participant);

        if (!write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        )) {
            redirect_admin(
                'Erreur : impossible de réinitialiser les participations.'
            );
        }

        if (!clear_winner()) {
            redirect_admin(
                'Les participations ont été réinitialisées, mais le fichier gagnant n’a pas pu être supprimé.'
            );
        }

        redirect_admin(
            'Le tirage du mois a été réinitialisé.'
        );
    }

    redirect_admin(
        'Action inconnue.'
    );
}

/*
|--------------------------------------------------------------------------
| LECTURE
|--------------------------------------------------------------------------
*/

$participations = read_json_file(
    PARTICIPATIONS_FILE,
    []
);

if (!is_array($participations)) {
    $participations = [];
}

$currentMonth = current_month_key();

$currentMonthParticipants = array_values(
    array_filter(
        $participations,
        static function ($item) use ($currentMonth): bool {
            return is_array($item)
                && (
                    $item['month_key'] ?? ''
                ) === $currentMonth;
        }
    )
);

$total = count(
    $currentMonthParticipants
);

$pending = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? 'pending') === 'pending'
    )
);

$winner = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? '') === 'winner'
    )
);

$notWinner = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? '') === 'not_winner'
    )
);

$marketing = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            !empty($item['marketing_consent'])
    )
);

$currentWinner = load_winner();

$message = (string) (
    $_GET['message'] ?? ''
);

$hasWinner =
    $currentWinner !== null
    || $winner > 0;

$csrf = csrf_token();

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Le Grand + — Administration
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            background: #080808;
        }

        body {
            margin: 0;
            background: #080808;
            color: #f5f5f2;
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Helvetica Neue",
                Helvetica,
                Arial,
                sans-serif;
        }

        button,
        input {
            font: inherit;
        }

        .container {
            width: min(
                1400px,
                calc(100% - 40px)
            );

            margin: 0 auto;

            padding: 60px 0;
        }

        .eyebrow {
            color: #c8a45d;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .24em;
            text-transform: uppercase;
        }

        h1 {
            margin: 14px 0 0;

            font-size:
                clamp(42px, 7vw, 88px);

            line-height: .9;

            letter-spacing: -.06em;
        }

        .month {
            margin-top: 18px;

            color:
                rgba(255,255,255,.45);

            font-size: 16px;
        }

        .path {
            margin-top: 10px;

            color:
                rgba(255,255,255,.2);

            font-size: 11px;

            word-break: break-all;
        }

        .message {
            margin-top: 30px;

            padding: 18px 20px;

            border:
                1px solid
                rgba(200,164,93,.35);

            border-radius: 16px;

            background:
                rgba(200,164,93,.08);

            color: #c8a45d;

            line-height: 1.5;
        }

        .stats {
            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 12px;

            margin-top: 50px;
        }

        .stat {
            padding: 24px;

            border:
                1px solid
                rgba(255,255,255,.09);

            border-radius: 22px;

            background: #111;
        }

        .stat-label {
            color:
                rgba(255,255,255,.35);

            font-size: 11px;

            font-weight: 800;

            letter-spacing: .16em;

            text-transform: uppercase;
        }

        .stat-value {
            margin-top: 12px;

            font-size: 36px;

            font-weight: 800;

            letter-spacing: -.04em;
        }

        .winner-card {
            margin-top: 30px;

            padding: 28px;

            border:
                1px solid
                rgba(200,164,93,.35);

            border-radius: 24px;

            background:
                linear-gradient(
                    135deg,
                    rgba(200,164,93,.12),
                    rgba(255,255,255,.025)
                );
        }

        .winner-title {
            color: #c8a45d;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: .18em;

            text-transform: uppercase;
        }

        .winner-company {
            margin-top: 10px;

            font-size: 30px;

            font-weight: 800;

            letter-spacing: -.04em;
        }

        .winner-contact {
            margin-top: 8px;

            color:
                rgba(255,255,255,.45);

            font-size: 14px;
        }

        .actions {
            display: flex;

            flex-wrap: wrap;

            gap: 12px;

            margin-top: 24px;
        }

        .button {
            appearance: none;

            border: 0;

            border-radius: 999px;

            padding: 14px 20px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 800;

            letter-spacing: .08em;

            text-transform: uppercase;

            transition:
                transform .2s ease,
                opacity .2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            background: #c8a45d;
            color: #080808;
        }

        .button-secondary {
            background: #ffffff;
            color: #080808;
        }

        .button-danger {
            background:
                rgba(255,255,255,.08);

            color:
                rgba(255,255,255,.65);
        }

        .button:disabled {
            cursor: not-allowed;
            opacity: .35;
            transform: none;
        }

        .warning {
            margin-top: 16px;

            color:
                rgba(255,255,255,.35);

            font-size: 12px;

            line-height: 1.6;
        }

        .table-wrap {
            margin-top: 50px;

            overflow-x: auto;

            border:
                1px solid
                rgba(255,255,255,.09);

            border-radius: 24px;

            background: #111;
        }

        table {
            width: 100%;

            min-width: 1050px;

            border-collapse: collapse;
        }

        th,
        td {
            padding: 18px 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.07);

            text-align: left;

            vertical-align: top;
        }

        th {
            color:
                rgba(255,255,255,.35);

            font-size: 10px;

            letter-spacing: .16em;

            text-transform: uppercase;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .company {
            font-weight: 800;
        }

        .muted {
            margin-top: 4px;

            color:
                rgba(255,255,255,.4);

            font-size: 12px;
        }

        .problem {
            max-width: 280px;

            color:
                rgba(255,255,255,.55);

            line-height: 1.5;
        }

        .badge {
            display: inline-flex;

            align-items: center;

            border-radius: 999px;

            padding: 7px 11px;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: .08em;

            text-transform: uppercase;
        }

        .pending {
            background:
                rgba(255,255,255,.08);

            color:
                rgba(255,255,255,.6);
        }

        .winner {
            background:
                rgba(200,164,93,.16);

            color: #c8a45d;
        }

        .not-winner {
            background:
                rgba(255,255,255,.05);

            color:
                rgba(255,255,255,.35);
        }

        .yes {
            color: #c8a45d;

            font-weight: 800;
        }

        .no {
            color:
                rgba(255,255,255,.3);
        }

        .empty {
            padding: 60px;

            text-align: center;

            color:
                rgba(255,255,255,.4);
        }

        @media (max-width: 1000px) {

            .stats {
                grid-template-columns:
                    repeat(3, 1fr);
            }

        }

        @media (max-width: 700px) {

            .container {
                width:
                    calc(100% - 24px);

                padding:
                    35px 0;
            }

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .winner-company {
                font-size: 25px;
            }

        }

        @media (max-width: 480px) {

            .stats {
                grid-template-columns:
                    1fr 1fr;
            }

            .stat {
                padding: 18px;
            }

            .stat-value {
                font-size: 28px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="eyebrow">
        Vitrine+ — Administration
    </div>

    <h1>
        Le Grand +
    </h1>

    <div class="month">
        <?= h(current_month_label()) ?>
    </div>

    <div class="path">
        Données : <?= h(PARTICIPATIONS_FILE) ?>
    </div>

    <?php if ($message !== ''): ?>

        <div class="message">
            <?= h($message) ?>
        </div>

    <?php endif; ?>

    <div class="stats">

        <div class="stat">

            <div class="stat-label">
                Participants
            </div>

            <div class="stat-value">
                <?= $total ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                En attente
            </div>

            <div class="stat-value">
                <?= $pending ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Gagnant
            </div>

            <div class="stat-value">
                <?= $winner ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Non gagnants
            </div>

            <div class="stat-value">
                <?= $notWinner ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Marketing
            </div>

            <div class="stat-value">
                <?= $marketing ?>
            </div>

        </div>

    </div>

    <?php if ($hasWinner && $currentWinner !== null): ?>

        <div class="winner-card">

            <div class="winner-title">
                🏆 Gagnant du mois
            </div>

            <div class="winner-company">
                <?= h(
                    (string) (
                        $currentWinner['company']
                        ?? ''
                    )
                ) ?>
            </div>

            <div class="winner-contact">

                <?= h(
                    (string) (
                        $currentWinner['name']
                        ?? ''
                    )
                ) ?>

                ·

                <?= h(
                    (string) (
                        $currentWinner['email']
                        ?? ''
                    )
                ) ?>

            </div>

            <div class="actions">

                <form
                    method="post"
                    onsubmit="
                        return confirm(
                            'Réinitialiser le tirage de ce mois ?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="reset_winner"
                    >

                    <button
                        type="submit"
                        class="button button-danger"
                    >
                        Réinitialiser le tirage
                    </button>

                </form>

            </div>

            <div class="warning">
                La réinitialisation remet les participants du mois
                en attente et supprime le gagnant enregistré.
            </div>

        </div>

    <?php elseif ($total > 0): ?>

        <div class="winner-card">

            <div class="winner-title">
                Tirage du mois
            </div>

            <div class="winner-company">
                <?= $pending ?>
                participant<?= $pending > 1 ? 's' : '' ?>
                éligible<?= $pending > 1 ? 's' : '' ?>
            </div>

            <div class="winner-contact">
                Le tirage sélectionnera aléatoirement un participant
                actuellement en attente.
            </div>

            <div class="actions">

                <form
                    method="post"
                    onsubmit="
                        return confirm(
                            'Lancer le tirage du Grand + pour ce mois ?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="select_winner"
                    >

                    <button
                        type="submit"
                        class="button button-primary"
                        <?= $pending === 0 ? 'disabled' : '' ?>
                    >
                        🎯 Tirer le gagnant
                    </button>

                </form>

            </div>

            <div class="warning">
                Une fois le tirage effectué, le gagnant et les
                participants non gagnants seront enregistrés.
            </div>

        </div>

    <?php endif; ?>

    <div class="table-wrap">

        <?php if ($total === 0): ?>

            <div class="empty">
                Aucun participant pour
                <?= h(current_month_label()) ?>.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Entreprise
                        </th>

                        <th>
                            Contact
                        </th>

                        <th>
                            Activité
                        </th>

                        <th>
                            Problématique
                        </th>

                        <th>
                            Marketing
                        </th>

                        <th>
                            Statut
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $currentMonthParticipants
                    as $participant
                ): ?>

                    <?php

                    $status = (string) (
                        $participant['status']
                        ?? 'pending'
                    );

                    ?>

                    <tr>

                        <td>

                            <div class="company">

                                <?= h(
                                    (string) (
                                        $participant['company']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant['sector']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>

                        <td>

                            <div>

                                <?= h(
                                    (string) (
                                        $participant['name']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant['email']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant['phone']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>

                        <td>

                            <?php if (
                                !empty(
                                    $participant['website']
                                )
                            ): ?>

                                <a
                                    href="<?= h(
                                        (string)
                                        $participant['website']
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="
                                        color:#c8a45d;
                                    "
                                >
                                    Voir le site
                                </a>

                            <?php else: ?>

                                <span class="no">
                                    Aucun site
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="problem">

                                <?= h(
                                    (string) (
                                        $participant['problem']
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>

                        <td>

                            <?php if (
                                !empty(
                                    $participant[
                                        'marketing_consent'
                                    ]
                                )
                            ): ?>

                                <span class="yes">
                                    Oui
                                </span>

                            <?php else: ?>

                                <span class="no">
                                    Non
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (
                                $status === 'winner'
                            ): ?>

                                <span class="
                                    badge winner
                                ">
                                    Gagnant
                                </span>

                            <?php elseif (
                                $status === 'not_winner'
                            ): ?>

                                <span class="
                                    badge not-winner
                                ">
                                    Non gagnant
                                </span>

                            <?php else: ?>

                                <span class="
                                    badge pending
                                ">
                                    En attente
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>

</html>