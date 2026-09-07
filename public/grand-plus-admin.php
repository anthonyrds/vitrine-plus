<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE GRAND + — ADMINISTRATION
|--------------------------------------------------------------------------
|
| Administration privée des participations.
|
| Fonctions :
| - consulter les participations du mois ;
| - désigner un gagnant ;
| - clôturer automatiquement les autres participations ;
| - publier le gagnant ;
| - envoyer les e-mails de résultat ;
| - envoyer les offres commerciales aux non-gagnants
|   ayant explicitement accepté le marketing ;
| - empêcher les doubles envois ;
| - gérer un lien de désinscription.
|
|--------------------------------------------------------------------------
*/

session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';

const PARTICIPATIONS_FILE =
    DATA_DIR . '/participations.json';

const WINNER_FILE =
    __DIR__ . '/grand-plus-winner.json';

const CONFIG_FILE =
    __DIR__ . '/vitrine-mail-config.php';

const UNSUBSCRIBE_FILE =
    DATA_DIR . '/unsubscribed.json';

const DEFAULT_TO_EMAIL =
    'vitrineplus@hotmail.com';

const DEFAULT_FROM_NAME =
    'Vitrine+';

const DEFAULT_SITE_URL =
    'https://vitrineplus.fr';


/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| ERREUR
|--------------------------------------------------------------------------
*/

