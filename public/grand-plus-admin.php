<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| LE GRAND + — ADMINISTRATION
|--------------------------------------------------------------------------
|
| Administration privée des participations.
|
| Fonctionnalités :
| - consulter les participations
| - filtrer par mois
| - désigner un gagnant
| - envoyer automatiquement l'e-mail au gagnant
| - envoyer automatiquement l'e-mail aux autres participants
| - éviter les doublons d'e-mails
| - réinitialiser le gagnant
|
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';
const WINNER_FILE = __DIR__ . '/grand-plus-winner.json';

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';
const PARTICIPATIONS_FILE = DATA_DIR . '/participations.json';

const DEFAULT_TO_EMAIL = 'vitrineplus@hotmail.com';
const DEFAULT_FROM_NAME = 'Vitrine+';

const OFFER_VALIDITY_DAYS = 30;
const OFFER_AMOUNT = 300;

/*
|--------------------------------------------------------------------------
| UTILITAIRES
|--------------------------------------------------------------------------
*/

function load_config(): array
{
    if (!file_exists(CONFIG_FILE)) {
        return [];
    }

    $config = require CONFIG_FILE;

    return is_array($config) ? $config : [];
}

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['grand_plus_admin_authenticated']);
}

function csrf_token(): string
{
    if (empty($_SESSION['grand_plus_admin_csrf'])) {
        $_SESSION['grand_plus_admin_csrf'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['grand_plus_admin_csrf'];
}

function verify_csrf(): bool
{
    return isset($_POST['csrf'])
        && isset($_SESSION['grand_plus_admin_csrf'])
        && hash_equals(
            $_SESSION['grand_plus_admin_csrf'],
            (string) $_POST['csrf']
        );
}

/*
|--------------------------------------------------------------------------
| PARTICIPATIONS
|--------------------------------------------------------------------------
*/

function load_participations(): array
{
    if (!file_exists(PARTICIPATIONS_FILE)) {
        return [];
    }

    $json = file_get_contents(PARTICIPATIONS_FILE);

    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function save_participations(array $participations): bool
{
    if (!is_dir(DATA_DIR)) {
        if (!mkdir(DATA_DIR, 0755, true) && !is_dir(DATA_DIR)) {
            return false;
        }
    }

    $json = json_encode(
        $participations,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return file_put_contents(
        PARTICIPATIONS_FILE,
        $json,
        LOCK_EX
    ) !== false;
}

/*
|--------------------------------------------------------------------------
| DATES
|--------------------------------------------------------------------------
*/

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

    return $months[(int) date('n')] . ' ' . date('Y');
}

function format_date(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d/m/Y à H:i', $timestamp);
}

function selected_month(): string
{
    $month = $_GET['month']
        ?? $_POST['month']
        ?? current_month_key();

    if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
        return current_month_key();
    }

    return (string) $month;
}

function month_label_from_key(string $key): string
{
    $parts = explode('-', $key);

    if (count($parts) !== 2) {
        return $key;
    }

    $months = [
        '01' => 'Janvier',
        '02' => 'Février',
        '03' => 'Mars',
        '04' => 'Avril',
        '05' => 'Mai',
        '06' => 'Juin',
        '07' => 'Juillet',
        '08' => 'Août',
        '09' => 'Septembre',
        '10' => 'Octobre',
        '11' => 'Novembre',
        '12' => 'Décembre',
    ];

    return ($months[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
}

/*
|--------------------------------------------------------------------------
| GAGNANT
|--------------------------------------------------------------------------
*/

function load_winner(): array
{
    $default = [
        'hasWinner' => false,
        'month' => '',
        'company' => '',
        'description' => '',
        'website' => '',
        'image' => '',
        'winner_id' => '',
        'winner_email_sent' => false,
        'loser_emails_sent' => 0,
        'notification_completed_at' => null,
    ];

    if (!file_exists(WINNER_FILE)) {
        return $default;
    }

    $json = file_get_contents(WINNER_FILE);

    if ($json === false) {
        return $default;
    }

    $winner = json_decode($json, true);

    if (!is_array($winner)) {
        return $default;
    }

    return array_merge($default, $winner);
}

function save_winner(array $winner): bool
{
    $json = json_encode(
        $winner,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return file_put_contents(
        WINNER_FILE,
        $json,
        LOCK_EX
    ) !== false;
}

/*
|--------------------------------------------------------------------------
| SMTP
|--------------------------------------------------------------------------
*/

function smtp_read_response($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (
            strlen($line) >= 4
            && $line[3] === ' '
        ) {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes): bool
{
    $response = smtp_read_response($socket);

    if ($response === '') {
        return false;
    }

    $code = (int) substr($response, 0, 3);

    return in_array($code, $codes, true);
}

function smtp_command(
    $socket,
    string $command,
    array $codes
): bool {
    fwrite(
        $socket,
        $command . "\r\n"
    );

    return smtp_expect(
        $socket,
        $codes
    );
}

function smtp_send_html_email(
    array $config,
    string $to,
    string $subject,
    string $html,
    ?string $replyTo = null
): bool {
    $host = trim(
        (string) ($config['smtp_host'] ?? '')
    );

    $port = (int) (
        $config['smtp_port'] ?? 465
    );

    $username = trim(
        (string) ($config['smtp_username'] ?? '')
    );

    $password = (string) (
        $config['smtp_password'] ?? ''
    );

    $fromEmail = trim(
        (string) ($config['from_email'] ?? '')
    );

    $fromName = trim(
        (string) (
            $config['from_name']
            ?? DEFAULT_FROM_NAME
        )
    );

    if (
        $host === ''
        || $username === ''
        || $password === ''
        || $fromEmail === ''
        || !filter_var(
            $to,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $socket = @fsockopen(
        'ssl://' . $host,
        $port,
        $errno,
        $errstr,
        20
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout(
        $socket,
        20
    );

    try {
        if (!smtp_expect($socket, [220])) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                'EHLO vitrineplus.fr',
                [250]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                'AUTH LOGIN',
                [334]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                base64_encode($username),
                [334]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                base64_encode($password),
                [235]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                'MAIL FROM:<' . $fromEmail . '>',
                [250]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                'RCPT TO:<' . $to . '>',
                [250, 251]
            )
        ) {
            fclose($socket);
            return false;
        }

        if (
            !smtp_command(
                $socket,
                'DATA',
                [354]
            )
        ) {
            fclose($socket);
            return false;
        }

        $encodedSubject =
            '=?UTF-8?B?'
            . base64_encode($subject)
            . '?=';

        $headers =
            'From: '
            . $fromName
            . ' <'
            . $fromEmail
            . ">\r\n"
            . 'To: <'
            . $to
            . ">\r\n"
            . 'Subject: '
            . $encodedSubject
            . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";

        if (
            $replyTo !== null
            && filter_var(
                $replyTo,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $headers .=
                'Reply-To: '
                . $replyTo
                . "\r\n";
        }

        $message =
            $headers
            . "\r\n"
            . $html
            . "\r\n.";

        if (
            !smtp_command(
                $socket,
                $message,
                [250]
            )
        ) {
            fclose($socket);
            return false;
        }

        smtp_command(
            $socket,
            'QUIT',
            [221]
        );

        fclose($socket);

        return true;

    } catch (Throwable $e) {
        fclose($socket);

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| TEMPLATE EMAIL
|--------------------------------------------------------------------------
*/

function email_layout(
    string $content
): string {
    return '
<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>
<title>Vitrine+ — Le Grand +</title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f5f5f3;
        font-family:Arial,Helvetica,sans-serif;
        color:#080808;
    "
>

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        background:#f5f5f3;
        padding:40px 15px;
    "
>
<tr>
<td align="center">

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        max-width:620px;
        background:#ffffff;
        border-radius:24px;
        overflow:hidden;
    "
>

<tr>
<td
    style="
        background:#080808;
        padding:32px 36px;
    "
>

<div
    style="
        font-size:28px;
        font-weight:800;
        color:#ffffff;
    "
>
    Vitrine<span style="color:#C8A45D;">+</span>
</div>

<div
    style="
        margin-top:8px;
        font-size:11px;
        letter-spacing:3px;
        text-transform:uppercase;
        color:#C8A45D;
        font-weight:700;
    "
>
    Le Grand +
</div>

</td>
</tr>

<tr>
<td
    style="
        padding:42px 36px;
    "
>
'
        . $content .
'
</td>
</tr>

<tr>
<td
    style="
        padding:25px 36px;
        border-top:1px solid #eeeeee;
        color:#999999;
        font-size:12px;
        line-height:1.6;
    "
>
    Vitrine+ — Votre entreprise. En mieux.<br>

    <a
        href="https://vitrineplus.fr"
        style="color:#999999;"
    >
        vitrineplus.fr
    </a>
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';
}

/*
|--------------------------------------------------------------------------
| EMAIL GAGNANT
|--------------------------------------------------------------------------
*/

function send_winner_email(
    array $config,
    array $participant
): bool {
    $name = trim(
        (string) (
            $participant['name'] ?? ''
        )
    );

    $email = trim(
        (string) (
            $participant['email'] ?? ''
        )
    );

    $company = trim(
        (string) (
            $participant['company'] ?? ''
        )
    );

    $month = trim(
        (string) (
            $participant['month_label']
            ?? current_month_label()
        )
    );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $safeName = htmlspecialchars(
        $name,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeCompany = htmlspecialchars(
        $company,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeMonth = htmlspecialchars(
        $month,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $content = '
<h1
    style="
        margin:0 0 22px;
        font-size:32px;
        line-height:1.1;
    "
>
    Félicitations '
    . $safeName .
    ' 🎉
</h1>

<p
    style="
        font-size:17px;
        line-height:1.7;
        margin:0 0 20px;
    "
>
    Nous avons une excellente nouvelle
    à vous annoncer.
</p>

<p
    style="
        font-size:17px;
        line-height:1.7;
        margin:0 0 24px;
    "
>
    Votre entreprise
    <strong>'
    . $safeCompany .
    '</strong>
    a été tirée au sort et devient
    <strong>
        la grande gagnante du Grand +
        de '
    . $safeMonth .
    '
    </strong>.
</p>

<div
    style="
        background:#080808;
        color:#ffffff;
        border-radius:18px;
        padding:24px;
        margin:25px 0;
    "
>

<div
    style="
        font-size:11px;
        letter-spacing:2px;
        text-transform:uppercase;
        color:#C8A45D;
        font-weight:700;
    "
>
    Votre cadeau
</div>

<div
    style="
        font-size:22px;
        font-weight:800;
        margin-top:10px;
    "
>
    La refonte complète de votre site internet
</div>

<div
    style="
        font-size:15px;
        line-height:1.6;
        color:#cccccc;
        margin-top:10px;
    "
>
    100 % offerte par Vitrine+.
</div>

</div>

<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Notre équipe va prochainement prendre
    contact avec vous afin d’échanger sur
    votre activité, vos besoins et votre
    vision du futur site.
</p>

<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Vous n’avez rien à faire pour le moment :
    nous revenons vers vous très rapidement.
</p>

<p
    style="
        font-size:16px;
        line-height:1.7;
        margin-top:30px;
    "
>
    Encore toutes nos félicitations.
</p>

<p
    style="
        font-weight:800;
        font-size:17px;
        margin-top:28px;
    "
>
    L’équipe Vitrine+
</p>
';

    return smtp_send_html_email(
        $config,
        $email,
        '🎉 Vous êtes le gagnant du Grand + de ' . $month,
        email_layout($content),
        $config['from_email'] ?? null
    );
}

/*
|--------------------------------------------------------------------------
| EMAIL PARTICIPANT NON GAGNANT
|--------------------------------------------------------------------------
*/

function send_non_winner_email(
    array $config,
    array $participant
): bool {
    $name = trim(
        (string) (
            $participant['name'] ?? ''
        )
    );

    $email = trim(
        (string) (
            $participant['email'] ?? ''
        )
    );

    $month = trim(
        (string) (
            $participant['month_label']
            ?? current_month_label()
        )
    );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $safeName = htmlspecialchars(
        $name,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeMonth = htmlspecialchars(
        $month,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $marketingConsent = !empty(
        $participant['marketing_consent']
    );

    $offerBlock = '';

    /*
     * L'offre commerciale n'est envoyée
     * que si le participant a explicitement
     * accepté les communications marketing.
     */
    if ($marketingConsent) {

        $offerBlock = '
<div
    style="
        background:#f7f4ec;
        border:1px solid #eadfc8;
        border-radius:18px;
        padding:25px;
        margin:28px 0;
    "
>

<div
    style="
        font-size:11px;
        letter-spacing:2px;
        text-transform:uppercase;
        color:#9a773d;
        font-weight:700;
    "
>
    Votre avantage exclusif
</div>

<div
    style="
        font-size:23px;
        font-weight:800;
        margin-top:10px;
    "
>
    Audit stratégique offert
</div>

<p
    style="
        font-size:15px;
        line-height:1.6;
        color:#555555;
        margin:12px 0 0;
    "
>
    Et
    <strong>'
    . OFFER_AMOUNT .
    ' € offerts</strong>
    sur la création ou la refonte
    de votre site internet.
</p>

<p
    style="
        font-size:14px;
        line-height:1.6;
        color:#777777;
        margin:14px 0 0;
    "
>
    Cet avantage est réservé aux participants
    du Grand + et valable pendant
    <strong>'
    . OFFER_VALIDITY_DAYS .
    ' jours</strong>.
</p>

<a
    href="https://vitrineplus.fr/audit"
    style="
        display:inline-block;
        margin-top:20px;
        background:#080808;
        color:#ffffff;
        text-decoration:none;
        padding:14px 20px;
        border-radius:12px;
        font-weight:700;
    "
>
    Profiter de mon avantage →
</a>

</div>';
    }

    $closing = $marketingConsent
        ? '
<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Merci encore pour votre participation.
    Nous espérons pouvoir vous accompagner
    prochainement dans votre projet.
</p>'
        : '
<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Merci encore pour votre participation
    au Grand +.
</p>';

    $content = '
<h1
    style="
        margin:0 0 22px;
        font-size:30px;
        line-height:1.1;
    "
>
    Merci pour votre participation
</h1>

<p
    style="
        font-size:17px;
        line-height:1.7;
        margin:0 0 20px;
    "
>
    Bonjour '
    . $safeName .
    ',
</p>

<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Le tirage au sort du
    <strong>
        Grand + de '
    . $safeMonth .
    '
    </strong>
    vient d’avoir lieu.
</p>

<p
    style="
        font-size:16px;
        line-height:1.7;
    "
>
    Cette fois, votre entreprise n’a
    malheureusement pas été tirée au sort.
</p>

'
    . $offerBlock .
    $closing .
'
<p
    style="
        font-size:16px;
        line-height:1.7;
        margin-top:28px;
    "
>
    À bientôt,
</p>

<p
    style="
        font-weight:800;
        font-size:17px;
    "
>
    L’équipe Vitrine+
</p>
';

    return smtp_send_html_email(
        $config,
        $email,
        'Le Grand + de ' . $month . ' — Merci pour votre participation',
        email_layout($content),
        $config['from_email'] ?? null
    );
}

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

$config = load_config();

$adminUser = trim(
    (string) (
        $config['grand_plus_admin_user'] ?? ''
    )
);

$adminPassword = (string) (
    $config['grand_plus_admin_password'] ?? ''
);

if (isset($_GET['logout'])) {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    redirect(
        '/grand-plus-admin.php'
    );
}

/*
|--------------------------------------------------------------------------
| CONNEXION
|--------------------------------------------------------------------------
*/

$loginError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'login'
) {

    $username = trim(
        (string) (
            $_POST['username'] ?? ''
        )
    );

    $password = (string) (
        $_POST['password'] ?? ''
    );

    if (
        $adminUser !== ''
        && $adminPassword !== ''
        && hash_equals(
            $adminUser,
            $username
        )
        && hash_equals(
            $adminPassword,
            $password
        )
    ) {

        session_regenerate_id(true);

        $_SESSION[
            'grand_plus_admin_authenticated'
        ] = true;

        $_SESSION[
            'grand_plus_admin_user'
        ] = $adminUser;

        $_SESSION[
            'grand_plus_admin_csrf'
        ] = bin2hex(
            random_bytes(32)
        );

        redirect(
            '/grand-plus-admin.php'
        );
    }

    $loginError =
        'Identifiant ou mot de passe incorrect.';
}

/*
|--------------------------------------------------------------------------
| PAGE DE CONNEXION
|--------------------------------------------------------------------------
*/

if (!is_logged_in()):
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
    Administration Grand+ — Vitrine+
</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    min-height: 100%;
}

body {
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #080808;
    color: #fff;
}

.page {
    min-height: 100vh;

    display: flex;

    align-items: center;
    justify-content: center;

    padding: 24px;

    position: relative;

    overflow: hidden;
}

.glow {
    position: absolute;

    width: 500px;
    height: 500px;

    border-radius: 999px;

    background:
        rgba(200, 164, 93, .08);

    filter: blur(100px);

    pointer-events: none;
}

.card {
    width: 100%;
    max-width: 440px;

    background:
        rgba(18, 18, 18, .96);

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 24px;

    padding: 42px;

    position: relative;

    z-index: 1;

    box-shadow:
        0 30px 100px rgba(0,0,0,.45);
}

.logo {
    font-size: 28px;

    font-weight: 800;

    letter-spacing: -.04em;

    margin-bottom: 40px;
}

.logo span {
    color: #C8A45D;
}

.eyebrow {
    color: #C8A45D;

    text-transform: uppercase;

    letter-spacing: .16em;

    font-size: 11px;

    font-weight: 700;

    margin-bottom: 12px;
}

h1 {
    margin: 0 0 12px;

    font-size: 32px;

    line-height: 1.05;

    letter-spacing: -.04em;
}

.intro {
    color: #999;

    line-height: 1.6;

    margin: 0 0 30px;
}

label {
    display: block;

    margin-bottom: 8px;

    font-size: 13px;

    font-weight: 600;

    color: #ddd;
}

.field {
    margin-bottom: 20px;
}

input {
    width: 100%;

    border: 1px solid #292929;

    background: #0d0d0d;

    color: #fff;

    border-radius: 12px;

    padding: 14px 15px;

    font-size: 15px;

    outline: none;
}

input:focus {
    border-color: #C8A45D;
}

button {
    width: 100%;

    border: 0;

    border-radius: 12px;

    padding: 15px;

    background: #C8A45D;

    color: #080808;

    font-size: 14px;

    font-weight: 800;

    cursor: pointer;
}

button:hover {
    filter: brightness(1.06);
}

.error {
    background:
        rgba(220, 70, 70, .1);

    border:
        1px solid rgba(220, 70, 70, .25);

    color: #ff9c9c;

    border-radius: 12px;

    padding: 12px 14px;

    margin-bottom: 20px;

    font-size: 13px;
}

.back {
    display: block;

    text-align: center;

    margin-top: 24px;

    color: #777;

    text-decoration: none;

    font-size: 13px;
}

.back:hover {
    color: #fff;
}

@media (max-width: 600px) {

    .card {
        padding: 30px 24px;
    }

    h1 {
        font-size: 28px;
    }

}

</style>

</head>

<body>

<div class="page">

<div class="glow"></div>

<div class="card">

<div class="logo">
    Vitrine<span>+</span>
</div>

<div class="eyebrow">
    Administration
</div>

<h1>
    Le Grand+
</h1>

<p class="intro">
    Connectez-vous pour accéder
    à l'administration du Grand+.
</p>

<?php if ($loginError !== ''): ?>

<div class="error">
    <?= h($loginError) ?>
</div>

<?php endif; ?>

<form
    method="post"
    autocomplete="on"
>

<input
    type="hidden"
    name="action"
    value="login"
>

<div class="field">

<label for="username">
    Identifiant
</label>

<input
    id="username"
    name="username"
    type="text"
    autocomplete="username"
    required
    autofocus
>

</div>

<div class="field">

<label for="password">
    Mot de passe
</label>

<input
    id="password"
    name="password"
    type="password"
    autocomplete="current-password"
    required
>

</div>

<button type="submit">
    Se connecter
</button>

</form>

<a
    class="back"
    href="/"
>
    ← Retour sur Vitrine+
</a>

</div>

</div>

</body>

</html>

<?php

exit;

endif;

/*
|--------------------------------------------------------------------------
| ACTIONS ADMIN
|--------------------------------------------------------------------------
*/

$actionMessage = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {

        $actionError =
            'Session expirée. Rechargez la page.';

    } else {

        $action = (string) (
            $_POST['action'] ?? ''
        );

        $participations =
            load_participations();

        /*
        |--------------------------------------------------------------------------
        | DÉSIGNER LE GAGNANT
        |--------------------------------------------------------------------------
        */

        if ($action === 'select_winner') {

            $winnerId = trim(
                (string) (
                    $_POST['winner_id'] ?? ''
                )
            );

            if ($winnerId === '') {

                $actionError =
                    'Veuillez sélectionner un participant.';

            } else {

                $winnerParticipant = null;

                foreach (
                    $participations
                    as $participant
                ) {

                    if (
                        isset($participant['id'])
                        && (string) $participant['id']
                            === $winnerId
                    ) {

                        $winnerParticipant =
                            $participant;

                        break;
                    }
                }

                if ($winnerParticipant === null) {

                    $actionError =
                        'Participant introuvable.';

                } else {

                    $winner = [
                        'hasWinner' => true,

                        'month' => (string) (
                            $winnerParticipant['month_label']
                            ?? current_month_label()
                        ),

                        'company' => (string) (
                            $winnerParticipant['company']
                            ?? ''
                        ),

                        'description' => (string) (
                            $winnerParticipant['problem']
                            ?? ''
                        ),

                        'website' => (string) (
                            $winnerParticipant['website']
                            ?? ''
                        ),

                        'image' => '',

                        'winner_id' =>
                            $winnerId,

                        'winner_email_sent' =>
                            false,

                        'loser_emails_sent' =>
                            0,

                        'notification_completed_at' =>
                            null,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | ENREGISTREMENT DU GAGNANT
                    |--------------------------------------------------------------------------
                    */

                    if (!save_winner($winner)) {

                        $actionError =
                            "Impossible d'enregistrer le gagnant.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | EMAIL DU GAGNANT
                        |--------------------------------------------------------------------------
                        */

                        $winnerEmailSent =
                            send_winner_email(
                                $config,
                                $winnerParticipant
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | EMAILS DES AUTRES PARTICIPANTS
                        |--------------------------------------------------------------------------
                        */

                        $loserEmailsSent = 0;
                        $loserEmailsFailed = 0;

                        foreach (
                            $participations
                            as $index => $participant
                        ) {

                            $participantId =
                                (string) (
                                    $participant['id']
                                    ?? ''
                                );

                            /*
                             * Ne pas envoyer d'e-mail
                             * au gagnant.
                             */

                            if (
                                $participantId === ''
                                || $participantId === $winnerId
                            ) {
                                continue;
                            }

                            /*
                             * Uniquement les participants
                             * du même mois.
                             */

                            if (
                                (string) (
                                    $participant['month_key']
                                    ?? ''
                                )
                                !==
                                (string) (
                                    $winnerParticipant['month_key']
                                    ?? ''
                                )
                            ) {
                                continue;
                            }

                            /*
                             * Évite les doublons.
                             */

                            if (
                                !empty(
                                    $participant[
                                        'result_email_sent'
                                    ]
                                )
                            ) {
                                continue;
                            }

                            $emailSent =
                                send_non_winner_email(
                                    $config,
                                    $participant
                                );

                            if ($emailSent) {

                                $participations[$index][
                                    'result_email_sent'
                                ] = true;

                                $participations[$index][
                                    'result_email_sent_at'
                                ] = date('c');

                                $loserEmailsSent++;

                            } else {

                                $loserEmailsFailed++;
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | SAUVEGARDE DES PARTICIPATIONS
                        |--------------------------------------------------------------------------
                        */

                        save_participations(
                            $participations
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | STATUT DES NOTIFICATIONS
                        |--------------------------------------------------------------------------
                        */

                        $winner[
                            'winner_email_sent'
                        ] = $winnerEmailSent;

                        $winner[
                            'loser_emails_sent'
                        ] = $loserEmailsSent;

                        $winner[
                            'notification_completed_at'
                        ] = date('c');

                        save_winner(
                            $winner
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | MESSAGE ADMIN
                        |--------------------------------------------------------------------------
                        */

                        $totalParticipants =
                            count($participations);

                        $totalLosers =
                            max(
                                0,
                                $totalParticipants - 1
                            );

                        if (!$winnerEmailSent) {

                            $actionError =
                                'Le gagnant a été enregistré, '
                                . 'mais son e-mail n’a pas pu être envoyé. '
                                . 'Les autres notifications ont été traitées : '
                                . $loserEmailsSent
                                . ' / '
                                . $totalLosers
                                . '.';

                        } elseif (
                            $loserEmailsFailed > 0
                        ) {

                            $actionError =
                                'Le gagnant a été enregistré '
                                . 'et son e-mail a été envoyé. '
                                . $loserEmailsSent
                                . ' e-mail(s) participant(s) envoyé(s), '
                                . $loserEmailsFailed
                                . ' échec(s).';

                        } else {

                            $actionMessage =
                                'Gagnant enregistré. '
                                . 'E-mail du gagnant envoyé. '
                                . $loserEmailsSent
                                . ' e-mail(s) aux autres participants envoyé(s).';
                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | RÉINITIALISER LE GAGNANT
        |--------------------------------------------------------------------------
        */

        if ($action === 'reset_winner') {

            $winner = [
                'hasWinner' => false,
                'month' => '',
                'company' => '',
                'description' => '',
                'website' => '',
                'image' => '',
                'winner_id' => '',
                'winner_email_sent' => false,
                'loser_emails_sent' => 0,
                'notification_completed_at' => null,
            ];

            if (save_winner($winner)) {

                $actionMessage =
                    'Le gagnant a été réinitialisé.';

            } else {

                $actionError =
                    "Impossible de réinitialiser le gagnant.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DONNÉES DU TABLEAU DE BORD
|--------------------------------------------------------------------------
*/

$month = selected_month();

$allParticipations =
    load_participations();

$monthParticipations = array_values(
    array_filter(
        $allParticipations,
        static function (
            array $participant
        ) use ($month): bool {

            return (string) (
                $participant['month_key']
                ?? ''
            ) === $month;
        }
    )
);

usort(
    $monthParticipations,
    static function (
        array $a,
        array $b
    ): int {

        return strcmp(
            (string) (
                $b['created_at'] ?? ''
            ),
            (string) (
                $a['created_at'] ?? ''
            )
        );
    }
);

$winner = load_winner();

$winnerCompany =
    (string) (
        $winner['company'] ?? ''
    );

$marketingCount = count(
    array_filter(
        $monthParticipations,
        static fn(
            array $participant
        ): bool =>
            !empty(
                $participant['marketing_consent']
            )
    )
);

$todayCount = count(
    array_filter(
        $monthParticipations,
        static function (
            array $participant
        ): bool {

            $createdAt =
                (string) (
                    $participant['created_at']
                    ?? ''
                );

            return $createdAt !== ''
                && date(
                    'Y-m-d',
                    strtotime($createdAt)
                ) === date('Y-m-d');
        }
    )
);

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
    Grand+ — Administration | Vitrine+
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

    color: #fff;

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

a {
    color: inherit;
}

.shell {
    min-height: 100vh;
}

.topbar {
    height: 72px;

    border-bottom:
        1px solid #202020;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 32px;

    background:
        rgba(8,8,8,.96);

    position: sticky;

    top: 0;

    z-index: 20;

    backdrop-filter: blur(20px);
}

.brand {
    font-weight: 800;

    font-size: 20px;

    letter-spacing: -.04em;
}

.brand span {
    color: #C8A45D;
}

.top-right {
    display: flex;

    align-items: center;

    gap: 20px;
}

.site-link {
    color: #888;

    text-decoration: none;

    font-size: 13px;
}

.site-link:hover {
    color: #fff;
}

.logout {
    border:
        1px solid #292929;

    background: #111;

    padding: 9px 14px;

    border-radius: 9px;

    text-decoration: none;

    font-size: 13px;
}

.logout:hover {
    background: #1a1a1a;
}

.container {
    width:
        min(
            1400px,
            calc(100% - 48px)
        );

    margin: 0 auto;

    padding: 48px 0 80px;
}

.hero {
    margin-bottom: 36px;
}

.eyebrow {
    color: #C8A45D;

    text-transform: uppercase;

    letter-spacing: .16em;

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 10px;
}

h1 {
    font-size:
        clamp(
            34px,
            5vw,
            58px
        );

    line-height: .98;

    letter-spacing: -.055em;

    margin: 0 0 14px;
}

.subtitle {
    color: #888;

    margin: 0;

    font-size: 15px;
}

.toolbar {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 28px;

    flex-wrap: wrap;
}

.month-form {
    display: flex;

    align-items: center;

    gap: 10px;
}

.month-form label {
    color: #777;

    font-size: 13px;
}

select {
    border:
        1px solid #292929;

    background: #111;

    color: #fff;

    padding: 11px 13px;

    border-radius: 10px;

    outline: none;
}

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 30px;
}

.stat {
    border:
        1px solid #202020;

    background: #101010;

    border-radius: 16px;

    padding: 22px;
}

.stat-label {
    color: #777;

    font-size: 12px;

    margin-bottom: 12px;
}

.stat-value {
    font-size: 32px;

    font-weight: 800;

    letter-spacing: -.04em;
}

.layout {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        340px;

    gap: 20px;

    align-items: start;
}

.panel {
    border:
        1px solid #202020;

    background: #101010;

    border-radius: 18px;

    overflow: hidden;
}

.panel-header {
    padding: 20px 22px;

    border-bottom:
        1px solid #202020;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;
}

.panel-title {
    font-weight: 750;

    font-size: 15px;
}

.panel-meta {
    color: #666;

    font-size: 12px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

th {
    text-align: left;

    color: #666;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .08em;

    font-weight: 700;

    padding: 14px 18px;

    border-bottom:
        1px solid #202020;
}

td {
    padding: 16px 18px;

    border-bottom:
        1px solid #1b1b1b;

    vertical-align: top;

    font-size: 13px;
}

tr:last-child td {
    border-bottom: 0;
}

.name {
    font-weight: 700;

    margin-bottom: 4px;
}

.muted {
    color: #777;
}

.email {
    color: #aaa;
}

.badge {
    display: inline-flex;

    align-items: center;

    border-radius: 999px;

    padding: 5px 8px;

    font-size: 10px;

    font-weight: 700;

    background: #181818;

    color: #aaa;
}

.badge.gold {
    color: #C8A45D;

    background:
        rgba(200,164,93,.09);
}

.winner-badge {
    display: inline-block;

    margin-top: 6px;

    color: #C8A45D;

    font-size: 11px;

    font-weight: 800;
}

.winner-panel {
    padding: 24px;
}

.winner-empty {
    color: #777;

    line-height: 1.6;

    font-size: 13px;

    margin-bottom: 20px;
}

.winner-company {
    font-size: 24px;

    line-height: 1.1;

    letter-spacing: -.04em;

    font-weight: 800;

    margin-bottom: 10px;
}

.winner-month {
    color: #C8A45D;

    font-size: 12px;

    font-weight: 700;

    margin-bottom: 18px;
}

.winner-description {
    color: #999;

    font-size: 13px;

    line-height: 1.6;

    margin-bottom: 18px;
}

.winner-link {
    color: #fff;

    font-size: 13px;

    text-decoration: none;
}

.winner-link:hover {
    text-decoration: underline;
}

.button {
    width: 100%;

    border: 0;

    background: #C8A45D;

    color: #080808;

    border-radius: 10px;

    padding: 12px 14px;

    font-size: 13px;

    font-weight: 800;

    cursor: pointer;
}

.button:hover {
    filter: brightness(1.06);
}

.button.secondary {
    background: #181818;

    color: #fff;

    border:
        1px solid #292929;
}

.button.danger {
    background: #191010;

    color: #ff9c9c;

    border:
        1px solid #351d1d;
}

.winner-actions {
    display: flex;

    flex-direction: column;

    gap: 10px;

    margin-top: 20px;
}

.alert {
    border-radius: 12px;

    padding: 13px 15px;

    margin-bottom: 22px;

    font-size: 13px;
}

.alert.success {
    background:
        rgba(70,180,110,.08);

    border:
        1px solid rgba(70,180,110,.2);

    color: #9be1b3;
}

.alert.error {
    background:
        rgba(220,70,70,.08);

    border:
        1px solid rgba(220,70,70,.2);

    color: #ff9c9c;
}

.empty {
    padding: 50px 20px;

    text-align: center;

    color: #666;

    font-size: 13px;
}

.select-winner {
    width: 100%;

    margin-top: 8px;
}

@media (max-width: 1000px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .layout {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .topbar {
        padding: 0 18px;
    }

    .site-link {
        display: none;
    }

    .container {
        width:
            min(
                100% - 28px,
                1400px
            );

        padding-top: 32px;
    }

    .stats {
        grid-template-columns:
            1fr 1fr;
    }

    .stat {
        padding: 17px;
    }

    .stat-value {
        font-size: 26px;
    }

    .month-form {
        width: 100%;
    }

    select {
        flex: 1;
    }

}

</style>

</head>

<body>

<div class="shell">

<header class="topbar">

<div class="brand">
    Vitrine<span>+</span>
</div>

<div class="top-right">

<a
    class="site-link"
    href="/le-grand-plus"
>
    Voir le Grand+
</a>

<a
    class="logout"
    href="/grand-plus-admin.php?logout=1"
>
    Déconnexion
</a>

</div>

</header>

<main class="container">

<section class="hero">

<div class="eyebrow">
    Administration
</div>

<h1>
    Le Grand+
</h1>

<p class="subtitle">
    Gérez les participations et le gagnant du mois.
</p>

</section>

<?php if ($actionMessage !== ''): ?>

<div class="alert success">
    <?= h($actionMessage) ?>
</div>

<?php endif; ?>

<?php if ($actionError !== ''): ?>

<div class="alert error">
    <?= h($actionError) ?>
</div>

<?php endif; ?>

<div class="toolbar">

<form
    method="get"
    class="month-form"
>

<label for="month">
    Mois
</label>

<select
    id="month"
    name="month"
    onchange="this.form.submit()"
>

<?php

$availableMonths = [];

foreach (
    $allParticipations
    as $participant
) {

    $key = (string) (
        $participant['month_key']
        ?? ''
    );

    if (
        preg_match(
            '/^\d{4}-\d{2}$/',
            $key
        )
        && !in_array(
            $key,
            $availableMonths,
            true
        )
    ) {

        $availableMonths[] = $key;
    }
}

$currentKey =
    current_month_key();

if (
    !in_array(
        $currentKey,
        $availableMonths,
        true
    )
) {

    $availableMonths[] =
        $currentKey;
}

rsort($availableMonths);

?>

<?php foreach (
    $availableMonths
    as $availableMonth
): ?>

<option
    value="<?= h($availableMonth) ?>"
    <?= $availableMonth === $month
        ? 'selected'
        : '' ?>
>
    <?= h(
        month_label_from_key(
            $availableMonth
        )
    ) ?>
</option>

<?php endforeach; ?>

</select>

</form>

</div>

<section class="stats">

<div class="stat">

<div class="stat-label">
    Participants
</div>

<div class="stat-value">
    <?= count(
        $monthParticipations
    ) ?>
</div>

</div>

<div class="stat">

<div class="stat-label">
    Aujourd'hui
</div>

<div class="stat-value">
    <?= $todayCount ?>
</div>

</div>

<div class="stat">

<div class="stat-label">
    Marketing accepté
</div>

<div class="stat-value">
    <?= $marketingCount ?>
</div>

</div>

<div class="stat">

<div class="stat-label">
    Gagnant
</div>

<div class="stat-value">
    <?= $winnerCompany !== ''
        ? '✓'
        : '—' ?>
</div>

</div>

</section>

<div class="layout">

<section class="panel">

<div class="panel-header">

<div class="panel-title">
    Participants
</div>

<div class="panel-meta">

<?= count(
    $monthParticipations
) ?>

participation(s)

</div>

</div>

<?php if (
    count($monthParticipations) === 0
): ?>

<div class="empty">

Aucun participant pour
<?= h(
    month_label_from_key($month)
) ?>.

</div>

<?php else: ?>

<div class="table-wrap">

<table>

<thead>

<tr>

<th>
    Participant
</th>

<th>
    Entreprise
</th>

<th>
    Secteur
</th>

<th>
    Contact
</th>

<th>
    Participation
</th>

<th>
    Marketing
</th>

</tr>

</thead>

<tbody>

<?php foreach (
    $monthParticipations
    as $participant
): ?>

<tr>

<td>

<div class="name">

<?= h(
    $participant['name']
    ?? ''
) ?>

</div>

<?php if (
    !empty(
        $participant['id']
    )
): ?>

<div class="muted">

<?= h(
    $participant['id']
) ?>

</div>

<?php endif; ?>

<?php

$isWinner =
    $winnerCompany !== ''
    && $winnerCompany ===
        (string) (
            $participant['company']
            ?? ''
        );

?>

<?php if ($isWinner): ?>

<div class="winner-badge">
    ★ GAGNANT
</div>

<?php endif; ?>

</td>

<td>

<div class="name">

<?= h(
    $participant['company']
    ?? ''
) ?>

</div>

<?php if (
    !empty(
        $participant['website']
    )
): ?>

<a
    class="email"
    href="<?= h(
        $participant['website']
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
>
    Site web
</a>

<?php endif; ?>

</td>

<td>

<?= h(
    $participant['sector']
    ?? ''
) ?>

</td>

<td>

<div class="email">

<?= h(
    $participant['email']
    ?? ''
) ?>

</div>

<?php if (
    !empty(
        $participant['phone']
    )
): ?>

<div class="muted">

<?= h(
    $participant['phone']
) ?>

</div>

<?php endif; ?>

</td>

<td>

<div>

<?= h(
    format_date(
        (string) (
            $participant['created_at']
            ?? ''
        )
    )
) ?>

</div>

<?php if (
    !empty(
        $participant['problem']
    )
): ?>

<div
    class="muted"
    style="margin-top:7px;"
>

<?= h(
    $participant['problem']
) ?>

</div>

<?php endif; ?>

</td>

<td>

<?php if (
    !empty(
        $participant['marketing_consent']
    )
): ?>

<span class="badge gold">
    Oui
</span>

<?php else: ?>

<span class="badge">
    Non
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<aside class="panel">

<div class="panel-header">

<div class="panel-title">
    Gagnant du mois
</div>

</div>

<div class="winner-panel">

<?php if (
    !empty(
        $winner['hasWinner']
    )
): ?>

<div class="winner-month">

<?= h(
    $winner['month']
    ?? ''
) ?>

</div>

<div class="winner-company">

<?= h(
    $winner['company']
    ?? ''
) ?>

</div>

<?php if (
    !empty(
        $winner['description']
    )
): ?>

<div class="winner-description">

<?= h(
    $winner['description']
) ?>

</div>

<?php endif; ?>

<?php if (
    !empty(
        $winner['website']
    )
): ?>

<a
    class="winner-link"
    href="<?= h(
        $winner['website']
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
>
    Voir le site →
</a>

<?php endif; ?>

<?php if (
    !empty(
        $winner['winner_email_sent']
    )
): ?>

<div
    class="badge gold"
    style="margin-top:15px;"
>
    ✓ E-mail gagnant envoyé
</div>

<?php endif; ?>

<?php if (
    isset(
        $winner['loser_emails_sent']
    )
    && (int) (
        $winner['loser_emails_sent']
    ) > 0
): ?>

<div
    class="muted"
    style="
        margin-top:10px;
        font-size:12px;
        line-height:1.5;
    "
>

<?= (int) (
    $winner['loser_emails_sent']
) ?>

participant(s)
notifié(s)

</div>

<?php endif; ?>

<div class="winner-actions">

<form method="post">

<input
    type="hidden"
    name="csrf"
    value="<?= h(
        csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="action"
    value="reset_winner"
>

<input
    type="hidden"
    name="month"
    value="<?= h($month) ?>"
>

<button
    class="button danger"
    type="submit"
    onclick="
        return confirm(
            'Réinitialiser le gagnant du mois ?'
        )
    "
>
    Réinitialiser le gagnant
</button>

</form>

</div>

<?php else: ?>

<div class="winner-empty">

Aucun gagnant n'est actuellement
enregistré pour le Grand+.

</div>

<?php endif; ?>

<?php if (
    count($monthParticipations) > 0
): ?>

<form
    method="post"
    style="margin-top:24px;"
>

<input
    type="hidden"
    name="csrf"
    value="<?= h(
        csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="action"
    value="select_winner"
>

<input
    type="hidden"
    name="month"
    value="<?= h($month) ?>"
>

<select
    class="select-winner"
    name="winner_id"
    required
>

<option value="">
    Choisir un gagnant…
</option>

<?php foreach (
    $monthParticipations
    as $participant
): ?>

<option
    value="<?= h(
        $participant['id']
        ?? ''
    ) ?>"
>

<?= h(
    $participant['company']
    ?? ''
) ?>

—

<?= h(
    $participant['name']
    ?? ''
) ?>

</option>

<?php endforeach; ?>

</select>

<div style="height:10px;"></div>

<button
    class="button"
    type="submit"
    onclick="
        return confirm(
            'Définir cette entreprise comme gagnante ? Les e-mails seront envoyés automatiquement.'
        )
    "
>
    Désigner le gagnant
</button>

</form>

<?php endif; ?>

</div>

</aside>

</div>

</main>

</div>

</body>

</html>