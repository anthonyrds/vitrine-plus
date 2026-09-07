<?php

declare(strict_types=1);

session_start();

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
| SÉCURITÉ
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
            'Les identifiants administrateur du Grand + ne sont pas configurés dans vitrine-mail-config.php.'
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
            'WWW-Authenticate: Basic realm="Vitrine+ — Grand +"'
        );

        http_response_code(401);

        exit('Authentification requise.');
    }

    $user = (string) $_SERVER['PHP_AUTH_USER'];
    $password = (string) $_SERVER['PHP_AUTH_PW'];

    if (
        !hash_equals(
            $credentials['username'],
            $user
        ) ||
        !hash_equals(
            $credentials['password'],
            $password
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ — Grand +"'
        );

        http_response_code(401);

        exit('Identifiants incorrects.');
    }
}

require_auth();

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['grand_plus_admin_csrf'])
) {
    $_SESSION['grand_plus_admin_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    (string) $_SESSION['grand_plus_admin_csrf'];

function verify_csrf(): void
{
    $sessionToken =
        (string) (
            $_SESSION['grand_plus_admin_csrf']
            ?? ''
        );

    $postedToken =
        (string) (
            $_POST['csrf_token']
            ?? ''
        );

    if (
        $sessionToken === '' ||
        $postedToken === '' ||
        !hash_equals(
            $sessionToken,
            $postedToken
        )
    ) {
        http_response_code(403);

        exit('Requête non autorisée.');
    }
}

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

    if (
        $content === false ||
        trim($content) === ''
    ) {
        return $default;
    }

    $decoded = json_decode(
        $content,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE
    ) {
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

    return @file_put_contents(
        $file,
        $json,
        LOCK_EX
    ) !== false;
}

function current_month_key(): string
{
    return date('Y-m');
}

function month_label(
    string $monthKey
): string {
    $parts = explode(
        '-',
        $monthKey
    );

    if (
        count($parts) !== 2
    ) {
        return $monthKey;
    }

    $year = (int) $parts[0];
    $month = (int) $parts[1];

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

    return (
        $months[$month] ?? $monthKey
    ) . ' ' . $year;
}

function normalize_email(
    string $email
): string {
    return function_exists('mb_strtolower')
        ? mb_strtolower(
            trim($email),
            'UTF-8'
        )
        : strtolower(
            trim($email)
        );
}

function redirect_admin(
    string $month,
    string $message = '',
    string $type = 'success'
): never {
    $url =
        'grand-plus-admin.php?month=' .
        rawurlencode($month);

    if ($message !== '') {
        $url .=
            '&message=' .
            rawurlencode($message);

        $url .=
            '&type=' .
            rawurlencode($type);
    }

    header(
        'Location: ' . $url
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| SMTP
|--------------------------------------------------------------------------
*/

function smtp_read($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets(
            $socket,
            515
        );

        if ($line === false) {
            break;
        }

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

function smtp_expect(
    $socket,
    array $expectedCodes
): void {
    $response = smtp_read(
        $socket
    );

    $code = (int) substr(
        trim($response),
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
        throw new RuntimeException(
            'Réponse SMTP inattendue : ' .
            $code
        );
    }
}

function smtp_command(
    $socket,
    string $command,
    array $expectedCodes
): void {
    fwrite(
        $socket,
        $command . "\r\n"
    );

    smtp_expect(
        $socket,
        $expectedCodes
    );
}

function smtp_send_mail(
    array $config,
    string $to,
    string $subject,
    string $body,
    ?string $replyTo = null
): bool {
    $host =
        (string) (
            $config['smtp_host']
            ?? ''
        );

    $port =
        (int) (
            $config['smtp_port']
            ?? 465
        );

    $username =
        (string) (
            $config['smtp_username']
            ?? ''
        );

    $password =
        (string) (
            $config['smtp_password']
            ?? ''
        );

    $fromEmail =
        (string) (
            $config['from_email']
            ?? $username
        );

    $fromName =
        (string) (
            $config['from_name']
            ?? DEFAULT_FROM_NAME
        );

    if (
        $host === '' ||
        $username === '' ||
        $password === ''
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

    try {
        smtp_expect(
            $socket,
            [220]
        );

        smtp_command(
            $socket,
            'EHLO vitrineplus.fr',
            [250]
        );

        smtp_command(
            $socket,
            'AUTH LOGIN',
            [334]
        );

        smtp_command(
            $socket,
            base64_encode($username),
            [334]
        );

        smtp_command(
            $socket,
            base64_encode($password),
            [235]
        );

        smtp_command(
            $socket,
            'MAIL FROM:<' .
            $fromEmail .
            '>',
            [250]
        );

        smtp_command(
            $socket,
            'RCPT TO:<' .
            $to .
            '>',
            [250, 251]
        );

        smtp_command(
            $socket,
            'DATA',
            [354]
        );

        $headers = [];

        $headers[] =
            'From: ' .
            $fromName .
            ' <' .
            $fromEmail .
            '>';

        $headers[] =
            'To: <' .
            $to .
            '>';

        $headers[] =
            'Subject: ' .
            '=?UTF-8?B?' .
            base64_encode($subject) .
            '?=';

        $headers[] =
            'MIME-Version: 1.0';

        $headers[] =
            'Content-Type: text/plain; charset=UTF-8';

        $headers[] =
            'Content-Transfer-Encoding: 8bit';

        if (
            $replyTo !== null &&
            filter_var(
                $replyTo,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $headers[] =
                'Reply-To: <' .
                $replyTo .
                '>';
        }

        $message =
            implode(
                "\r\n",
                $headers
            ) .
            "\r\n\r\n" .
            $body .
            "\r\n.";

        fwrite(
            $socket,
            $message . "\r\n"
        );

        smtp_expect(
            $socket,
            [250]
        );

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
| DONNÉES
|--------------------------------------------------------------------------
*/

$participations = read_json_file(
    PARTICIPATIONS_FILE,
    []
);

if (!is_array($participations)) {
    $participations = [];
}

$months = [];

foreach (
    $participations as $participant
) {
    if (!is_array($participant)) {
        continue;
    }

    $month =
        (string) (
            $participant['month_key']
            ?? ''
        );

    if ($month !== '') {
        $months[$month] = true;
    }
}

$months[current_month_key()] = true;

$monthList = array_keys($months);

rsort($monthList);

$selectedMonth =
    (string) (
        $_GET['month']
        ?? current_month_key()
    );

if (
    !in_array(
        $selectedMonth,
        $monthList,
        true
    )
) {
    $selectedMonth =
        current_month_key();
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action =
        (string) (
            $_POST['action']
            ?? ''
        );

    $month =
        (string) (
            $_POST['month']
            ?? current_month_key()
        );

    if (
        !preg_match(
            '/^\d{4}-\d{2}$/',
            $month
        )
    ) {
        $month =
            current_month_key();
    }

    /*
     * --------------------------------------------------------------
     * DÉSIGNER UN GAGNANT
     * --------------------------------------------------------------
     */

    if (
        $action === 'select_winner'
    ) {
        $participantId =
            trim(
                (string) (
                    $_POST['participant_id']
                    ?? ''
                )
            );

        $description =
            trim(
                (string) (
                    $_POST['winner_description']
                    ?? ''
                )
            );

        $image =
            trim(
                (string) (
                    $_POST['winner_image']
                    ?? ''
                )
            );

        if ($participantId === '') {
            redirect_admin(
                $month,
                'Aucun participant sélectionné.',
                'error'
            );
        }

        $found = false;

        foreach (
            $participations as $index => $participant
        ) {
            if (!is_array($participant)) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $month
            ) {
                continue;
            }

            if (
                ($participant['id'] ?? '') ===
                $participantId
            ) {
                $participations[$index]['status'] =
                    'winner';

                $participations[$index]['winner_selected_at'] =
                    date('c');

                $found = true;

            } elseif (
                ($participant['status'] ?? 'pending') ===
                'pending'
            ) {
                $participations[$index]['status'] =
                    'not_winner';

                $participations[$index]['winner_selected_at'] =
                    null;
            }
        }

        if (!$found) {
            redirect_admin(
                $month,
                'Participant introuvable.',
                'error'
            );
        }

        if (
            !write_json_file(
                PARTICIPATIONS_FILE,
                $participations
            )
        ) {
            redirect_admin(
                $month,
                'Impossible d’enregistrer les participations.',
                'error'
            );
        }

        $winner = null;

        foreach (
            $participations as $participant
        ) {
            if (
                is_array($participant) &&
                ($participant['id'] ?? '') ===
                $participantId
            ) {
                $winner = $participant;
                break;
            }
        }

        if (
            !is_array($winner)
        ) {
            redirect_admin(
                $month,
                'Gagnant introuvable après enregistrement.',
                'error'
            );
        }

        $winnerData = [
            'hasWinner' => true,
            'month' => month_label($month),
            'month_key' => $month,
            'company' =>
                (string) (
                    $winner['company']
                    ?? ''
                ),
            'description' =>
                $description,
            'website' =>
                (string) (
                    $winner['website']
                    ?? ''
                ),
            'image' =>
                $image,
            'updated_at' =>
                date('c'),
        ];

        if (
            !write_json_file(
                WINNER_FILE,
                $winnerData
            )
        ) {
            redirect_admin(
                $month,
                'Gagnant enregistré mais impossible de mettre à jour la vitrine publique.',
                'error'
            );
        }

        redirect_admin(
            $month,
            'Le gagnant du mois a été enregistré.'
        );
    }

    /*
     * --------------------------------------------------------------
     * RÉINITIALISER LE GAGNANT
     * --------------------------------------------------------------
     */

    if (
        $action === 'reset_winner'
    ) {
        foreach (
            $participations as $index => $participant
        ) {
            if (
                !is_array($participant)
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $month
            ) {
                continue;
            }

            if (
                in_array(
                    ($participant['status'] ?? ''),
                    [
                        'winner',
                        'not_winner',
                    ],
                    true
                )
            ) {
                $participations[$index]['status'] =
                    'pending';

                $participations[$index]['winner_selected_at'] =
                    null;
            }
        }

        write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        );

        $winnerData = [
            'hasWinner' => false,
            'month' => month_label($month),
            'month_key' => $month,
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
            'updated_at' => date('c'),
        ];

        write_json_file(
            WINNER_FILE,
            $winnerData
        );

        redirect_admin(
            $month,
            'Le tirage du mois a été réinitialisé.'
        );
    }

    /*
     * --------------------------------------------------------------
     * ENVOYER LES RÉSULTATS
     * --------------------------------------------------------------
     */

    if (
        $action === 'send_results'
    ) {
        $config = load_config();

        $sentWinner = 0;
        $sentNonWinner = 0;

        foreach (
            $participations as $index => $participant
        ) {
            if (
                !is_array($participant)
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $month
            ) {
                continue;
            }

            $status =
                (string) (
                    $participant['status']
                    ?? 'pending'
                );

            $email =
                normalize_email(
                    (string) (
                        $participant['email']
                        ?? ''
                    )
                );

            $name =
                (string) (
                    $participant['name']
                    ?? ''
                );

            $company =
                (string) (
                    $participant['company']
                    ?? ''
                );

            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                continue;
            }

            /*
             * GAGNANT
             */

            if (
                $status === 'winner' &&
                empty(
                    $participant[
                        'result_email_sent_at'
                    ]
                )
            ) {
                $subject =
                    'Le Grand + — Votre entreprise a été sélectionnée';

                $body =
                    "Bonjour " .
                    $name .
                    ",\n\n" .

                    "Nous avons une excellente nouvelle à vous annoncer.\n\n" .

                    "Votre entreprise « " .
                    $company .
                    " » a été sélectionnée comme gagnante du Grand + de Vitrine+ pour " .
                    month_label($month) .
                    ".\n\n" .

                    "Vitrine+ va donc vous offrir la refonte complète de votre site internet.\n\n" .

                    "Nous allons revenir vers vous très prochainement afin d’échanger sur votre entreprise, vos besoins et votre futur site.\n\n" .

                    "À très bientôt,\n\n" .
                    "L’équipe Vitrine+\n" .
                    "« Votre entreprise. En mieux. »";

                if (
                    smtp_send_mail(
                        $config,
                        $email,
                        $subject,
                        $body
                    )
                ) {
                    $participations[$index][
                        'result_email_sent_at'
                    ] = date('c');

                    $sentWinner++;
                }
            }

            /*
             * NON GAGNANT
             */

            if (
                $status === 'not_winner' &&
                empty(
                    $participant[
                        'result_email_sent_at'
                    ]
                )
            ) {
                $subject =
                    'Le Grand + — Merci pour votre participation';

                $body =
                    "Bonjour " .
                    $name .
                    ",\n\n" .

                    "Merci d’avoir participé au Grand + de Vitrine+ pour " .
                    month_label($month) .
                    ".\n\n" .

                    "Cette fois-ci, votre entreprise « " .
                    $company .
                    " » n’a malheureusement pas été sélectionnée.\n\n" .

                    "Mais votre participation nous a permis de découvrir votre activité, et nous vous en remercions sincèrement.\n\n" .

                    "Le Grand + revient chaque mois. Vous pourrez donc retenter votre chance lors d’une prochaine édition.\n\n" .

                    "À bientôt,\n\n" .
                    "L’équipe Vitrine+\n" .
                    "« Votre entreprise. En mieux. »";

                if (
                    smtp_send_mail(
                        $config,
                        $email,
                        $subject,
                        $body
                    )
                ) {
                    $participations[$index][
                        'result_email_sent_at'
                    ] = date('c');

                    $sentNonWinner++;
                }
            }
        }

        write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        );

        redirect_admin(
            $month,
            $sentWinner .
            ' mail gagnant et ' .
            $sentNonWinner .
            ' mail(s) non-gagnant(s) envoyé(s).'
        );
    }

    /*
     * --------------------------------------------------------------
     * OFFRE COMMERCIALE
     * --------------------------------------------------------------
     */

    if (
        $action === 'send_offer'
    ) {
        $config = load_config();

        $participantId =
            trim(
                (string) (
                    $_POST['participant_id']
                    ?? ''
                )
            );

        foreach (
            $participations as $index => $participant
        ) {
            if (
                !is_array($participant)
            ) {
                continue;
            }

            if (
                ($participant['id'] ?? '') !==
                $participantId
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '') !==
                $month
            ) {
                continue;
            }

            if (
                empty(
                    $participant[
                        'marketing_consent'
                    ]
                )
            ) {
                redirect_admin(
                    $month,
                    'Cette personne n’a pas accepté les communications commerciales.',
                    'error'
                );
            }

            if (
                !empty(
                    $participant[
                        'offer_email_sent_at'
                    ]
                )
            ) {
                redirect_admin(
                    $month,
                    'L’offre a déjà été envoyée à cette personne.',
                    'error'
                );
            }

            $email =
                normalize_email(
                    (string) (
                        $participant['email']
                        ?? ''
                    )
                );

            $name =
                (string) (
                    $participant['name']
                    ?? ''
                );

            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                redirect_admin(
                    $month,
                    'Adresse e-mail invalide.',
                    'error'
                );
            }

            $subject =
                'Vitrine+ — Une proposition pour votre entreprise';

            $body =
                "Bonjour " .
                $name .
                ",\n\n" .

                "Merci encore pour votre participation au Grand + de Vitrine+.\n\n" .

                "Même si votre entreprise n’a pas été sélectionnée cette fois-ci, nous avons souhaité vous proposer une possibilité particulière : bénéficier d’un accompagnement Vitrine+ pour améliorer votre présence en ligne.\n\n" .

                "Nous pouvons notamment vous accompagner sur la création ou la refonte de votre site internet, votre visibilité et votre stratégie digitale.\n\n" .

                "Si le sujet vous intéresse, vous pouvez simplement répondre à cet e-mail ou prendre rendez-vous avec nous.\n\n" .

                "À bientôt,\n\n" .
                "L’équipe Vitrine+\n" .
                "« Votre entreprise. En mieux. »";

            if (
                smtp_send_mail(
                    $config,
                    $email,
                    $subject,
                    $body,
                    $email
                )
            ) {
                $participations[$index][
                    'offer_email_sent_at'
                ] = date('c');

                write_json_file(
                    PARTICIPATIONS_FILE,
                    $participations
                );

                redirect_admin(
                    $month,
                    'Offre envoyée.'
                );
            }

            redirect_admin(
                $month,
                'Impossible d’envoyer l’offre.',
                'error'
            );
        }

        redirect_admin(
            $month,
            'Participant introuvable.',
            'error'
        );
    }
}

/*
|--------------------------------------------------------------------------
| FILTRAGE DU MOIS
|--------------------------------------------------------------------------
*/

$currentParticipants = array_values(
    array_filter(
        $participations,
        static function ($participant) use (
            $selectedMonth
        ): bool {
            return is_array($participant)
                && (
                    (string) (
                        $participant['month_key']
                        ?? ''
                    )
                ) === $selectedMonth;
        }
    )
);

/*
|--------------------------------------------------------------------------
| TRI
|--------------------------------------------------------------------------
*/

usort(
    $currentParticipants,
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

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$total = count(
    $currentParticipants
);

$pending = 0;
$winnerCount = 0;
$notWinner = 0;
$marketing = 0;
$resultSent = 0;
$offerSent = 0;

$winnerParticipant = null;

foreach (
    $currentParticipants as $participant
) {
    $status =
        (string) (
            $participant['status']
            ?? 'pending'
        );

    if ($status === 'pending') {
        $pending++;
    }

    if ($status === 'winner') {
        $winnerCount++;
        $winnerParticipant = $participant;
    }

    if ($status === 'not_winner') {
        $notWinner++;
    }

    if (
        !empty(
            $participant['marketing_consent']
        )
    ) {
        $marketing++;
    }

    if (
        !empty(
            $participant['result_email_sent_at']
        )
    ) {
        $resultSent++;
    }

    if (
        !empty(
            $participant['offer_email_sent_at']
        )
    ) {
        $offerSent++;
    }
}

$winnerPublicData =
    read_json_file(
        WINNER_FILE,
        [
            'hasWinner' => false,
            'month' => '',
            'company' => '',
            'description' => '',
            'website' => '',
            'image' => '',
        ]
    );

$message =
    (string) (
        $_GET['message']
        ?? ''
    );

$messageType =
    (string) (
        $_GET['type']
        ?? 'success'
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

<title>Administration Grand + — Vitrine+</title>

<style>

:root {
    --black: #080808;
    --panel: #111111;
    --panel-2: #151515;
    --white: #ffffff;
    --muted: rgba(255,255,255,.48);
    --muted-2: rgba(255,255,255,.30);
    --border: rgba(255,255,255,.09);
    --gold: #c8a45d;
    --gold-dark: #9a773d;
    --green: #7ed7a1;
    --red: #ef8f8f;
}

* {
    box-sizing: border-box;
}

html {
    background: var(--black);
}

body {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(
            circle at 80% 0%,
            rgba(200,164,93,.09),
            transparent 32%
        ),
        var(--black);
    color: var(--white);
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Helvetica Neue",
        Helvetica,
        Arial,
        sans-serif;
}

button,
select,
input,
textarea {
    font: inherit;
}

a {
    color: inherit;
}

.topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    border-bottom: 1px solid var(--border);
    background: rgba(8,8,8,.90);
    backdrop-filter: blur(20px);
}

.topbar-inner {
    width: min(1480px, calc(100% - 40px));
    margin: 0 auto;
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.brand-mark {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: var(--white);
    color: var(--black);
    font-size: 15px;
    font-weight: 900;
}

.brand-copy {
    display: grid;
    gap: 2px;
}

.brand-copy strong {
    font-size: 13px;
}

.brand-copy span {
    color: var(--muted-2);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .16em;
    font-weight: 800;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid var(--border);
    border-radius: 999px;
    text-decoration: none;
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

.top-link:hover {
    color: var(--white);
    border-color: rgba(255,255,255,.18);
}

.container {
    width: min(1480px, calc(100% - 40px));
    margin: 0 auto;
    padding: 58px 0 100px;
}

.eyebrow {
    color: var(--gold);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .24em;
}

.hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 30px;
}

h1 {
    margin: 12px 0 0;
    font-size: clamp(48px, 7vw, 92px);
    line-height: .88;
    letter-spacing: -.065em;
}

.hero-sub {
    margin: 18px 0 0;
    color: var(--muted);
    line-height: 1.7;
    max-width: 620px;
}

.month-selector {
    min-width: 220px;
}

.month-selector label {
    display: block;
    margin-bottom: 8px;
    color: var(--muted-2);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .16em;
}

.month-selector select {
    width: 100%;
    min-height: 48px;
    padding: 0 15px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--panel);
    color: var(--white);
    outline: none;
}

.flash {
    margin-top: 30px;
    padding: 16px 18px;
    border-radius: 16px;
    font-size: 13px;
    font-weight: 700;
}

.flash.success {
    border: 1px solid rgba(126,215,161,.20);
    background: rgba(126,215,161,.08);
    color: var(--green);
}

.flash.error {
    border: 1px solid rgba(239,143,143,.20);
    background: rgba(239,143,143,.08);
    color: var(--red);
}

.stats {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    margin-top: 48px;
}

.stat {
    padding: 22px;
    border: 1px solid var(--border);
    border-radius: 22px;
    background: rgba(17,17,17,.88);
}

.stat-label {
    color: var(--muted-2);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .16em;
}

.stat-value {
    margin-top: 11px;
    font-size: 34px;
    font-weight: 900;
    letter-spacing: -.05em;
}

.section {
    margin-top: 46px;
}

.section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 18px;
}

.section-title {
    margin: 0;
    font-size: 24px;
    letter-spacing: -.04em;
}

.section-description {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

.winner-panel {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 30px;
    padding: 28px;
    border: 1px solid rgba(200,164,93,.18);
    border-radius: 26px;
    background:
        linear-gradient(
            135deg,
            rgba(200,164,93,.10),
            rgba(255,255,255,.025)
        );
}

.winner-company {
    margin-top: 7px;
    font-size: 32px;
    font-weight: 900;
    letter-spacing: -.05em;
}

.winner-meta {
    margin-top: 8px;
    color: var(--muted);
    font-size: 13px;
}

.badge {
    display: inline-flex;
    align-items: center;
    min-height: 27px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .10em;
}

.badge.pending {
    background: rgba(255,255,255,.07);
    color: var(--muted);
}

.badge.winner {
    background: rgba(200,164,93,.16);
    color: var(--gold);
}

.badge.not-winner {
    background: rgba(255,255,255,.045);
    color: var(--muted-2);
}

.badge.marketing {
    background: rgba(126,215,161,.10);
    color: var(--green);
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.button {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 15px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: transparent;
    color: var(--white);
    cursor: pointer;
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
}

.button:hover {
    border-color: rgba(255,255,255,.22);
}

.button.gold {
    background: var(--gold);
    border-color: var(--gold);
    color: var(--black);
}

.button.dark {
    background: var(--white);
    border-color: var(--white);
    color: var(--black);
}

.button.danger {
    color: var(--red);
    border-color: rgba(239,143,143,.18);
}

.button.small {
    min-height: 34px;
    padding: 0 12px;
    font-size: 9px;
}

.table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 24px;
    background: var(--panel);
}

table {
    width: 100%;
    min-width: 1250px;
    border-collapse: collapse;
}

th,
td {
    padding: 17px 18px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
}

th {
    color: var(--muted-2);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .14em;
}

td {
    font-size: 12px;
}

tr:last-child td {
    border-bottom: 0;
}

.company {
    font-size: 14px;
    font-weight: 900;
}

.contact {
    margin-top: 4px;
    color: var(--muted);
    line-height: 1.6;
}

.problem {
    max-width: 260px;
    color: var(--muted);
    line-height: 1.55;
}

.date {
    color: var(--muted-2);
    white-space: nowrap;
}

.actions-cell {
    min-width: 190px;
}

.empty {
    padding: 70px 30px;
    text-align: center;
    color: var(--muted);
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,.78);
    backdrop-filter: blur(10px);
}

.modal-backdrop.open {
    display: flex;
}

.modal {
    width: min(680px, 100%);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    padding: 28px;
    border: 1px solid var(--border);
    border-radius: 26px;
    background: #101010;
    box-shadow: 0 30px 100px rgba(0,0,0,.5);
}

.modal h2 {
    margin: 0;
    font-size: 28px;
    letter-spacing: -.04em;
}

.modal p {
    color: var(--muted);
    line-height: 1.6;
    font-size: 13px;
}

.field {
    margin-top: 18px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    color: var(--muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .14em;
}

.field input,
.field textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    outline: none;
    background: #151515;
    color: var(--white);
}

.field textarea {
    min-height: 120px;
    resize: vertical;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 24px;
}

.mobile-note {
    display: none;
}

@media (max-width: 1200px) {
    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 800px) {
    .container {
        width: min(100% - 24px, 1480px);
        padding-top: 35px;
    }

    .topbar-inner {
        width: min(100% - 24px, 1480px);
    }

    .brand-copy {
        display: none;
    }

    .hero {
        display: block;
    }

    .month-selector {
        margin-top: 25px;
    }

    .winner-panel {
        grid-template-columns: 1fr;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .mobile-note {
        display: block;
        margin-top: 15px;
        color: var(--muted-2);
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .stat {
        padding: 16px;
    }

    .stat-value {
        font-size: 27px;
    }

    .top-link {
        padding: 0 11px;
    }
}

</style>

</head>

<body>

<header class="topbar">

    <div class="topbar-inner">

        <a
            href="https://vitrineplus.fr/"
            class="brand"
        >
            <div class="brand-mark">
                V+
            </div>

            <div class="brand-copy">
                <strong>Vitrine+</strong>
                <span>Administration</span>
            </div>
        </a>

        <div class="top-actions">

            <a
                href="https://vitrineplus.fr/le-grand-plus"
                class="top-link"
                target="_blank"
                rel="noreferrer"
            >
                Voir le Grand +
            </a>

            <a
                href="https://vitrineplus.fr/"
                class="top-link"
            >
                Retour au site
            </a>

        </div>

    </div>

</header>

<main class="container">

    <section class="hero">

        <div>

            <div class="eyebrow">
                Vitrine+ — Administration
            </div>

            <h1>
                Le Grand +
            </h1>

            <p class="hero-sub">
                Pilote les participations, sélectionne le gagnant,
                clôture l’édition et gère les communications.
            </p>

        </div>

        <form
            method="get"
            class="month-selector"
        >

            <label for="month">
                Édition
            </label>

            <select
                id="month"
                name="month"
                onchange="this.form.submit()"
            >

                <?php foreach ($monthList as $month): ?>

                    <option
                        value="<?= h($month) ?>"
                        <?= $month === $selectedMonth ? 'selected' : '' ?>
                    >
                        <?= h(month_label($month)) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </section>

    <?php if ($message !== ''): ?>

        <div
            class="flash <?= $messageType === 'error' ? 'error' : 'success' ?>"
        >
            <?= h($message) ?>
        </div>

    <?php endif; ?>

    <section class="stats">

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

        <div class="stat">
            <div class="stat-label">
                Mails résultats
            </div>
            <div class="stat-value">
                <?= $resultSent ?>
            </div>
        </div>

    </section>

    <?php if ($winnerCount > 0 && is_array($winnerParticipant)): ?>

        <section class="section">

            <div class="section-header">

                <div>
                    <h2 class="section-title">
                        Gagnant de l’édition
                    </h2>

                    <p class="section-description">
                        Cette entreprise est actuellement affichée comme gagnante.
                    </p>
                </div>

            </div>

            <div class="winner-panel">

                <div>

                    <span class="badge winner">
                        Gagnant
                    </span>

                    <div class="winner-company">
                        <?= h(
                            $winnerParticipant['company']
                            ?? ''
                        ) ?>
                    </div>

                    <div class="winner-meta">

                        <?= h(
                            $winnerParticipant['name']
                            ?? ''
                        ) ?>

                        ·

                        <?= h(
                            $winnerParticipant['email']
                            ?? ''
                        ) ?>

                    </div>

                </div>

                <div class="actions">

                    <form
                        method="post"
                        onsubmit="return confirm('Réinitialiser le gagnant de cette édition ?');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="reset_winner"
                        >

                        <input
                            type="hidden"
                            name="month"
                            value="<?= h($selectedMonth) ?>"
                        >

                        <button
                            type="submit"
                            class="button danger"
                        >
                            Réinitialiser
                        </button>

                    </form>

                </div>

            </div>

        </section>

    <?php endif; ?>

    <section class="section">

        <div class="section-header">

            <div>
                <h2 class="section-title">
                    Communications
                </h2>

                <p class="section-description">
                    Les mails déjà envoyés sont automatiquement mémorisés.
                </p>
            </div>

            <div class="actions">

                <?php if ($winnerCount > 0): ?>

                    <form
                        method="post"
                        onsubmit="return confirm('Envoyer les résultats aux participants qui ne les ont pas encore reçus ?');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="send_results"
                        >

                        <input
                            type="hidden"
                            name="month"
                            value="<?= h($selectedMonth) ?>"
                        >

                        <button
                            type="submit"
                            class="button dark"
                        >
                            Envoyer les résultats
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <section class="section">

        <div class="section-header">

            <div>
                <h2 class="section-title">
                    Participants
                </h2>

                <p class="section-description">
                    <?= $total ?>
                    participation<?= $total > 1 ? 's' : '' ?>
                    pour <?= h(month_label($selectedMonth)) ?>.
                </p>
            </div>

        </div>

        <?php if ($total === 0): ?>

            <div class="table-wrap">

                <div class="empty">
                    Aucun participant pour cette édition.
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>
                            <th>Entreprise</th>
                            <th>Contact</th>
                            <th>Activité</th>
                            <th>Problématique</th>
                            <th>Marketing</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($currentParticipants as $participant): ?>

                        <?php
                        $status =
                            (string) (
                                $participant['status']
                                ?? 'pending'
                            );

                        $marketingConsent =
                            !empty(
                                $participant[
                                    'marketing_consent'
                                ]
                            );

                        $resultAlreadySent =
                            !empty(
                                $participant[
                                    'result_email_sent_at'
                                ]
                            );

                        $offerAlreadySent =
                            !empty(
                                $participant[
                                    'offer_email_sent_at'
                                ]
                            );
                        ?>

                        <tr>

                            <td>

                                <div class="company">
                                    <?= h(
                                        $participant['company']
                                        ?? ''
                                    ) ?>
                                </div>

                                <div class="contact">
                                    <?= h(
                                        $participant['sector']
                                        ?? ''
                                    ) ?>
                                </div>

                                <?php if (!empty($participant['website'])): ?>

                                    <div style="margin-top:8px;">

                                        <a
                                            href="<?= h(
                                                $participant['website']
                                            ) ?>"
                                            target="_blank"
                                            rel="noreferrer"
                                            style="color:#c8a45d;text-decoration:none;font-weight:800;"
                                        >
                                            Voir le site →
                                        </a>

                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="company">
                                    <?= h(
                                        $participant['name']
                                        ?? ''
                                    ) ?>
                                </div>

                                <div class="contact">

                                    <?= h(
                                        $participant['email']
                                        ?? ''
                                    ) ?>

                                    <?php if (!empty($participant['phone'])): ?>

                                        <br>

                                        <?= h(
                                            $participant['phone']
                                        ) ?>

                                    <?php endif; ?>

                                </div>

                                <div class="date">

                                    <?= h(
                                        $participant['created_at']
                                        ?? ''
                                    ) ?>

                                </div>

                            </td>

                            <td>

                                <?= h(
                                    $participant['sector']
                                    ?? ''
                                ) ?>

                            </td>

                            <td>

                                <div class="problem">
                                    <?= h(
                                        $participant['problem']
                                        ?? ''
                                    ) ?>
                                </div>

                            </td>

                            <td>

                                <?php if ($marketingConsent): ?>

                                    <span class="badge marketing">
                                        Oui
                                    </span>

                                <?php else: ?>

                                    <span class="badge pending">
                                        Non
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($status === 'winner'): ?>

                                    <span class="badge winner">
                                        Gagnant
                                    </span>

                                <?php elseif ($status === 'not_winner'): ?>

                                    <span class="badge not-winner">
                                        Non gagnant
                                    </span>

                                <?php else: ?>

                                    <span class="badge pending">
                                        En attente
                                    </span>

                                <?php endif; ?>

                                <?php if ($resultAlreadySent): ?>

                                    <div
                                        style="margin-top:8px;color:#7ed7a1;font-size:10px;font-weight:800;"
                                    >
                                        Résultat envoyé
                                    </div>

                                <?php endif; ?>

                                <?php if ($offerAlreadySent): ?>

                                    <div
                                        style="margin-top:5px;color:#7ed7a1;font-size:10px;font-weight:800;"
                                    >
                                        Offre envoyée
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td class="actions-cell">

                                <div class="actions">

                                    <?php if ($status === 'pending'): ?>

                                        <button
                                            type="button"
                                            class="button gold small"
                                            onclick="openWinnerModal(
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        (string) ($participant['id'] ?? ''),
                                                        JSON_UNESCAPED_UNICODE |
                                                        JSON_UNESCAPED_SLASHES
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        (string) ($participant['company'] ?? ''),
                                                        JSON_UNESCAPED_UNICODE |
                                                        JSON_UNESCAPED_SLASHES
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        (string) ($participant['website'] ?? ''),
                                                        JSON_UNESCAPED_UNICODE |
                                                        JSON_UNESCAPED_SLASHES
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            )"
                                        >
                                            Désigner gagnant
                                        </button>

                                    <?php endif; ?>

                                    <?php if (
                                        $status === 'not_winner' &&
                                        $marketingConsent &&
                                        !$offerAlreadySent
                                    ): ?>

                                        <form
                                            method="post"
                                            onsubmit="return confirm('Envoyer une proposition commerciale à cette personne ?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= h($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="send_offer"
                                            >

                                            <input
                                                type="hidden"
                                                name="month"
                                                value="<?= h($selectedMonth) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="participant_id"
                                                value="<?= h(
                                                    $participant['id']
                                                    ?? ''
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="button small"
                                            >
                                                Envoyer offre
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="mobile-note">
                Faites défiler horizontalement le tableau sur mobile.
            </div>

        <?php endif; ?>

    </section>

</main>

<div
    id="winnerModal"
    class="modal-backdrop"
>

    <div class="modal">

        <h2>
            Désigner le gagnant
        </h2>

        <p>
            Cette action désignera cette entreprise comme gagnante
            et classera automatiquement les autres participants
            de l’édition comme non-gagnants.
        </p>

        <form
            method="post"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="select_winner"
            >

            <input
                type="hidden"
                name="month"
                value="<?= h($selectedMonth) ?>"
            >

            <input
                type="hidden"
                name="participant_id"
                id="winnerParticipantId"
            >

            <div class="field">

                <label>
                    Entreprise sélectionnée
                </label>

                <input
                    id="winnerCompany"
                    type="text"
                    readonly
                >

            </div>

            <div class="field">

                <label for="winnerDescription">
                    Description publique
                </label>

                <textarea
                    id="winnerDescription"
                    name="winner_description"
                    placeholder="Présentez brièvement l'entreprise et le projet réalisé..."
                ></textarea>

            </div>

            <div class="field">

                <label for="winnerImage">
                    Image publique
                </label>

                <input
                    id="winnerImage"
                    name="winner_image"
                    type="url"
                    placeholder="https://..."
                >

            </div>

            <div class="modal-actions">

                <button
                    type="button"
                    class="button"
                    onclick="closeWinnerModal()"
                >
                    Annuler
                </button>

                <button
                    type="submit"
                    class="button gold"
                >
                    Confirmer le gagnant
                </button>

            </div>

        </form>

    </div>

</div>

<script>

function openWinnerModal(
    id,
    company,
    website
) {
    document
        .getElementById('winnerParticipantId')
        .value = id;

    document
        .getElementById('winnerCompany')
        .value = company;

    document
        .getElementById('winnerDescription')
        .value =
            'Entreprise sélectionnée dans le cadre du Grand + de Vitrine+.';

    document
        .getElementById('winnerImage')
        .value = '';

    document
        .getElementById('winnerModal')
        .classList
        .add('open');
}

function closeWinnerModal() {
    document
        .getElementById('winnerModal')
        .classList
        .remove('open');
}

document
    .getElementById('winnerModal')
    .addEventListener(
        'click',
        function(event) {
            if (
                event.target === this
            ) {
                closeWinnerModal();
            }
        }
    );

document.addEventListener(
    'keydown',
    function(event) {
        if (
            event.key === 'Escape'
        ) {
            closeWinnerModal();
        }
    }
);

</script>

</body>
</html>