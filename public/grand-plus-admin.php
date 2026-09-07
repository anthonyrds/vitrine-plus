<?php
declare(strict_types=1);

session_start();

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

function config(): array
{
    $config = file_exists(CONFIG_FILE) ? require CONFIG_FILE : [];

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
        $_SESSION['grand_plus_admin_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['grand_plus_admin_csrf'];
}

function verify_csrf(): bool
{
    return isset(
        $_POST['csrf'],
        $_SESSION['grand_plus_admin_csrf']
    ) && hash_equals(
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
    if (
        !is_dir(DATA_DIR)
        && !mkdir(DATA_DIR, 0755, true)
        && !is_dir(DATA_DIR)
    ) {
        return false;
    }

    $json = json_encode(
        $participations,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
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
| MOIS
|--------------------------------------------------------------------------
*/

function current_month_key(): string
{
    return date('Y-m');
}

function month_label(string $key): string
{
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

    $parts = explode('-', $key);

    if (count($parts) !== 2) {
        return $key;
    }

    return ($months[$parts[1]] ?? $parts[1])
        . ' '
        . $parts[0];
}

function selected_month(): string
{
    $month = (string) (
        $_GET['month']
        ?? $_POST['month']
        ?? current_month_key()
    );

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return current_month_key();
    }

    return $month;
}


/*
|--------------------------------------------------------------------------
| GAGNANT
|--------------------------------------------------------------------------
*/

function load_winner(): array
{
    if (!file_exists(WINNER_FILE)) {
        return [
            'hasWinner' => false,
            'month' => '',
            'month_key' => '',
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
        ];
    }

    $json = file_get_contents(WINNER_FILE);

    if ($json === false || trim($json) === '') {
        return [
            'hasWinner' => false,
            'month' => '',
            'month_key' => '',
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
        ];
    }

    $winner = json_decode($json, true);

    return is_array($winner)
        ? $winner
        : [
            'hasWinner' => false,
            'month' => '',
            'month_key' => '',
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
        ];
}

function save_winner(array $winner): bool
{
    $json = json_encode(
        $winner,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
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

function smtp_command(
    $socket,
    string $command,
    array $codes
): bool {
    fwrite(
        $socket,
        $command . "\r\n"
    );

    $response = smtp_read_response($socket);

    if ($response === '') {
        return false;
    }

    $code = (int) substr(
        $response,
        0,
        3
    );

    return in_array(
        $code,
        $codes,
        true
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
        (string) (
            $config['smtp_username'] ?? ''
        )
    );

    $password = (string) (
        $config['smtp_password'] ?? ''
    );

    $fromEmail = trim(
        (string) (
            $config['from_email'] ?? ''
        )
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

        $response = smtp_read_response(
            $socket
        );

        if (
            !in_array(
                (int) substr($response, 0, 3),
                [220],
                true
            )
        ) {
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
| EMAILS GRAND+
|--------------------------------------------------------------------------
*/

function email_layout(
    string $title,
    string $content
): string {

    return '<!doctype html>
<html lang="fr">
<head>
<meta charset="UTF-8">
</head>

<body style="
margin:0;
padding:40px 20px;
background:#080808;
color:#ffffff;
font-family:Arial,sans-serif;
">

<div style="
max-width:620px;
margin:0 auto;
background:#111111;
border:1px solid #292929;
border-radius:18px;
padding:36px;
">

<div style="
font-size:25px;
font-weight:800;
">

Vitrine<span style="color:#C8A45D">+</span>

</div>

<h1 style="
font-size:28px;
line-height:1.2;
margin:30px 0 18px;
">

' . h($title) . '

</h1>

<div style="
color:#bbbbbb;
font-size:15px;
line-height:1.7;
">

' . $content . '

</div>

</div>

</body>
</html>';
}

function send_winner_email(
    array $config,
    array $participant
): bool {

    $email = (string) (
        $participant['email'] ?? ''
    );

    $company = h(
        $participant['company']
        ?? 'votre entreprise'
    );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $content = '
<p>
Félicitations ' . $company . ' !
</p>

<p>
Votre entreprise a été tirée au sort
et remporte <strong style="color:#C8A45D">
Le Grand+
</strong>.
</p>

<p>
Vitrine+ va réaliser gratuitement
la refonte de votre site internet.
</p>

<p>
Nous allons revenir vers vous très
prochainement afin d’organiser le projet
et définir ensemble les prochaines étapes.
</p>

<p>
À très bientôt,<br>
<strong>L’équipe Vitrine+</strong>
</p>
';

    return smtp_send_html_email(
        $config,
        $email,
        'Félicitations — vous avez remporté le Grand+ !',
        email_layout(
            'Vous avez remporté le Grand+',
            $content
        ),
        $config['from_email'] ?? null
    );
}

function send_participant_email(
    array $config,
    array $participant
): bool {

    $email = (string) (
        $participant['email'] ?? ''
    );

    $company = h(
        $participant['company']
        ?? 'votre entreprise'
    );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $bonus = '';

    if (
        !empty(
            $participant['marketing_consent']
        )
    ) {

        $bonus = '
<div style="
margin:25px 0;
padding:20px;
border:1px solid #3a3120;
border-radius:12px;
background:#15120d;
">

<div style="
color:#C8A45D;
font-size:17px;
font-weight:800;
margin-bottom:8px;
">

Votre bonus

</div>

<p style="margin:0;color:#cccccc">

Vous bénéficiez d’un
<strong style="color:#ffffff">
audit stratégique offert
</strong>
ainsi que de
<strong style="color:#ffffff">
' . OFFER_AMOUNT . ' € de réduction
</strong>
sur votre futur site internet Vitrine+.

</p>

<p style="
margin:12px 0 0;
font-size:12px;
color:#777777;
">

Offre valable ' . OFFER_VALIDITY_DAYS . ' jours.

</p>

</div>';
    }

    $content = '
<p>
Merci à ' . $company . '
pour sa participation au Grand+.
</p>

<p>
Cette fois, votre entreprise n’a malheureusement
pas été tirée au sort.
</p>

' . $bonus . '

<p>
Nous vous remercions néanmoins pour votre
confiance et votre intérêt pour Vitrine+.
</p>

<p>
À bientôt,<br>
<strong>L’équipe Vitrine+</strong>
</p>
';

    return smtp_send_html_email(
        $config,
        $email,
        'Merci pour votre participation au Grand+ — Vitrine+',
        email_layout(
            'Merci pour votre participation',
            $content
        ),
        $config['from_email'] ?? null
    );
}


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

$config = config();

$adminUser = trim(
    (string) (
        $config['grand_plus_admin_user']
        ?? ''
    )
);

$adminPassword = (string) (
    $config['grand_plus_admin_password']
    ?? ''
);

if (isset($_GET['logout'])) {

    $_SESSION = [];

    if (
        ini_get('session.use_cookies')
    ) {

        $params =
            session_get_cookie_params();

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

$loginError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        $_POST['action'] ?? ''
    ) === 'login'
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
| PAGE LOGIN
|--------------------------------------------------------------------------
*/

if (!is_logged_in()):

?>

<!doctype html>

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

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #080808;
    color: #ffffff;
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

.card {
    width: 100%;
    max-width: 440px;
    padding: 40px;
    background: #111111;
    border: 1px solid #292929;
    border-radius: 20px;
}

.logo {
    font-size: 28px;
    font-weight: 800;
}

.gold {
    color: #C8A45D;
}

.eyebrow {
    margin-top: 35px;
    color: #C8A45D;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .16em;
    text-transform: uppercase;
}

h1 {
    margin: 12px 0;
    font-size: 32px;
}

.intro {
    color: #888888;
    line-height: 1.6;
}

label {
    display: block;
    margin: 18px 0 7px;
    color: #bbbbbb;
    font-size: 13px;
}

input {
    width: 100%;
    padding: 14px;
    background: #080808;
    color: #ffffff;
    border: 1px solid #333333;
    border-radius: 10px;
    outline: none;
}

input:focus {
    border-color: #C8A45D;
}

button {
    width: 100%;
    margin-top: 22px;
    padding: 14px;
    border: 0;
    border-radius: 10px;
    background: #C8A45D;
    color: #080808;
    font-weight: 800;
    cursor: pointer;
}

.error {
    margin: 20px 0;
    padding: 13px;
    border: 1px solid #542626;
    border-radius: 10px;
    background: #211010;
    color: #ffaaa8;
}

</style>

</head>

<body>

<div class="card">

<div class="logo">
Vitrine<span class="gold">+</span>
</div>

<div class="eyebrow">
Administration
</div>

<h1>
Le Grand+
</h1>

<p class="intro">
Connectez-vous pour accéder à
l'administration du Grand+.
</p>

<?php if ($loginError !== ''): ?>

<div class="error">
<?= h($loginError) ?>
</div>

<?php endif; ?>

<form method="post">

<input
type="hidden"
name="action"
value="login"
>

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

<button type="submit">
Se connecter
</button>

</form>

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

$month = selected_month();

$allParticipations =
    load_participations();


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        $_POST['action'] ?? ''
    ) !== 'login'
) {

    if (!verify_csrf()) {

        $actionError =
            'Session expirée. Rechargez la page.';

    } else {

        $action =
            (string) (
                $_POST['action'] ?? ''
            );

        if (
            $action === 'select_winner'
        ) {

            $winnerId = trim(
                (string) (
                    $_POST['winner_id']
                    ?? ''
                )
            );

            if ($winnerId === '') {

                $actionError =
                    'Veuillez sélectionner un participant.';

            } else {

                $winnerParticipant = null;

                foreach (
                    $allParticipations
                    as $participant
                ) {

                    if (
                        is_array($participant)
                        && isset(
                            $participant['id']
                        )
                        && (
                            (string)
                            $participant['id']
                        ) === $winnerId
                    ) {

                        $winnerParticipant =
                            $participant;

                        break;
                    }
                }

                if (
                    $winnerParticipant === null
                ) {

                    $actionError =
                        'Participant introuvable.';

                } else {

                    $winnerMonthKey =
                        (string) (
                            $winnerParticipant[
                                'month_key'
                            ]
                            ?? $month
                        );

                    $winner = [

                        'hasWinner' => true,

                        'month' =>
                            (string) (
                                $winnerParticipant[
                                    'month_label'
                                ]
                                ?? month_label(
                                    $winnerMonthKey
                                )
                            ),

                        'month_key' =>
                            $winnerMonthKey,

                        'company' =>
                            (string) (
                                $winnerParticipant[
                                    'company'
                                ] ?? ''
                            ),

                        'description' =>
                            (string) (
                                $winnerParticipant[
                                    'problem'
                                ] ?? ''
                            ),

                        'website' =>
                            (string) (
                                $winnerParticipant[
                                    'website'
                                ] ?? ''
                            ),

                        'image' => '',

                        'winner_id' =>
                            $winnerId,

                        'winner_email_sent' =>
                            false,

                        'winner_email_sent_at' =>
                            '',

                        'loser_emails_sent' =>
                            0,

                        'notification_completed_at' =>
                            '',
                    ];

                    if (
                        !save_winner($winner)
                    ) {

                        $actionError =
                            "Impossible d'enregistrer le gagnant.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | MAIL DU GAGNANT
                        |--------------------------------------------------------------------------
                        */

                        $winnerEmailSent =
                            send_winner_email(
                                $config,
                                $winnerParticipant
                            );

                        $loserEmailsSent = 0;


                        /*
                        |--------------------------------------------------------------------------
                        | MAILS DES AUTRES PARTICIPANTS
                        |--------------------------------------------------------------------------
                        */

                        foreach (
                            $allParticipations
                            as $index => $participant
                        ) {

                            if (
                                !is_array(
                                    $participant
                                )
                            ) {
                                continue;
                            }

                            if (
                                (string) (
                                    $participant[
                                        'month_key'
                                    ] ?? ''
                                )
                                !==
                                $winnerMonthKey
                            ) {
                                continue;
                            }

                            /*
                            | GAGNANT
                            */

                            if (
                                (string) (
                                    $participant['id']
                                    ?? ''
                                )
                                === $winnerId
                            ) {

                                $allParticipations[
                                    $index
                                ]['status'] =
                                    'winner';

                                continue;
                            }


                            /*
                            | AUTRES PARTICIPANTS
                            */

                            $allParticipations[
                                $index
                            ]['status'] =
                                'not_winner';


                            /*
                            | Évite d'envoyer deux fois
                            | le même mail.
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


                            if (
                                send_participant_email(
                                    $config,
                                    $participant
                                )
                            ) {

                                $allParticipations[
                                    $index
                                ]['result_email_sent']
                                    = true;

                                $allParticipations[
                                    $index
                                ]['result_email_sent_at']
                                    = date('c');

                                $loserEmailsSent++;
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SAUVEGARDE
                        |--------------------------------------------------------------------------
                        */

                        save_participations(
                            $allParticipations
                        );

                        $winner[
                            'winner_email_sent'
                        ] =
                            $winnerEmailSent;

                        $winner[
                            'winner_email_sent_at'
                        ] =
                            $winnerEmailSent
                            ? date('c')
                            : '';

                        $winner[
                            'loser_emails_sent'
                        ] =
                            $loserEmailsSent;

                        $winner[
                            'notification_completed_at'
                        ] =
                            date('c');

                        save_winner(
                            $winner
                        );


                        if (
                            $winnerEmailSent
                        ) {

                            $actionMessage =
                                'Le gagnant a été enregistré. '
                                . 'Le mail du gagnant a été envoyé et '
                                . $loserEmailsSent
                                . ' mail(s) participant(s) ont été envoyé(s).';

                        } else {

                            $actionMessage =
                                'Le gagnant a été enregistré, '
                                . 'mais le mail du gagnant n’a pas pu être envoyé. '
                                . $loserEmailsSent
                                . ' mail(s) participant(s) ont été envoyé(s).';
                        }
                    }
                }
            }

        } elseif (
            $action === 'reset_winner'
        ) {

            $winner = [

                'hasWinner' => false,

                'month' => '',

                'month_key' => '',

                'company' => '',

                'description' => '',

                'website' => '',

                'image' => '',
            ];

            if (
                save_winner($winner)
            ) {

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
| DONNÉES
|--------------------------------------------------------------------------
*/

$monthParticipations =
    array_values(
        array_filter(
            $allParticipations,
            static function (
                array $participant
            ) use ($month): bool {

                return (
                    string
                ) (
                    $participant['month_key']
                    ?? ''
                )
                === $month;
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
                $b['created_at']
                ?? ''
            ),
            (string) (
                $a['created_at']
                ?? ''
            )
        );
    }
);

$winner =
    load_winner();

$winnerCompany =
    (string) (
        $winner['company']
        ?? ''
    );

$marketingCount =
    count(
        array_filter(
            $monthParticipations,
            static function (
                array $participant
            ): bool {

                return !empty(
                    $participant[
                        'marketing_consent'
                    ]
                );
            }
        )
    );

$todayCount =
    count(
        array_filter(
            $monthParticipations,
            static function (
                array $participant
            ): bool {

                $createdAt =
                    (string) (
                        $participant[
                            'created_at'
                        ] ?? ''
                    );

                if ($createdAt === '') {
                    return false;
                }

                $timestamp =
                    strtotime($createdAt);

                if ($timestamp === false) {
                    return false;
                }

                return date(
                    'Y-m-d',
                    $timestamp
                ) === date('Y-m-d');
            }
        )
    );


/*
|--------------------------------------------------------------------------
| MOIS DISPONIBLES
|--------------------------------------------------------------------------
*/

$availableMonths = [];

foreach (
    $allParticipations
    as $participant
) {

    if (!is_array($participant)) {
        continue;
    }

    $key =
        (string) (
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

$currentMonth =
    current_month_key();

if (
    !in_array(
        $currentMonth,
        $availableMonths,
        true
    )
) {

    $availableMonths[] =
        $currentMonth;
}

rsort(
    $availableMonths
);

?>

<!doctype html>

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
    color: #ffffff;
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

.topbar {
    height: 72px;
    padding: 0 32px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #202020;

    background: rgba(
        8,
        8,
        8,
        .96
    );

    position: sticky;
    top: 0;
    z-index: 20;
}

.brand {
    font-size: 20px;
    font-weight: 800;
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

.site-link,
.logout {
    color: #888888;
    text-decoration: none;
    font-size: 13px;
}

.logout {
    padding: 9px 14px;
    border: 1px solid #292929;
    border-radius: 9px;
    background: #111111;
}

.site-link:hover,
.logout:hover {
    color: #ffffff;
}

.container {
    width: min(
        1400px,
        calc(100% - 48px)
    );

    margin: 0 auto;

    padding: 48px 0 80px;
}

.eyebrow {
    margin-bottom: 10px;

    color: #C8A45D;

    font-size: 11px;
    font-weight: 800;

    letter-spacing: .16em;
    text-transform: uppercase;
}

h1 {
    margin: 0 0 14px;

    font-size: clamp(
        34px,
        5vw,
        58px
    );

    line-height: .98;

    letter-spacing: -.055em;
}

.subtitle {
    margin: 0;

    color: #888888;

    font-size: 15px;
}

.alert {
    margin: 22px 0;

    padding: 14px 16px;

    border-radius: 12px;

    font-size: 13px;
}

.alert.success {
    background: rgba(
        70,
        180,
        110,
        .08
    );

    border: 1px solid rgba(
        70,
        180,
        110,
        .2
    );

    color: #9be1b3;
}

.alert.error {
    background: rgba(
        220,
        70,
        70,
        .08
    );

    border: 1px solid rgba(
        220,
        70,
        70,
        .2
    );

    color: #ff9c9c;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin: 30px 0;

    gap: 20px;
}

.month-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.month-form label {
    color: #777777;
    font-size: 13px;
}

select {
    min-width: 180px;

    padding: 11px 13px;

    border: 1px solid #292929;
    border-radius: 10px;

    background: #111111;
    color: #ffffff;

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
    padding: 22px;

    border: 1px solid #202020;
    border-radius: 16px;

    background: #101010;
}

.stat-label {
    margin-bottom: 12px;

    color: #777777;

    font-size: 12px;
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
    overflow: hidden;

    border: 1px solid #202020;
    border-radius: 18px;

    background: #101010;
}

.panel-header {
    padding: 20px 22px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #202020;
}

.panel-title {
    font-size: 15px;
    font-weight: 750;
}

.panel-meta {
    color: #666666;
    font-size: 12px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;

    min-width: 900px;

    border-collapse: collapse;
}

th {
    padding: 14px 18px;

    text-align: left;

    border-bottom: 1px solid #202020;

    color: #666666;

    font-size: 11px;
    font-weight: 700;

    letter-spacing: .08em;
    text-transform: uppercase;
}

td {
    padding: 16px 18px;

    border-bottom: 1px solid #1b1b1b;

    vertical-align: top;

    font-size: 13px;
}

tr:last-child td {
    border-bottom: 0;
}

.name {
    margin-bottom: 4px;

    font-weight: 700;
}

.muted {
    margin-top: 4px;
    color: #777777;
}

.email {
    color: #aaaaaa;
}

.badge {
    display: inline-flex;

    padding: 5px 8px;

    border-radius: 999px;

    background: #181818;

    color: #aaaaaa;

    font-size: 10px;
    font-weight: 700;
}

.badge.gold {
    background: rgba(
        200,
        164,
        93,
        .09
    );

    color: #C8A45D;
}

.winner-badge {
    margin-top: 6px;

    color: #C8A45D;

    font-size: 11px;
    font-weight: 800;
}

.winner-panel {
    padding: 24px;
}

.winner-empty {
    margin-bottom: 20px;

    color: #777777;

    line-height: 1.6;

    font-size: 13px;
}

.winner-company {
    margin-bottom: 10px;

    font-size: 24px;
    font-weight: 800;

    line-height: 1.1;

    letter-spacing: -.04em;
}

.winner-month {
    margin-bottom: 18px;

    color: #C8A45D;

    font-size: 12px;
    font-weight: 700;
}

.winner-description {
    margin-bottom: 18px;

    color: #999999;

    font-size: 13px;

    line-height: 1.6;
}

.winner-link {
    color: #ffffff;

    font-size: 13px;

    text-decoration: none;
}

.winner-link:hover {
    text-decoration: underline;
}

.button {
    width: 100%;

    padding: 12px 14px;

    border: 0;
    border-radius: 10px;

    background: #C8A45D;

    color: #080808;

    font-size: 13px;
    font-weight: 800;

    cursor: pointer;
}

.button:hover {
    filter: brightness(1.06);
}

.button.secondary {
    background: #181818;
    color: #ffffff;

    border: 1px solid #292929;
}

.button.danger {
    background: #191010;

    color: #ff9c9c;

    border: 1px solid #351d1d;
}

.winner-actions {
    display: flex;
    flex-direction: column;

    gap: 10px;

    margin-top: 20px;
}

.empty {
    padding: 50px 20px;

    text-align: center;

    color: #666666;

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
            calc(100% - 28px);

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
        min-width: 0;
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

<?php foreach (
    $availableMonths
    as $availableMonth
): ?>

<option
value="<?= h($availableMonth) ?>"
<?= (
    $availableMonth === $month
)
    ? 'selected'
    : ''
?>
>

<?= h(
    month_label(
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
    : '—'
?>

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
    count($monthParticipations)
    === 0
): ?>

<div class="empty">

Aucun participant pour

<?= h(
    month_label($month)
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
            $participant[
                'company'
            ] ?? ''
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
    $participant['created_at']
    ?? ''
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
        $participant[
            'marketing_consent'
        ]
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
value="<?= h(
    $month
) ?>"
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
    count($monthParticipations)
    > 0
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
value="<?= h(
    $month
) ?>"
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


<div style="height:10px;">
</div>


<button
class="button"
type="submit"
onclick="
return confirm(
'Définir cette entreprise comme gagnante ?'
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