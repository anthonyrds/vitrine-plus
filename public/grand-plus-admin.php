<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE GRAND + — ADMINISTRATION
|--------------------------------------------------------------------------
|
| Administration privée des participations.
|
| Fonctionnalités :
| - consulter les participations
| - désigner un gagnant
| - envoyer le mail au gagnant
| - envoyer le mail aux autres participants
| - offre commerciale uniquement si consentement marketing
| - réinitialiser le gagnant
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

const OFFER_AMOUNT = 300;
const OFFER_VALIDITY_DAYS = 30;

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

function load_admin_credentials(): array
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

/*
|--------------------------------------------------------------------------
| UTILITAIRES
|--------------------------------------------------------------------------
*/

function read_json_file(string $file, mixed $default): mixed
{
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

function write_json_file(string $file, mixed $data): bool
{
    $directory = dirname($file);

    if (!is_dir($directory)) {
        if (
            !mkdir($directory, 0750, true) &&
            !is_dir($directory)
        ) {
            return false;
        }
    }

    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return @file_put_contents(
        $file,
        $json,
        LOCK_EX
    ) !== false;
}

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
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

function format_date(string $date): string
{
    if ($date === '') {
        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d/m/Y à H:i', $timestamp);
}

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

function load_participations(): array
{
    $data = read_json_file(
        PARTICIPATIONS_FILE,
        []
    );

    return is_array($data) ? $data : [];
}

function save_participations(array $participations): bool
{
    return write_json_file(
        PARTICIPATIONS_FILE,
        $participations
    );
}

function load_winner(): array
{
    $default = [
        'hasWinner' => false,
        'month' => '',
        'month_key' => '',
        'company' => '',
        'description' => '',
        'website' => '',
        'image' => '',
        'winner_id' => '',
        'winner_email_sent' => false,
        'winner_email_sent_at' => '',
        'loser_emails_sent' => 0,
        'notification_completed_at' => '',
    ];

    $winner = read_json_file(
        WINNER_FILE,
        $default
    );

    if (!is_array($winner)) {
        return $default;
    }

    return array_merge(
        $default,
        $winner
    );
}

function save_winner(array $winner): bool
{
    return write_json_file(
        WINNER_FILE,
        $winner
    );
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
            strlen($line) >= 4 &&
            $line[3] === ' '
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

    return in_array(
        $code,
        $codes,
        true
    );
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
            $config['from_name'] ??
            DEFAULT_FROM_NAME
        )
    );

    if (
        $host === '' ||
        $username === '' ||
        $password === '' ||
        $fromEmail === '' ||
        !filter_var(
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
            '=?UTF-8?B?' .
            base64_encode($subject) .
            '?=';

        $headers =
            'From: ' .
            $fromName .
            ' <' .
            $fromEmail .
            ">\r\n" .

            'To: <' .
            $to .
            ">\r\n" .

            'Subject: ' .
            $encodedSubject .
            "\r\n" .

            'MIME-Version: 1.0' .
            "\r\n" .

            'Content-Type: text/html; charset=UTF-8' .
            "\r\n";

        if (
            $replyTo !== null &&
            filter_var(
                $replyTo,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $headers .=
                'Reply-To: ' .
                $replyTo .
                "\r\n";
        }

        $message =
            $headers .
            "\r\n" .
            $html .
            "\r\n.";

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
| EMAILS
|--------------------------------------------------------------------------
*/

function email_layout(
    string $title,
    string $content
): string {
    return '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . h($title) . '</title>
</head>
<body style="
    margin:0;
    padding:0;
    background:#080808;
    color:#f5f5f2;
    font-family:Arial,Helvetica,sans-serif;
">
<div style="
    max-width:620px;
    margin:0 auto;
    padding:40px 20px;
">
    <div style="
        border:1px solid #242424;
        border-radius:20px;
        background:#111111;
        overflow:hidden;
    ">
        <div style="
            padding:26px 30px;
            border-bottom:1px solid #242424;
        ">
            <div style="
                font-size:22px;
                font-weight:800;
                letter-spacing:-0.04em;
            ">
                Vitrine<span style="color:#C8A45D;">+</span>
            </div>
        </div>

        <div style="
            padding:34px 30px;
        ">
            ' . $content . '
        </div>

        <div style="
            padding:22px 30px;
            border-top:1px solid #242424;
            color:#777777;
            font-size:12px;
            line-height:1.6;
        ">
            Le Grand+ — Vitrine+<br>
            Votre entreprise. En mieux.
        </div>
    </div>
</div>
</body>
</html>';
}

function send_winner_email(
    array $config,
    array $participant,
    string $monthLabel
): bool {
    $name = trim(
        (string) ($participant['name'] ?? '')
    );

    $company = trim(
        (string) ($participant['company'] ?? '')
    );

    $email = trim(
        (string) ($participant['email'] ?? '')
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

    $safeName = h(
        $name !== '' ? $name : 'Bonjour'
    );

    $safeCompany = h(
        $company !== '' ? $company : 'votre entreprise'
    );

    $content = '
        <div style="
            color:#C8A45D;
            font-size:11px;
            font-weight:800;
            letter-spacing:.18em;
            text-transform:uppercase;
            margin-bottom:14px;
        ">
            LE GRAND+ — ' . h($monthLabel) . '
        </div>

        <h1 style="
            margin:0 0 18px;
            font-size:36px;
            line-height:1.05;
            letter-spacing:-.05em;
        ">
            Félicitations ' . $safeName . ' !
        </h1>

        <p style="
            color:#b5b5b5;
            font-size:16px;
            line-height:1.7;
            margin:0 0 20px;
        ">
            Nous avons le plaisir de vous annoncer que
            <strong style="color:#ffffff;">
                ' . $safeCompany . '
            </strong>
            a été désignée gagnante du Grand+ pour
            <strong style="color:#ffffff;">
                ' . h($monthLabel) . '
            </strong>.
        </p>

        <div style="
            margin:26px 0;
            padding:22px;
            border:1px solid rgba(200,164,93,.35);
            border-radius:14px;
            background:rgba(200,164,93,.07);
        ">
            <div style="
                color:#C8A45D;
                font-size:13px;
                font-weight:800;
                margin-bottom:8px;
            ">
                VOTRE CADEAU
            </div>

            <div style="
                color:#ffffff;
                font-size:21px;
                font-weight:800;
                line-height:1.3;
            ">
                La refonte complète de votre site internet,
                100 % offerte.
            </div>

            <div style="
                color:#999999;
                font-size:13px;
                line-height:1.6;
                margin-top:10px;
            ">
                Nous allons reprendre contact avec vous afin
                d’échanger sur votre entreprise, vos objectifs
                et votre futur site.
            </div>
        </div>

        <p style="
            color:#999999;
            font-size:14px;
            line-height:1.7;
            margin:0;
        ">
            Merci encore pour votre participation au Grand+.
            Nous sommes ravis de pouvoir mettre notre expertise
            au service de votre entreprise.
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

function send_non_winner_email(
    array $config,
    array $participant,
    string $monthLabel
): bool {
    $name = trim(
        (string) ($participant['name'] ?? '')
    );

    $company = trim(
        (string) ($participant['company'] ?? '')
    );

    $email = trim(
        (string) ($participant['email'] ?? '')
    );

    $marketing = !empty(
        $participant['marketing_consent']
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

    $safeName = h(
        $name !== '' ? $name : 'Bonjour'
    );

    $safeCompany = h(
        $company !== '' ? $company : 'votre entreprise'
    );

    if ($marketing) {
        $content = '
            <div style="
                color:#C8A45D;
                font-size:11px;
                font-weight:800;
                letter-spacing:.18em;
                text-transform:uppercase;
                margin-bottom:14px;
            ">
                LE GRAND+ — ' . h($monthLabel) . '
            </div>

            <h1 style="
                margin:0 0 18px;
                font-size:32px;
                line-height:1.05;
                letter-spacing:-.05em;
            ">
                Merci pour votre participation,
                ' . $safeName . '.
            </h1>

            <p style="
                color:#b5b5b5;
                font-size:16px;
                line-height:1.7;
                margin:0 0 20px;
            ">
                Cette fois, le Grand+ n’a malheureusement pas
                retenu
                <strong style="color:#ffffff;">
                    ' . $safeCompany . '
                </strong>.
            </p>

            <p style="
                color:#999999;
                font-size:14px;
                line-height:1.7;
                margin:0 0 24px;
            ">
                Mais nous ne voulions pas vous laisser repartir
                les mains vides.
            </p>

            <div style="
                margin:26px 0;
                padding:22px;
                border:1px solid rgba(200,164,93,.35);
                border-radius:14px;
                background:rgba(200,164,93,.07);
            ">
                <div style="
                    color:#C8A45D;
                    font-size:13px;
                    font-weight:800;
                    margin-bottom:8px;
                ">
                    VOTRE AVANTAGE
                </div>

                <div style="
                    color:#ffffff;
                    font-size:20px;
                    font-weight:800;
                    line-height:1.3;
                ">
                    Audit stratégique offert
                    + 300 € sur votre site internet.
                </div>

                <div style="
                    color:#999999;
                    font-size:13px;
                    line-height:1.6;
                    margin-top:10px;
                ">
                    Offre valable pendant ' .
                    OFFER_VALIDITY_DAYS .
                    ' jours à compter de cet e-mail.
                </div>
            </div>

            <p style="
                color:#999999;
                font-size:14px;
                line-height:1.7;
                margin:0;
            ">
                Si vous souhaitez en profiter, répondez simplement
                à cet e-mail ou prenez rendez-vous avec Vitrine+.
            </p>
        ';
    } else {
        $content = '
            <div style="
                color:#C8A45D;
                font-size:11px;
                font-weight:800;
                letter-spacing:.18em;
                text-transform:uppercase;
                margin-bottom:14px;
            ">
                LE GRAND+ — ' . h($monthLabel) . '
            </div>

            <h1 style="
                margin:0 0 18px;
                font-size:32px;
                line-height:1.05;
                letter-spacing:-.05em;
            ">
                Merci pour votre participation,
                ' . $safeName . '.
            </h1>

            <p style="
                color:#b5b5b5;
                font-size:16px;
                line-height:1.7;
                margin:0 0 20px;
            ">
                Le Grand+ de
                <strong style="color:#ffffff;">
                    ' . h($monthLabel) . '
                </strong>
                a été attribué à une autre entreprise.
            </p>

            <p style="
                color:#999999;
                font-size:14px;
                line-height:1.7;
                margin:0;
            ">
                Merci sincèrement d’avoir participé et d’avoir
                accordé votre confiance à Vitrine+.
            </p>
        ';
    }

    return smtp_send_html_email(
        $config,
        $email,
        'Merci pour votre participation au Grand+',
        email_layout(
            'Merci pour votre participation',
            $content
        ),
        $config['from_email'] ?? null
    );
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

require_auth();

$config = require CONFIG_FILE;

if (!is_array($config)) {
    $config = [];
}

$participations = load_participations();

$currentMonth = current_month_key();
$currentMonthLabel = current_month_label();

$currentMonthParticipants = array_values(
    array_filter(
        $participations,
        static function ($item) use ($currentMonth): bool {
            return is_array($item)
                && (string) ($item['month_key'] ?? '') === $currentMonth;
        }
    )
);

$actionMessage = '';
$actionError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $action = (string) (
        $_POST['action'] ?? ''
    );

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
                $currentMonthParticipants
                as $participant
            ) {
                if (
                    isset($participant['id']) &&
                    (string) $participant['id'] === $winnerId
                ) {
                    $winnerParticipant = $participant;
                    break;
                }
            }

            if ($winnerParticipant === null) {
                $actionError =
                    'Participant introuvable.';
            } else {
                $existingWinner = load_winner();

                /*
                 * On évite de désigner deux gagnants
                 * accidentellement sans passer par reset.
                 */
                if (
                    !empty($existingWinner['hasWinner']) &&
                    (string) (
                        $existingWinner['month_key'] ?? ''
                    ) === $currentMonth
                ) {
                    $actionError =
                        'Un gagnant est déjà enregistré pour ce mois. Réinitialisez-le avant de procéder à une nouvelle désignation.';
                } else {
                    $winner = [
                        'hasWinner' => true,
                        'month' => (
                            string
                        ) (
                            $winnerParticipant['month_label']
                            ?? $currentMonthLabel
                        ),
                        'month_key' => $currentMonth,
                        'company' => (
                            string
                        ) (
                            $winnerParticipant['company']
                            ?? ''
                        ),
                        'description' => (
                            string
                        ) (
                            $winnerParticipant['problem']
                            ?? ''
                        ),
                        'website' => (
                            string
                        ) (
                            $winnerParticipant['website']
                            ?? ''
                        ),
                        'image' => '',
                        'winner_id' => $winnerId,
                        'winner_email_sent' => false,
                        'winner_email_sent_at' => '',
                        'loser_emails_sent' => 0,
                        'notification_completed_at' => '',
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Mise à jour des statuts
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $participations
                        as $index => $participant
                    ) {
                        if (
                            !is_array($participant) ||
                            (string) (
                                $participant['month_key']
                                ?? ''
                            ) !== $currentMonth
                        ) {
                            continue;
                        }

                        if (
                            (string) (
                                $participant['id'] ?? ''
                            ) === $winnerId
                        ) {
                            $participations[$index]['status'] =
                                'winner';
                        } else {
                            $participations[$index]['status'] =
                                'not_winner';
                        }
                    }

                    if (
                        !save_participations(
                            $participations
                        )
                    ) {
                        $actionError =
                            'Impossible de mettre à jour les participations.';
                    } elseif (
                        !save_winner($winner)
                    ) {
                        $actionError =
                            'Impossible d’enregistrer le gagnant.';
                    } else {
                        /*
                        |--------------------------------------------------------------------------
                        | EMAIL DU GAGNANT
                        |--------------------------------------------------------------------------
                        */

                        $winnerEmailSent =
                            send_winner_email(
                                $config,
                                $winnerParticipant,
                                $currentMonthLabel
                            );

                        $winner['winner_email_sent'] =
                            $winnerEmailSent;

                        if ($winnerEmailSent) {
                            $winner['winner_email_sent_at'] =
                                date('c');
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | EMAILS DES AUTRES PARTICIPANTS
                        |--------------------------------------------------------------------------
                        */

                        $loserEmailsSent = 0;

                        foreach (
                            $participations
                            as $index => $participant
                        ) {
                            if (
                                !is_array($participant)
                            ) {
                                continue;
                            }

                            if (
                                (string) (
                                    $participant['month_key']
                                    ?? ''
                                ) !== $currentMonth
                            ) {
                                continue;
                            }

                            if (
                                (string) (
                                    $participant['id']
                                    ?? ''
                                ) === $winnerId
                            ) {
                                continue;
                            }

                            /*
                             * Si le mail a déjà été envoyé,
                             * on ne le renvoie pas.
                             */
                            if (
                                !empty(
                                    $participant['result_email_sent']
                                )
                            ) {
                                continue;
                            }

                            $sent =
                                send_non_winner_email(
                                    $config,
                                    $participant,
                                    $currentMonthLabel
                                );

                            if ($sent) {
                                $participations[$index][
                                    'result_email_sent'
                                ] = true;

                                $participations[$index][
                                    'result_email_sent_at'
                                ] = date('c');

                                $loserEmailsSent++;
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | ENREGISTREMENT DES EMAILS
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $loserEmailsSent > 0
                        ) {
                            save_participations(
                                $participations
                            );
                        }

                        $winner['loser_emails_sent'] =
                            $loserEmailsSent;

                        $winner[
                            'notification_completed_at'
                        ] = date('c');

                        save_winner($winner);

                        if ($winnerEmailSent) {
                            $actionMessage =
                                'Le gagnant a été enregistré. Le mail du gagnant a été envoyé et ' .
                                $loserEmailsSent .
                                ' mail(s) participant(s) ont été envoyé(s).';
                        } else {
                            $actionMessage =
                                'Le gagnant a été enregistré, mais le mail du gagnant n’a pas pu être envoyé. ' .
                                $loserEmailsSent .
                                ' mail(s) participant(s) ont été envoyé(s).';
                        }
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
            'month_key' => '',
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
            'winner_id' => '',
            'winner_email_sent' => false,
            'winner_email_sent_at' => '',
            'loser_emails_sent' => 0,
            'notification_completed_at' => '',
        ];

        if (
            save_winner($winner)
        ) {
            /*
             * On remet les statuts à pending,
             * mais on conserve les traces d'envoi des mails.
             * Cela évite les doublons si le gagnant est
             * désigné à nouveau.
             */
            foreach (
                $participations
                as $index => $participant
            ) {
                if (
                    is_array($participant) &&
                    (string) (
                        $participant['month_key']
                        ?? ''
                    ) === $currentMonth
                ) {
                    $participations[$index]['status'] =
                        'pending';
                }
            }

            save_participations(
                $participations
            );

            $actionMessage =
                'Le gagnant a été réinitialisé.';
        } else {
            $actionError =
                'Impossible de réinitialiser le gagnant.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RECHARGEMENT
    |--------------------------------------------------------------------------
    */

    $participations = load_participations();

    $currentMonthParticipants = array_values(
        array_filter(
            $participations,
            static function ($item) use ($currentMonth): bool {
                return is_array($item)
                    && (string) (
                        $item['month_key'] ?? ''
                    ) === $currentMonth;
            }
        )
    );
}

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

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

$winnerCount = count(
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
            !empty(
                $item['marketing_consent']
            )
    )
);

$winner = load_winner();

$winnerCompany = (string) (
    $winner['company'] ?? ''
);

$winnerEmailSent = !empty(
    $winner['winner_email_sent']
);

$loserEmailsSent = (int) (
    $winner['loser_emails_sent'] ?? 0
);

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

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

        .container {
            width: min(
                1400px,
                calc(100% - 40px)
            );

            margin: 0 auto;
            padding: 60px 0 90px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .brand span {
            color: #c8a45d;
        }

        .topbar a {
            color: rgba(255,255,255,.45);
            text-decoration: none;
            font-size: 12px;
        }

        .topbar a:hover {
            color: #ffffff;
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
            font-size: clamp(
                42px,
                7vw,
                88px
            );
            line-height: .9;
            letter-spacing: -.06em;
        }

        .month {
            margin-top: 18px;
            color: rgba(255,255,255,.45);
            font-size: 16px;
        }

        .alert {
            margin-top: 30px;
            padding: 16px 18px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert.success {
            background: rgba(70,180,110,.08);
            border: 1px solid rgba(70,180,110,.2);
            color: #9be1b3;
        }

        .alert.error {
            background: rgba(220,70,70,.08);
            border: 1px solid rgba(220,70,70,.2);
            color: #ff9c9c;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 50px;
        }

        .stat {
            padding: 24px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 22px;
            background: #111;
        }

        .stat-label {
            color: rgba(255,255,255,.35);
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

        .dashboard {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                360px;

            gap: 18px;
            margin-top: 50px;
            align-items: start;
        }

        .panel {
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 24px;
            background: #111;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 20px 22px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }

        .panel-title {
            font-size: 14px;
            font-weight: 800;
        }

        .panel-meta {
            color: rgba(255,255,255,.35);
            font-size: 11px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            text-align: left;
            vertical-align: top;
        }

        th {
            color: rgba(255,255,255,.35);
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
            color: rgba(255,255,255,.4);
            font-size: 12px;
        }

        .problem {
            max-width: 280px;
            color: rgba(255,255,255,.55);
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
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.6);
        }

        .winner {
            background: rgba(200,164,93,.16);
            color: #c8a45d;
        }

        .not-winner {
            background: rgba(255,255,255,.05);
            color: rgba(255,255,255,.35);
        }

        .yes {
            color: #c8a45d;
            font-weight: 800;
        }

        .no {
            color: rgba(255,255,255,.3);
        }

        .empty {
            padding: 60px;
            text-align: center;
            color: rgba(255,255,255,.4);
        }

        .winner-panel {
            padding: 24px;
        }

        .winner-company {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .winner-month {
            margin-bottom: 12px;
            color: #c8a45d;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .winner-description {
            margin-top: 15px;
            color: rgba(255,255,255,.5);
            font-size: 13px;
            line-height: 1.6;
        }

        .winner-website {
            display: inline-block;
            margin-top: 16px;
            color: #ffffff;
            font-size: 13px;
        }

        .winner-status {
            margin-top: 22px;
            padding: 14px;
            border-radius: 12px;
            background: rgba(255,255,255,.04);
            color: rgba(255,255,255,.5);
            font-size: 12px;
            line-height: 1.6;
        }

        .winner-status strong {
            color: #ffffff;
        }

        .winner-empty {
            color: rgba(255,255,255,.4);
            font-size: 13px;
            line-height: 1.6;
        }

        .actions {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255,255,255,.45);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        select {
            width: 100%;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            padding: 13px 14px;
            background: #080808;
            color: #ffffff;
            outline: none;
        }

        button {
            width: 100%;
            margin-top: 10px;
            border: 0;
            border-radius: 12px;
            padding: 14px 16px;
            background: #c8a45d;
            color: #080808;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        button:hover {
            filter: brightness(1.06);
        }

        button.danger {
            margin-top: 10px;
            background: #191010;
            color: #ff9c9c;
            border: 1px solid #351d1d;
        }

        .mail-summary {
            margin-top: 14px;
            color: rgba(255,255,255,.35);
            font-size: 11px;
            line-height: 1.6;
        }

        @media (max-width: 1100px) {

            .stats {
                grid-template-columns:
                    repeat(3, 1fr);
            }

            .dashboard {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .container {
                width: min(
                    100% - 24px,
                    1400px
                );

                padding: 35px 0 60px;
            }

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);

                margin-top: 35px;
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

    <div class="topbar">

        <div class="brand">
            Vitrine<span>+</span>
        </div>

        <div>
            <a
                href="/le-grand-plus"
                target="_blank"
                rel="noreferrer"
            >
                Voir le Grand+ →
            </a>
        </div>

    </div>

    <div style="margin-top:55px;">

        <div class="eyebrow">
            Administration
        </div>

        <h1>
            Le Grand +
        </h1>

        <div class="month">
            <?= h($currentMonthLabel) ?>
        </div>

    </div>

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
                <?= $winnerCount ?>
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

    <div class="dashboard">

        <section class="panel">

            <div class="panel-header">

                <div class="panel-title">
                    Participants
                </div>

                <div class="panel-meta">
                    <?= $total ?>
                    participation(s)
                </div>

            </div>

            <?php if ($total === 0): ?>

                <div class="empty">
                    Aucun participant pour
                    <?= h($currentMonthLabel) ?>.
                </div>

            <?php else: ?>

                <div class="table-wrap">

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

                        <?php
                        foreach (
                            $currentMonthParticipants
                            as $participant
                        ):
                        ?>

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

                                    <?php if (
                                        !empty(
                                            $participant['phone']
                                        )
                                    ): ?>

                                        <div class="muted">
                                            <?= h(
                                                (string) (
                                                    $participant['phone']
                                                )
                                            ) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $participant['website']
                                        )
                                    ): ?>

                                        <a
                                            href="<?= h(
                                                (string) (
                                                    $participant['website']
                                                )
                                            ) ?>"
                                            target="_blank"
                                            rel="noreferrer"
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

                                        <span class="badge winner">
                                            Gagnant
                                        </span>

                                    <?php elseif (
                                        $status === 'not_winner'
                                    ): ?>

                                        <span class="badge not-winner">
                                            Non gagnant
                                        </span>

                                    <?php else: ?>

                                        <span class="badge pending">
                                            En attente
                                        </span>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $participant[
                                                'result_email_sent'
                                            ]
                                        )
                                    ): ?>

                                        <div class="muted">
                                            Mail envoyé
                                        </div>

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
                            (string) (
                                $winner['month']
                                ?? $currentMonthLabel
                            )
                        ) ?>
                    </div>

                    <div class="winner-company">
                        <?= h($winnerCompany) ?>
                    </div>

                    <?php if (
                        !empty(
                            $winner['description']
                        )
                    ): ?>

                        <div class="winner-description">
                            <?= h(
                                (string) (
                                    $winner['description']
                                )
                            ) ?>
                        </div>

                    <?php endif; ?>

                    <?php if (
                        !empty(
                            $winner['website']
                        )
                    ): ?>

                        <a
                            class="winner-website"
                            href="<?= h(
                                (string) (
                                    $winner['website']
                                )
                            ) ?>"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Voir le site →
                        </a>

                    <?php endif; ?>

                    <div class="winner-status">

                        <div>
                            Mail gagnant :
                            <strong>
                                <?= $winnerEmailSent
                                    ? 'envoyé'
                                    : 'non envoyé'
                                ?>
                            </strong>
                        </div>

                        <div>
                            Mails participants :
                            <strong>
                                <?= $loserEmailsSent ?>
                            </strong>
                        </div>

                    </div>

                    <div class="actions">

                        <form
                            method="post"
                            onsubmit="
                                return confirm(
                                    'Réinitialiser le gagnant du mois ?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="reset_winner"
                            >

                            <button
                                type="submit"
                                class="danger"
                            >
                                Réinitialiser le gagnant
                            </button>

                        </form>

                    </div>

                <?php else: ?>

                    <div class="winner-empty">
                        Aucun gagnant n'est actuellement
                        enregistré pour <?= h(
                            $currentMonthLabel
                        ) ?>.
                    </div>

                <?php endif; ?>

                <?php if (
                    $total > 0 &&
                    empty(
                        $winner['hasWinner']
                    )
                ): ?>

                    <div class="actions">

                        <form
                            method="post"
                            onsubmit="
                                return confirm(
                                    'Désigner cette entreprise comme gagnante ? Les mails seront envoyés immédiatement.'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="select_winner"
                            >

                            <label
                                class="field-label"
                                for="winner_id"
                            >
                                Choisir le gagnant
                            </label>

                            <select
                                id="winner_id"
                                name="winner_id"
                                required
                            >

                                <option value="">
                                    Choisir une entreprise…
                                </option>

                                <?php
                                foreach (
                                    $currentMonthParticipants
                                    as $participant
                                ):
                                ?>

                                    <option
                                        value="<?= h(
                                            (string) (
                                                $participant['id']
                                                ?? ''
                                            )
                                        ) ?>"
                                    >
                                        <?= h(
                                            (string) (
                                                $participant['company']
                                                ?? ''
                                            )
                                        ) ?>
                                        —
                                        <?= h(
                                            (string) (
                                                $participant['name']
                                                ?? ''
                                            )
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <button type="submit">
                                Désigner le gagnant
                            </button>

                        </form>

                        <div class="mail-summary">
                            Le gagnant recevra son e-mail
                            automatiquement. Les autres participants
                            recevront également leur e-mail de résultat.
                            L'offre commerciale n'est envoyée qu'aux
                            participants ayant accepté le marketing.
                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</div>

</body>
</html>