function respond_error(
    string $message,
    int $status = 500
): void {
    http_response_code($status);

    exit(
        h($message)
    );
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION VITRINE+
|--------------------------------------------------------------------------
*/

function load_config(): array
{
    if (!file_exists(CONFIG_FILE)) {
        respond_error(
            'Configuration Vitrine+ introuvable.'
        );
    }

    $config = require CONFIG_FILE;

    if (!is_array($config)) {
        respond_error(
            'Configuration Vitrine+ invalide.'
        );
    }

    return $config;
}


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

function require_auth(
    array $config
): void {

    $username =
        trim(
            (string) (
                $config['grand_plus_admin_user']
                ?? ''
            )
        );

    $password =
        (string) (
            $config['grand_plus_admin_password']
            ?? ''
        );

    if (
        $username === '' ||
        $password === ''
    ) {
        respond_error(
            'Les identifiants administrateur du Grand + ne sont pas configurés.',
            500
        );
    }

    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        !isset($_SERVER['PHP_AUTH_PW'])
    ) {
        header(
            'WWW-Authenticate: Basic realm="Le Grand + — Administration"'
        );

        http_response_code(401);

        exit(
            'Authentification requise.'
        );
    }

    $user =
        (string) $_SERVER['PHP_AUTH_USER'];

    $providedPassword =
        (string) $_SERVER['PHP_AUTH_PW'];

    if (
        !hash_equals(
            $username,
            $user
        ) ||
        !hash_equals(
            $password,
            $providedPassword
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Le Grand + — Administration"'
        );

        http_response_code(401);

        exit(
            'Identifiants incorrects.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DOSSIER DE DONNÉES
|--------------------------------------------------------------------------
*/

function ensure_data_dir(): void
{
    if (is_dir(DATA_DIR)) {
        return;
    }

    if (
        !mkdir(
            DATA_DIR,
            0750,
            true
        ) &&
        !is_dir(DATA_DIR)
    ) {
        respond_error(
            'Impossible de préparer le dossier de données.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| JSON
|--------------------------------------------------------------------------
*/

function read_json_file(
    string $file,
    mixed $default
): mixed {

    if (!file_exists($file)) {
        return $default;
    }

    $content =
        @file_get_contents($file);

    if (
        $content === false ||
        trim($content) === ''
    ) {
        return $default;
    }

    $decoded =
        json_decode(
            $content,
            true
        );

    if (
        json_last_error() !==
        JSON_ERROR_NONE
    ) {
        return $default;
    }

    return $decoded;
}


function write_json_file(
    string $file,
    mixed $data
): bool {

    $json =
        json_encode(
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


/*
|--------------------------------------------------------------------------
| MOIS
|--------------------------------------------------------------------------
*/

function current_month_key(): string
{
    return date('Y-m');
}


function month_label(
    string $monthKey
): string {

    $parts =
        explode(
            '-',
            $monthKey
        );

    $month =
        isset($parts[1])
            ? (int) $parts[1]
            : (int) date('n');

    $year =
        $parts[0]
        ?? date('Y');

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

    return
        (
            $months[$month]
            ?? 'Mois'
        ) .
        ' ' .
        $year;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (
        empty(
            $_SESSION['grand_plus_csrf']
        )
    ) {
        $_SESSION['grand_plus_csrf'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return
        (string) $_SESSION['grand_plus_csrf'];
}


function verify_csrf(): void
{
    $sessionToken =
        (string) (
            $_SESSION['grand_plus_csrf']
            ?? ''
        );

    $postedToken =
        (string) (
            $_POST['csrf']
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
        respond_error(
            'Jeton de sécurité invalide. Rechargez la page et réessayez.',
            403
        );
    }
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

        $line =
            fgets(
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

    $response =
        smtp_read($socket);

    $code =
        (int) substr(
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
        $password === '' ||
        $fromEmail === ''
    ) {
        return false;
    }

    $remote =
        $port === 465
            ? 'ssl://' . $host
            : $host;

    $socket =
        @fsockopen(
            $remote,
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

        $hostname =
            $_SERVER['SERVER_NAME']
            ?? 'vitrineplus.fr';

        smtp_command(
            $socket,
            'EHLO ' . $hostname,
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
            'MAIL FROM:<' . $fromEmail . '>',
            [250]
        );

        smtp_command(
            $socket,
            'RCPT TO:<' . $to . '>',
            [250, 251]
        );

        smtp_command(
            $socket,
            'DATA',
            [354]
        );

        $safeSubject =
            preg_replace(
                "/[\r\n]+/",
                ' ',
                $subject
            ) ?? $subject;

        $safeFromName =
            preg_replace(
                "/[\r\n]+/",
                ' ',
                $fromName
            ) ?? $fromName;

        $headers =
            'From: ' .
            $safeFromName .
            ' <' .
            $fromEmail .
            ">\r\n" .

            (
                $replyTo
                    ? 'Reply-To: ' .
                      $replyTo .
                      "\r\n"
                    : ''
            ) .

            'To: <' .
            $to .
            ">\r\n" .

            'Subject: ' .
            '=?UTF-8?B?' .
            base64_encode(
                $safeSubject
            ) .
            "?=\r\n" .

            "MIME-Version: 1.0\r\n" .

            "Content-Type: text/plain; charset=UTF-8\r\n" .

            "Content-Transfer-Encoding: 8bit\r\n\r\n";

        $body =
            preg_replace(
                '/^\./m',
                '..',
                $body
            ) ?? $body;

        fwrite(
            $socket,
            $headers .
            $body .
            "\r\n.\r\n"
        );

        smtp_expect(
            $socket,
            [250]
        );

        smtp_command(
            $socket,
            'QUIT',
            [221, 250]
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
| PARTICIPANT
|--------------------------------------------------------------------------
*/

function participant_index(
    array $participations,
    string $id
): int {

    foreach (
        $participations
        as $index => $participant
    ) {

        if (
            is_array($participant) &&
            (string) (
                $participant['id']
                ?? ''
            ) === $id
        ) {
            return (int) $index;
        }
    }

    return -1;
}


/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

function safe_website(
    string $website
): string {

    $website =
        trim($website);

    if ($website === '') {
        return '';
    }

    if (
        !preg_match(
            '#^https?://#i',
            $website
        )
    ) {
        $website =
            'https://' .
            $website;
    }

    return
        filter_var(
            $website,
            FILTER_VALIDATE_URL
        )
        ? $website
        : '';
}


/*
|--------------------------------------------------------------------------
| TOKEN DÉSINSCRIPTION
|--------------------------------------------------------------------------
*/

function unsubscribe_token(
    string $email,
    array $config
): string {

    $secret =
        trim(
            (string) (
                $config['grand_plus_unsubscribe_secret']
                ?? ''
            )
        );

    if ($secret === '') {
        return '';
    }

    return hash_hmac(
        'sha256',
        strtolower(
            trim($email)
        ),
        $secret
    );
}


/*
|--------------------------------------------------------------------------
| E-MAIL GAGNANT
|--------------------------------------------------------------------------
*/

function send_winner_email(
    array $config,
    array $participant
): bool {

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

    $email =
        (string) (
            $participant['email']
            ?? ''
        );

    $month =
        (string) (
            $participant['month_label']
            ??
            month_label(
                (string) (
                    $participant['month_key']
                    ??
                    current_month_key()
                )
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

    $subject =
        'Vous êtes l’entreprise sélectionnée du Grand + — Vitrine+';

    $body =
        "Bonjour " .
        $name .
        ",\n\n" .

        "Bonne nouvelle : votre entreprise « " .
        $company .
        " » a été sélectionnée pour le Grand + de " .
        $month .
        ".\n\n" .

        "Vitrine+ va maintenant prendre contact avec vous afin de définir les modalités du projet et préparer la refonte offerte.\n\n" .

        "Votre identifiant de participation : " .
        (string) (
            $participant['id']
            ?? ''
        ) .
        "\n\n" .

        "Vous n’avez aucune commande à effectuer pour bénéficier de cette sélection.\n\n" .

        "À très bientôt,\n\n" .

        "Vitrine+\n" .
        "Votre entreprise. En mieux.\n" .
        DEFAULT_SITE_URL .
        "/le-grand-plus\n";

    return smtp_send_mail(
        $config,
        $email,
        $subject,
        $body
    );
}


/*
|--------------------------------------------------------------------------
| E-MAIL NON-GAGNANT
|--------------------------------------------------------------------------
*/

function send_non_winner_email(
    array $config,
    array $participant,
    bool $includeOffer
): bool {

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

    $email =
        (string) (
            $participant['email']
            ?? ''
        );

    $month =
        (string) (
            $participant['month_label']
            ??
            month_label(
                (string) (
                    $participant['month_key']
                    ??
                    current_month_key()
                )
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

    $subject =
        'Résultat du Grand + — Vitrine+';

    $body =
        "Bonjour " .
        $name .
        ",\n\n" .

        "Merci d’avoir participé au Grand + de " .
        $month .
        " pour « " .
        $company .
        " ».\n\n" .

        "Votre entreprise n’a malheureusement pas été sélectionnée ce mois-ci.\n\n";

    /*
     * OFFRE COMMERCIALE UNIQUEMENT
     * SI CONSENTEMENT MARKETING.
     */

    if ($includeOffer) {

        $unsubscribeUrl =
            DEFAULT_SITE_URL .
            '/grand-plus-unsubscribe.php?email=' .
            rawurlencode($email) .
            '&token=' .
            rawurlencode(
                unsubscribe_token(
                    $email,
                    $config
                )
            );

        $body .=

            "Mais nous ne voulions pas vous laisser repartir les mains vides.\n\n" .

            "Comme vous avez accepté de recevoir les actualités et offres de Vitrine+, nous vous invitons à découvrir nos solutions pour améliorer votre présence en ligne :\n\n" .

            DEFAULT_SITE_URL .
            "/creation-site-internet\n" .

            DEFAULT_SITE_URL .
            "/audit\n" .

            DEFAULT_SITE_URL .
            "/rendez-vous\n\n" .

            "Vous pouvez retirer votre consentement à tout moment ici :\n" .

            $unsubscribeUrl .
            "\n\n";
    }

    $body .=

        "Merci encore pour votre participation.\n\n" .

        "Vitrine+\n" .

        "Votre entreprise. En mieux.\n" .

        DEFAULT_SITE_URL .
        "/le-grand-plus\n";

    return smtp_send_mail(
        $config,
        $email,
        $subject,
        $body
    );
}


/*
|--------------------------------------------------------------------------
| RELANCE COMMERCIALE
|--------------------------------------------------------------------------
*/

function send_followup_offer(
    array $config,
    array $participant
): bool {

    $email =
        (string) (
            $participant['email']
            ?? ''
        );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

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

    $unsubscribeUrl =
        DEFAULT_SITE_URL .
        '/grand-plus-unsubscribe.php?email=' .
        rawurlencode($email) .
        '&token=' .
        rawurlencode(
            unsubscribe_token(
                $email,
                $config
            )
        );

    $subject =
        'Et si votre entreprise passait quand même au niveau supérieur ? — Vitrine+';

    $body =

        "Bonjour " .
        $name .
        ",\n\n" .

        "Votre entreprise « " .
        $company .
        " » n’a pas été sélectionnée lors du dernier Grand +.\n\n" .

        "Mais votre participation nous a permis de découvrir votre activité, et nous pensons que votre présence en ligne mérite peut-être d’aller plus loin.\n\n" .

        "Si vous souhaitez échanger avec Vitrine+ sur votre site, votre visibilité ou votre conversion, vous pouvez :\n\n" .

        "Faire analyser votre site gratuitement :\n" .
        DEFAULT_SITE_URL .
        "/audit\n\n" .

        "Découvrir nos solutions :\n" .
        DEFAULT_SITE_URL .
        "/creation-site-internet\n\n" .

        "Prendre un rendez-vous :\n" .
        DEFAULT_SITE_URL .
        "/rendez-vous\n\n" .

        "Cet e-mail vous est envoyé parce que vous avez accepté de recevoir occasionnellement les actualités et offres de Vitrine+.\n\n" .

        "Vous pouvez retirer votre consentement à tout moment :\n" .
        $unsubscribeUrl .
        "\n\n" .

        "Vitrine+\n" .
        "Votre entreprise. En mieux.\n" .
        DEFAULT_SITE_URL .
        "\n";

    return smtp_send_mail(
        $config,
        $email,
        $subject,
        $body
    );
}


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

$config =
    load_config();

require_auth(
    $config
);

ensure_data_dir();

$participations =
    read_json_file(
        PARTICIPATIONS_FILE,
        []
    );

if (!is_array($participations)) {
    $participations = [];
}

$actionMessage = '';
$actionError = '';

$currentMonth =
    current_month_key();


/*
|--------------------------------------------------------------------------
| ACTIONS ADMIN
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {

    verify_csrf();

    $action =
        (string) (
            $_POST['action']
            ?? ''
        );

    $id =
        trim(
            (string) (
                $_POST['id']
                ?? ''
            )
        );


    /*
     * ---------------------------------------------------------------
     * DÉSIGNER UN GAGNANT
     * ---------------------------------------------------------------
     */

    if (
        $action ===
        'select_winner'
    ) {

        $index =
            participant_index(
                $participations,
                $id
            );

        if ($index < 0) {

            $actionError =
                'Participant introuvable.';

        } else {

            $selected =
                $participations[$index];

            if (
                ($selected['month_key'] ?? '')
                !==
                $currentMonth
            ) {

                $actionError =
                    'Le participant sélectionné n’appartient pas au mois courant.';

            } else {

                $description =
                    trim(
                        (string) (
                            $_POST['description']
                            ?? ''
                        )
                    );

                $image =
                    trim(
                        (string) (
                            $_POST['image']
                            ?? ''
                        )
                    );

                foreach (
                    $participations
                    as $participantIndex =>
                    $participant
                ) {

                    if (
                        !is_array(
                            $participant
                        )
                    ) {
                        continue;
                    }

                    if (
                        ($participant['month_key'] ?? '')
                        !==
                        $currentMonth
                    ) {
                        continue;
                    }

                    if (
                        (string) (
                            $participant['id']
                            ?? ''
                        )
                        ===
                        $id
                    ) {

                        $participations[
                            $participantIndex
                        ]['status'] =
                            'winner';

                        $participations[
                            $participantIndex
                        ]['winner_selected_at'] =
                            date('c');

                    } elseif (
                        ($participant['status'] ?? 'pending')
                        ===
                        'pending'
                    ) {

                        $participations[
                            $participantIndex
                        ]['status'] =
                            'not_winner';

                        $participations[
                            $participantIndex
                        ]['closed_at'] =
                            date('c');
                    }
                }


                /*
                 * PUBLICATION DU GAGNANT
                 */

                $winnerData = [

                    'hasWinner' =>
                        true,

                    'month' =>
                        (string) (
                            $selected['month_label']
                            ??
                            month_label(
                                $currentMonth
                            )
                        ),

                    'company' =>
                        (string) (
                            $selected['company']
                            ?? ''
                        ),

                    'description' =>
                        $description,

                    'website' =>
                        safe_website(
                            (string) (
                                $selected['website']
                                ?? ''
                            )
                        ),

                    'image' =>
                        $image,
                ];


                $participationsSaved =
                    write_json_file(
                        PARTICIPATIONS_FILE,
                        $participations
                    );

                $winnerSaved =
                    write_json_file(
                        WINNER_FILE,
                        $winnerData
                    );


                if (
                    !$participationsSaved
                ) {

                    $actionError =
                        'Impossible d’enregistrer les statuts des participants.';

                } elseif (
                    !$winnerSaved
                ) {

                    $actionError =
                        'Les statuts sont enregistrés, mais le gagnant n’a pas pu être publié.';

                } else {

                    $actionMessage =
                        'Gagnant désigné et mois clôturé. Le gagnant est maintenant publié sur le site.';
                }
            }
        }
    }


    /*
     * ---------------------------------------------------------------
     * ANNULER LA SÉLECTION
     * ---------------------------------------------------------------
     */

    elseif (
        $action ===
        'reset_winner'
    ) {

        foreach (
            $participations
            as $participantIndex =>
            $participant
        ) {

            if (
                !is_array(
                    $participant
                )
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '')
                !==
                $currentMonth
            ) {
                continue;
            }


            if (
                ($participant['status'] ?? '')
                ===
                'winner'
            ) {

                $participations[
                    $participantIndex
                ]['status'] =
                    'pending';

                unset(
                    $participations[
                        $participantIndex
                    ]['winner_selected_at']
                );
            }


            if (
                ($participant['status'] ?? '')
                ===
                'not_winner'
            ) {

                $participations[
                    $participantIndex
                ]['status'] =
                    'pending';

                unset(
                    $participations[
                        $participantIndex
                    ]['closed_at']
                );
            }
        }


        $winnerData = [

            'hasWinner' =>
                false,

            'month' =>
                month_label(
                    $currentMonth
                ),

            'company' =>
                '',

            'description' =>
                '',

            'website' =>
                '',

            'image' =>
                '',
        ];


        if (
            write_json_file(
                PARTICIPATIONS_FILE,
                $participations
            ) &&
            write_json_file(
                WINNER_FILE,
                $winnerData
            )
        ) {

            $actionMessage =
                'Sélection annulée pour le mois courant.';

        } else {

            $actionError =
                'Impossible d’annuler la sélection.';
        }
    }


    /*
     * ---------------------------------------------------------------
     * ENVOYER LES RÉSULTATS
     * ---------------------------------------------------------------
     */

    elseif (
        $action ===
        'send_results'
    ) {

        $sent = 0;
        $failed = 0;


        foreach (
            $participations
            as $participantIndex =>
            $participant
        ) {

            if (
                !is_array(
                    $participant
                )
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '')
                !==
                $currentMonth
            ) {
                continue;
            }

            $status =
                (string) (
                    $participant['status']
                    ?? 'pending'
                );


            /*
             * GAGNANT
             */

            if (
                $status ===
                'winner' &&
                empty(
                    $participant[
                        'result_email_sent_at'
                    ]
                )
            ) {

                $ok =
                    send_winner_email(
                        $config,
                        $participant
                    );

                if ($ok) {

                    $participations[
                        $participantIndex
                    ]['result_email_sent_at'] =
                        date('c');

                    $participations[
                        $participantIndex
                    ]['result_email_type'] =
                        'winner';

                    $sent++;

                } else {

                    $failed++;
                }
            }


            /*
             * NON-GAGNANT
             */

            if (
                $status ===
                'not_winner' &&
                empty(
                    $participant[
                        'result_email_sent_at'
                    ]
                )
            ) {

                $includeOffer =
                    !empty(
                        $participant[
                            'marketing_consent'
                        ]
                    );

                $ok =
                    send_non_winner_email(
                        $config,
                        $participant,
                        $includeOffer
                    );

                if ($ok) {

                    $participations[
                        $participantIndex
                    ]['result_email_sent_at'] =
                        date('c');

                    $participations[
                        $participantIndex
                    ]['result_email_type'] =
                        $includeOffer
                            ? 'not_winner_with_offer'
                            : 'not_winner_transactional';

                    $sent++;

                } else {

                    $failed++;
                }
            }
        }


        write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        );


        $actionMessage =
            $sent .
            ' e-mail(s) de résultat envoyé(s).';


        if ($failed > 0) {

            $actionMessage .=
                ' ' .
                $failed .
                ' échec(s).';
        }
    }


    /*
     * ---------------------------------------------------------------
     * ENVOYER UNE OFFRE À UN PARTICIPANT
     * ---------------------------------------------------------------
     */

    elseif (
        $action ===
        'send_offer'
    ) {

        $index =
            participant_index(
                $participations,
                $id
            );

        if (
            $index < 0 ||
            !is_array(
                $participations[$index]
            )
        ) {

            $actionError =
                'Participant introuvable.';

        } else {

            $participant =
                $participations[$index];


            if (
                ($participant['month_key'] ?? '')
                !==
                $currentMonth
            ) {

                $actionError =
                    'Ce participant n’appartient pas au mois courant.';

            } elseif (
                ($participant['status'] ?? '')
                !==
                'not_winner'
            ) {

                $actionError =
                    'Une offre de relance est réservée aux non-gagnants.';

            } elseif (
                empty(
                    $participant[
                        'marketing_consent'
                    ]
                )
            ) {

                $actionError =
                    'Ce participant n’a pas donné son consentement marketing. Aucun e-mail commercial ne peut être envoyé.';

            } elseif (
                !empty(
                    $participant[
                        'offer_email_sent_at'
                    ]
                )
            ) {

                $actionError =
                    'Une offre a déjà été envoyée à ce participant.';

            } else {

                $ok =
                    send_followup_offer(
                        $config,
                        $participant
                    );

                if ($ok) {

                    $participations[
                        $index
                    ]['offer_email_sent_at'] =
                        date('c');

                    write_json_file(
                        PARTICIPATIONS_FILE,
                        $participations
                    );

                    $actionMessage =
                        'Offre de relance envoyée à ' .
                        (string) (
                            $participant['email']
                            ?? ''
                        ) .
                        '.';

                } else {

                    $actionError =
                        'L’envoi de l’offre a échoué.';
                }
            }
        }
    }


    /*
     * ---------------------------------------------------------------
     * ENVOYER LES OFFRES À TOUS
     * ---------------------------------------------------------------
     */

    elseif (
        $action ===
        'send_offer_all'
    ) {

        $sent = 0;
        $skipped = 0;
        $failed = 0;


        foreach (
            $participations
            as $participantIndex =>
            $participant
        ) {

            if (
                !is_array(
                    $participant
                )
            ) {
                continue;
            }

            if (
                ($participant['month_key'] ?? '')
                !==
                $currentMonth
            ) {
                continue;
            }

            if (
                ($participant['status'] ?? '')
                !==
                'not_winner'
            ) {
                continue;
            }


            /*
             * PAS DE CONSENTEMENT
             */

            if (
                empty(
                    $participant[
                        'marketing_consent'
                    ]
                )
            ) {

                $skipped++;

                continue;
            }


            /*
             * DÉJÀ ENVOYÉ
             */

            if (
                !empty(
                    $participant[
                        'offer_email_sent_at'
                    ]
                )
            ) {

                $skipped++;

                continue;
            }


            $ok =
                send_followup_offer(
                    $config,
                    $participant
                );


            if ($ok) {

                $participations[
                    $participantIndex
                ]['offer_email_sent_at'] =
                    date('c');

                $sent++;

            } else {

                $failed++;
            }
        }


        write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        );


        $actionMessage =
            $sent .
            ' offre(s) envoyée(s).';


        if ($skipped > 0) {

            $actionMessage .=
                ' ' .
                $skipped .
                ' participant(s) ignoré(s).';
        }


        if ($failed > 0) {

            $actionMessage .=
                ' ' .
                $failed .
                ' échec(s).';
        }
    }
}


/*
|--------------------------------------------------------------------------
| PARTICIPANTS DU MOIS
|--------------------------------------------------------------------------
*/

$currentParticipants =
    array_values(
        array_filter(
            $participations,
            static function ($item)
            use ($currentMonth): bool {

                return
                    is_array($item) &&
                    (
                        $item['month_key']
                        ?? ''
                    ) ===
                    $currentMonth;
            }
        )
    );


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
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$total = 0;
$pending = 0;
$winnerCount = 0;
$notWinner = 0;
$marketing = 0;
$resultsSent = 0;
$offersSent = 0;

$selectedWinner = null;


foreach (
    $currentParticipants
    as $participant
) {

    $total++;

    $status =
        (string) (
            $participant['status']
            ?? 'pending'
        );


    if (
        $status ===
        'pending'
    ) {

        $pending++;

    } elseif (
        $status ===
        'winner'
    ) {

        $winnerCount++;

        $selectedWinner =
            $participant;

    } elseif (
        $status ===
        'not_winner'
    ) {

        $notWinner++;
    }


    if (
        !empty(
            $participant[
                'marketing_consent'
            ]
        )
    ) {

        $marketing++;
    }


    if (
        !empty(
            $participant[
                'result_email_sent_at'
            ]
        )
    ) {

        $resultsSent++;
    }


    if (
        !empty(
            $participant[
                'offer_email_sent_at'
            ]
        )
    ) {

        $offersSent++;
    }
}


$token =
    csrf_token();

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
    width:
        min(
            1500px,
            calc(100% - 32px)
        );

    margin: 0 auto;

    padding:
        42px 0 80px;
}

.top {
    display: flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap: 24px;
}

.eyebrow {
    color: #c8a45d;

    font-size: 11px;

    font-weight: 800;

    letter-spacing:
        .24em;

    text-transform:
        uppercase;
}

h1 {
    margin:
        12px 0 0;

    font-size:
        clamp(
            44px,
            7vw,
            88px
        );

    line-height:
        .88;

    letter-spacing:
        -.06em;
}

.month {
    margin-top:
        16px;

    color:
        rgba(
            255,
            255,
            255,
            .45
        );
}

.actions {
    display:
        flex;

    flex-wrap:
        wrap;

    gap: 10px;

    justify-content:
        flex-end;
}

button,
.button {
    border: 0;

    border-radius:
        14px;

    padding:
        12px 15px;

    background:
        #fff;

    color:
        #080808;

    font: inherit;

    font-size:
        12px;

    font-weight:
        800;

    cursor:
        pointer;

    text-decoration:
        none;
}

button:hover,
.button:hover {
    background:
        #c8a45d;
}

.button.dark,
button.dark {
    background:
        #161616;

    color:
        #fff;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .1
        );
}

.button.danger,
button.danger {
    background:
        #2a1515;

    color:
        #ffb1b1;
}

.notice {
    margin-top:
        28px;

    padding:
        15px 18px;

    border-radius:
        16px;

    background:
        rgba(
            200,
            164,
            93,
            .12
        );

    border:
        1px solid
        rgba(
            200,
            164,
            93,
            .25
        );

    color:
        #e5d2a5;
}

.notice.error {
    background:
        rgba(
            255,
            80,
            80,
            .08
        );

    border-color:
        rgba(
            255,
            80,
            80,
            .2
        );

    color:
        #ffb1b1;
}

.stats {
    display:
        grid;

    grid-template-columns:
        repeat(
            6,
            1fr
        );

    gap: 10px;

    margin-top:
        42px;
}

.stat {
    padding:
        20px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .08
        );

    border-radius:
        20px;

    background:
        #111;
}

.stat-label {
    color:
        rgba(
            255,
            255,
            255,
            .35
        );

    font-size:
        10px;

    font-weight:
        800;

    letter-spacing:
        .15em;

    text-transform:
        uppercase;
}

.stat-value {
    margin-top:
        9px;

    font-size:
        32px;

    font-weight:
        800;

    letter-spacing:
        -.04em;
}

.card {
    margin-top:
        34px;

    padding:
        24px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .08
        );

    border-radius:
        22px;

    background:
        #111;
}

.card h2 {
    margin:
        0;

    font-size:
        20px;

    letter-spacing:
        -.03em;
}

.card p {
    color:
        rgba(
            255,
            255,
            255,
            .45
        );

    line-height:
        1.6;

    font-size:
        13px;
}

.table-wrap {
    margin-top:
        34px;

    overflow-x:
        auto;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .08
        );

    border-radius:
        22px;

    background:
        #111;
}

table {
    width:
        100%;

    min-width:
        1250px;

    border-collapse:
        collapse;
}

th,
td {
    padding:
        16px 17px;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            .07
        );

    text-align:
        left;

    vertical-align:
        top;
}

th {
    color:
        rgba(
            255,
            255,
            255,
            .3
        );

    font-size:
        9px;

    letter-spacing:
        .15em;

    text-transform:
        uppercase;
}

td {
    font-size:
        13px;
}

.company {
    font-weight:
        800;
}

.muted {
    margin-top:
        4px;

    color:
        rgba(
            255,
            255,
            255,
            .38
        );

    font-size:
        11px;
}

.problem {
    max-width:
        270px;

    color:
        rgba(
            255,
            255,
            255,
            .55
        );

    line-height:
        1.45;
}

.badge {
    display:
        inline-flex;

    border-radius:
        999px;

    padding:
        6px 9px;

    font-size:
        9px;

    font-weight:
        800;

    letter-spacing:
        .08em;

    text-transform:
        uppercase;
}

.pending {
    background:
        rgba(
            255,
            255,
            255,
            .08
        );

    color:
        #aaa;
}

.winner {
    background:
        rgba(
            200,
            164,
            93,
            .16
        );

    color:
        #c8a45d;
}

.not-winner {
    background:
        rgba(
            255,
            255,
            255,
            .05
        );

    color:
        #777;
}

.yes {
    color:
        #c8a45d;

    font-weight:
        800;
}

.no {
    color:
        #666;
}

.row-actions {
    display:
        flex;

    flex-wrap:
        wrap;

    gap: 7px;
}

.small {
    padding:
        8px 10px;

    font-size:
        10px;
}

.winner-form {
    margin-top:
        9px;

    display:
        grid;

    gap: 7px;

    min-width:
        250px;
}

input,
textarea {
    width:
        100%;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .1
        );

    border-radius:
        13px;

    background:
        #080808;

    color:
        #fff;

    padding:
        12px 13px;

    font:
        inherit;

    font-size:
        13px;

    outline:
        none;
}

textarea {
    min-height:
        95px;

    resize:
        vertical;
}

input:focus,
textarea:focus {
    border-color:
        #c8a45d;
}

.empty {
    padding:
        55px;

    text-align:
        center;

    color:
        rgba(
            255,
            255,
            255,
            .4
        );
}

@media (max-width: 1100px) {

    .stats {
        grid-template-columns:
            repeat(
                3,
                1fr
            );
    }
}

@media (max-width: 700px) {

    .top {
        display:
            block;
    }

    .actions {
        justify-content:
            flex-start;

        margin-top:
            22px;
    }

    .stats {
        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }

    .container {
        width:
            min(
                100% - 22px,
                1500px
            );

        padding-top:
            28px;
    }
}

</style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="top">

        <div>

            <div class="eyebrow">
                Vitrine+ — Administration privée
            </div>

            <h1>
                Le Grand +
            </h1>

            <div class="month">
                <?= h(
                    month_label(
                        $currentMonth
                    )
                ) ?>
            </div>

        </div>


        <div class="actions">

            <a
                class="button dark"
                href="/le-grand-plus"
                target="_blank"
                rel="noreferrer"
            >
                Voir le Grand +
            </a>


            <?php if ($winnerCount > 0): ?>

                <form
                    method="post"
                    onsubmit="return confirm('Annuler la sélection du gagnant et rouvrir le mois ?');"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= h($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="reset_winner"
                    >

                    <button
                        class="danger"
                        type="submit"
                    >
                        Annuler la sélection
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>


    <!-- NOTIFICATIONS -->

    <?php if ($actionMessage !== ''): ?>

        <div class="notice">

            <?= h(
                $actionMessage
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($actionError !== ''): ?>

        <div class="notice error">

            <?= h(
                $actionError
            ) ?>

        </div>

    <?php endif; ?>


    <!-- STATS -->

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


        <div class="stat">

            <div class="stat-label">
                Offres envoyées
            </div>

            <div class="stat-value">
                <?= $offersSent ?>
            </div>

        </div>

    </div>


    <!-- ACTIONS GLOBALES -->

    <div class="card">

        <h2>
            Gestion du mois
        </h2>

        <p>
            Désigne d’abord le gagnant depuis le tableau.
            Les autres participants seront automatiquement
            marqués comme non gagnants.
            Les e-mails de résultat sont indépendants des
            relances commerciales.
        </p>


        <div
            class="actions"
            style="justify-content:flex-start;margin-top:15px"
        >


            <form
                method="post"
                onsubmit="return confirm('Envoyer les résultats à tous les participants du mois ? Les e-mails déjà envoyés seront ignorés.');"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= h($token) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="send_results"
                >

                <button
                    type="submit"
                >
                    Envoyer les résultats
                </button>

            </form>


            <form
                method="post"
                onsubmit="return confirm('Envoyer les offres commerciales à tous les non-gagnants ayant accepté le marketing ? Les offres déjà envoyées seront ignorées.');"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= h($token) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="send_offer_all"
                >

                <button
                    class="dark"
                    type="submit"
                >
                    Envoyer les offres autorisées
                </button>

            </form>

        </div>

    </div>


    <!-- TABLEAU -->

    <div class="table-wrap">


        <?php if ($total === 0): ?>

            <div class="empty">

                Aucun participant pour
                <?= h(
                    month_label(
                        $currentMonth
                    )
                ) ?>.

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
                            Site
                        </th>

                        <th>
                            Activité / besoin
                        </th>

                        <th>
                            Marketing
                        </th>

                        <th>
                            Statut
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $currentParticipants
                    as $participant
                ): ?>


                    <?php

                    $status =
                        (string) (
                            $participant['status']
                            ?? 'pending'
                        );

                    $id =
                        (string) (
                            $participant['id']
                            ?? ''
                        );

                    $email =
                        (string) (
                            $participant['email']
                            ?? ''
                        );

                    $site =
                        safe_website(
                            (string) (
                                $participant['website']
                                ?? ''
                            )
                        );

                    ?>


                    <tr>


                        <!-- ENTREPRISE -->

                        <td>

                            <div class="company">

                                <?= h(
                                    (string) (
                                        $participant[
                                            'company'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>


                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant[
                                            'sector'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>


                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant[
                                            'created_at'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- CONTACT -->

                        <td>

                            <div>

                                <?= h(
                                    (string) (
                                        $participant[
                                            'name'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>


                            <div class="muted">

                                <?= h(
                                    $email
                                ) ?>

                            </div>


                            <div class="muted">

                                <?= h(
                                    (string) (
                                        $participant[
                                            'phone'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- SITE -->

                        <td>

                            <?php if ($site !== ''): ?>

                                <a
                                    href="<?= h($site) ?>"
                                    target="_blank"
                                    rel="noreferrer"
                                    style="color:#c8a45d"
                                >
                                    Voir le site
                                </a>

                            <?php else: ?>

                                <span class="no">
                                    Aucun site
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- BESOIN -->

                        <td>

                            <div class="problem">

                                <?= h(
                                    (string) (
                                        $participant[
                                            'problem'
                                        ]
                                        ?? ''
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- MARKETING -->

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


                        <!-- STATUT -->

                        <td>


                            <?php if (
                                $status ===
                                'winner'
                            ): ?>

                                <span
                                    class="badge winner"
                                >
                                    Gagnant
                                </span>


                            <?php elseif (
                                $status ===
                                'not_winner'
                            ): ?>

                                <span
                                    class="badge not-winner"
                                >
                                    Non gagnant
                                </span>


                            <?php else: ?>

                                <span
                                    class="badge pending"
                                >
                                    En attente
                                </span>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $participant[
                                        'result_email_sent_at'
                                    ]
                                )
                            ): ?>

                                <div class="muted">
                                    Résultat envoyé
                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $participant[
                                        'offer_email_sent_at'
                                    ]
                                )
                            ): ?>

                                <div class="muted">
                                    Offre envoyée
                                </div>

                            <?php endif; ?>


                        </td>


                        <!-- ACTIONS -->

                        <td>


                            <div class="row-actions">


                                <?php if (
                                    $status ===
                                    'pending'
                                ): ?>


                                    <form
                                        method="post"
                                        class="winner-form"
                                        onsubmit="return confirm('Désigner cette entreprise comme gagnante et clôturer le mois ?');"
                                    >


                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= h($token) ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="select_winner"
                                        >


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= h($id) ?>"
                                        >


                                        <textarea
                                            name="description"
                                            placeholder="Description publique du gagnant (optionnel)"
                                        ></textarea>


                                        <input
                                            type="url"
                                            name="image"
                                            placeholder="URL de l’image du gagnant (optionnel)"
                                        >


                                        <button
                                            class="small"
                                            type="submit"
                                        >
                                            Désigner gagnant
                                        </button>


                                    </form>


                                <?php endif; ?>


                                <?php if (
                                    $status ===
                                    'not_winner' &&
                                    !empty(
                                        $participant[
                                            'marketing_consent'
                                        ]
                                    ) &&
                                    empty(
                                        $participant[
                                            'offer_email_sent_at'
                                        ]
                                    )
                                ): ?>


                                    <form
                                        method="post"
                                        onsubmit="return confirm('Envoyer l’offre commerciale à ce participant ?');"
                                    >


                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= h($token) ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="send_offer"
                                        >


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= h($id) ?>"
                                        >


                                        <button
                                            class="small"
                                            type="submit"
                                        >
                                            Envoyer l’offre
                                        </button>


                                    </form>


                                <?php endif; ?>


                            </div>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php endif; ?>


    </div>


    <!-- INFORMATION -->

    <div class="card">

        <h2>
            Règle des relances
        </h2>

        <p>

            Une relance commerciale est envoyée uniquement
            lorsque
            <strong style="color:#fff">
                marketing_consent = true
            </strong>.

            Les participants n’ayant pas accepté les
            actualités et offres de Vitrine+ ne reçoivent
            aucune offre commerciale.

            Les e-mails de résultat du Grand + sont traités
            séparément.

        </p>

    </div>


</div>

</body>

</html>