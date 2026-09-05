<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE GRAND + — VITRINE+
|--------------------------------------------------------------------------
|
| Endpoint sécurisé de participation.
|
| Reçoit :
| - name
| - company
| - email
| - phone
| - sector
| - website
| - problem
| - consent
| - marketing
| - websiteCheck
|
| Stockage :
| /vitrine-data/grand-plus/
|
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond([
        'success' => false,
        'message' => 'Méthode non autorisée.',
    ], 405);
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const MAX_NAME_LENGTH = 100;
const MAX_COMPANY_LENGTH = 150;
const MAX_EMAIL_LENGTH = 190;
const MAX_PHONE_LENGTH = 30;
const MAX_SECTOR_LENGTH = 100;
const MAX_WEBSITE_LENGTH = 300;
const MAX_PROBLEM_LENGTH = 200;

const RATE_LIMIT_SECONDS = 60;
const RATE_LIMIT_MAX_ATTEMPTS = 3;

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';
const PARTICIPATIONS_FILE = DATA_DIR . '/participations.json';
const RATE_LIMIT_FILE = DATA_DIR . '/rate-limit.json';

const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

const DEFAULT_TO_EMAIL = 'vitrineplus@hotmail.com';
const DEFAULT_FROM_NAME = 'Vitrine+';


/*
|--------------------------------------------------------------------------
| RÉPONSE JSON
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| UTILITAIRES
|--------------------------------------------------------------------------
*/

function clean_string(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    $value = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $value
    ) ?? '';

    return trim($value);
}


function normalize_text(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $value = function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return $value;
}


function normalize_company(string $value): string
{
    $value = normalize_text($value);

    /*
     * On retire une partie de la ponctuation afin que :
     *
     * "Maison Dupont"
     * "MAISON DUPONT"
     * "Maison  Dupont"
     *
     * soient considérés comme la même entreprise.
     */

    $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}


function normalize_email(string $email): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower(trim($email), 'UTF-8')
        : strtolower(trim($email));
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


function generate_participation_id(): string
{
    return 'GP-' .
        date('Ymd-His') .
        '-' .
        strtoupper(bin2hex(random_bytes(4)));
}


function get_client_ip(): string
{
    /*
     * On utilise REMOTE_ADDR uniquement.
     *
     * Les headers X-Forwarded-For peuvent être falsifiés lorsqu'ils
     * ne sont pas fournis par un proxy de confiance.
     */

    return trim(
        (string) (
            $_SERVER['REMOTE_ADDR']
            ?? 'unknown'
        )
    );
}


function hash_ip(string $ip): string
{
    return hash(
        'sha256',
        $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
}


/*
|--------------------------------------------------------------------------
| STOCKAGE
|--------------------------------------------------------------------------
*/

function ensure_data_directory(): void
{
    if (is_dir(DATA_DIR)) {
        return;
    }

    if (!mkdir(DATA_DIR, 0750, true) && !is_dir(DATA_DIR)) {
        respond([
            'success' => false,
            'message' => 'Impossible de préparer l’enregistrement de la participation.',
        ], 500);
    }
}


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


/*
|--------------------------------------------------------------------------
| ANTI-SPAM
|--------------------------------------------------------------------------
*/

function check_rate_limit(string $ip): void
{
    ensure_data_directory();

    $now = time();

    $data = read_json_file(
        RATE_LIMIT_FILE,
        []
    );

    if (!is_array($data)) {
        $data = [];
    }

    /*
     * On nettoie les anciennes entrées.
     */

    foreach ($data as $hash => $entry) {
        if (
            !is_array($entry) ||
            !isset($entry['attempts'], $entry['last'])
        ) {
            unset($data[$hash]);
            continue;
        }

        if (
            ($now - (int) $entry['last']) >
            RATE_LIMIT_SECONDS
        ) {
            unset($data[$hash]);
        }
    }

    $ipHash = hash_ip($ip);

    if (!isset($data[$ipHash])) {
        $data[$ipHash] = [
            'attempts' => 1,
            'last' => $now,
        ];

        write_json_file(
            RATE_LIMIT_FILE,
            $data
        );

        return;
    }

    $attempts = (int) $data[$ipHash]['attempts'];
    $last = (int) $data[$ipHash]['last'];

    if (($now - $last) > RATE_LIMIT_SECONDS) {
        $data[$ipHash] = [
            'attempts' => 1,
            'last' => $now,
        ];

        write_json_file(
            RATE_LIMIT_FILE,
            $data
        );

        return;
    }

    if ($attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
        respond([
            'success' => false,
            'message' => 'Trop de tentatives. Merci de patienter quelques instants avant de réessayer.',
        ], 429);
    }

    $data[$ipHash]['attempts'] = $attempts + 1;
    $data[$ipHash]['last'] = $now;

    write_json_file(
        RATE_LIMIT_FILE,
        $data
    );
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION SMTP
|--------------------------------------------------------------------------
*/

function load_mail_config(): array
{
    if (!file_exists(CONFIG_FILE)) {
        respond([
            'success' => false,
            'message' => 'La configuration e-mail de Vitrine+ est introuvable.',
        ], 500);
    }

    $config = require CONFIG_FILE;

    if (!is_array($config)) {
        respond([
            'success' => false,
            'message' => 'La configuration e-mail est invalide.',
        ], 500);
    }

    $required = [
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
    ];

    foreach ($required as $key) {
        if (
            !isset($config[$key]) ||
            trim((string) $config[$key]) === ''
        ) {
            respond([
                'success' => false,
                'message' => 'La configuration e-mail est incomplète.',
            ], 500);
        }
    }

    return $config;
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
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        /*
         * Une réponse SMTP multi-lignes ressemble à :
         *
         * 250-...
         * 250 ...
         *
         * On s'arrête lorsque le 4e caractère est un espace.
         */

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
    $response = smtp_read($socket);

    $code = (int) substr(
        trim($response),
        0,
        3
    );

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException(
            'Réponse SMTP inattendue : ' . $code
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
    $host = (string) $config['smtp_host'];
    $port = (int) $config['smtp_port'];
    $username = (string) $config['smtp_username'];
    $password = (string) $config['smtp_password'];

    $fromEmail = (string) (
        $config['from_email']
        ?? $username
    );

    $fromName = (string) (
        $config['from_name']
        ?? DEFAULT_FROM_NAME
    );

    $timeout = 15;

    $remote = $port === 465
        ? 'ssl://' . $host
        : $host;

    $socket = @fsockopen(
        $remote,
        $port,
        $errno,
        $errstr,
        $timeout
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout(
        $socket,
        $timeout
    );

    try {
        smtp_expect(
            $socket,
            [220]
        );

        $hostname = $_SERVER['SERVER_NAME']
            ?? 'vitrineplus.fr';

        smtp_command(
            $socket,
            'EHLO ' . $hostname,
            [250]
        );

        if ($port !== 465) {
            /*
             * Pour les ports SMTP non-SSL directs,
             * STARTTLS peut être nécessaire.
             *
             * Notre configuration Vitrine+ utilise normalement
             * le port 465 avec SSL implicite.
             */
        }

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

        $safeSubject = preg_replace(
            "/[\r\n]+/",
            ' ',
            $subject
        );

        $safeFromName = preg_replace(
            "/[\r\n]+/",
            ' ',
            $fromName
        );

        $headers =
            'From: ' .
            $safeFromName .
            ' <' .
            $fromEmail .
            ">\r\n" .

            (
                $replyTo
                    ? 'Reply-To: ' . $replyTo . "\r\n"
                    : ''
            ) .

            'To: <' . $to . ">\r\n" .

            'Subject: ' .
            '=?UTF-8?B?' .
            base64_encode($safeSubject) .
            "?=\r\n" .

            'MIME-Version: 1.0' .
            "\r\n" .

            'Content-Type: text/plain; charset=UTF-8' .
            "\r\n" .

            'Content-Transfer-Encoding: 8bit' .
            "\r\n\r\n";

        /*
         * Protection contre une ligne contenant uniquement un point.
         */

        $body = preg_replace(
            '/^\./m',
            '..',
            $body
        ) ?? $body;

        $message =
            $headers .
            $body .
            "\r\n.\r\n";

        fwrite(
            $socket,
            $message
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
| LECTURE DU JSON
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');

if (
    $rawBody === false ||
    trim($rawBody) === ''
) {
    respond([
        'success' => false,
        'message' => 'Aucune donnée reçue.',
    ], 400);
}

$data = json_decode(
    $rawBody,
    true
);

if (
    !is_array($data) ||
    json_last_error() !== JSON_ERROR_NONE
) {
    respond([
        'success' => false,
        'message' => 'Les données envoyées sont invalides.',
    ], 400);
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRATION DES CHAMPS
|--------------------------------------------------------------------------
*/

$name = clean_string(
    $data['name'] ?? ''
);

$company = clean_string(
    $data['company'] ?? ''
);

$email = normalize_email(
    clean_string(
        $data['email'] ?? ''
    )
);

$phone = clean_string(
    $data['phone'] ?? ''
);

$sector = clean_string(
    $data['sector'] ?? ''
);

$website = clean_string(
    $data['website'] ?? ''
);

$problem = clean_string(
    $data['problem'] ?? ''
);

$consent = filter_var(
    $data['consent'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

$marketing = filter_var(
    $data['marketing'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

$websiteCheck = clean_string(
    $data['websiteCheck'] ?? ''
);


/*
|--------------------------------------------------------------------------
| HONEYPOT
|--------------------------------------------------------------------------
|
| Le champ doit rester vide pour un humain.
|--------------------------------------------------------------------------
*/

if ($websiteCheck !== '') {
    /*
     * On ne révèle pas au bot qu'il a été détecté.
     */

    respond([
        'success' => true,
    ]);
}


/*
|--------------------------------------------------------------------------
| VALIDATION DES LONGUEURS
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $company === '' ||
    $email === '' ||
    $phone === '' ||
    $sector === '' ||
    $problem === ''
) {
    respond([
        'success' => false,
        'message' => 'Merci de compléter tous les champs obligatoires.',
    ], 422);
}


if (
    mb_strlen($name, 'UTF-8') >
    MAX_NAME_LENGTH
) {
    respond([
        'success' => false,
        'message' => 'Le nom renseigné est trop long.',
    ], 422);
}


if (
    mb_strlen($company, 'UTF-8') >
    MAX_COMPANY_LENGTH
) {
    respond([
        'success' => false,
        'message' => "Le nom de l'entreprise est trop long.",
    ], 422);
}


if (
    mb_strlen($email, 'UTF-8') >
    MAX_EMAIL_LENGTH
) {
    respond([
        'success' => false,
        'message' => "L'adresse e-mail est trop longue.",
    ], 422);
}


if (
    mb_strlen($phone, 'UTF-8') >
    MAX_PHONE_LENGTH
) {
    respond([
        'success' => false,
        'message' => 'Le numéro de téléphone est invalide.',
    ], 422);
}


if (
    mb_strlen($sector, 'UTF-8') >
    MAX_SECTOR_LENGTH
) {
    respond([
        'success' => false,
        'message' => 'Le secteur renseigné est invalide.',
    ], 422);
}


if (
    mb_strlen($website, 'UTF-8') >
    MAX_WEBSITE_LENGTH
) {
    respond([
        'success' => false,
        'message' => 'L’adresse du site est trop longue.',
    ], 422);
}


if (
    mb_strlen($problem, 'UTF-8') >
    MAX_PROBLEM_LENGTH
) {
    respond([
        'success' => false,
        'message' => 'La réponse renseignée est trop longue.',
    ], 422);
}


/*
|--------------------------------------------------------------------------
| VALIDATION E-MAIL
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {
    respond([
        'success' => false,
        'message' => 'Veuillez renseigner une adresse e-mail valide.',
    ], 422);
}


/*
|--------------------------------------------------------------------------
| VALIDATION TÉLÉPHONE
|--------------------------------------------------------------------------
*/

$phoneDigits = preg_replace(
    '/\D+/',
    '',
    $phone
) ?? '';

if (
    strlen($phoneDigits) < 8 ||
    strlen($phoneDigits) > 15
) {
    respond([
        'success' => false,
        'message' => 'Veuillez renseigner un numéro de téléphone valide.',
    ], 422);
}


/*
|--------------------------------------------------------------------------
| VALIDATION SITE INTERNET
|--------------------------------------------------------------------------
*/

if ($website !== '') {
    $websiteToValidate = $website;

    if (
        !preg_match(
            '#^https?://#i',
            $websiteToValidate
        )
    ) {
        $websiteToValidate =
            'https://' .
            $websiteToValidate;
    }

    if (
        !filter_var(
            $websiteToValidate,
            FILTER_VALIDATE_URL
        )
    ) {
        respond([
            'success' => false,
            'message' => 'L’adresse du site internet semble invalide.',
        ], 422);
    }

    /*
     * On interdit les protocoles ou structures manifestement
     * dangereuses.
     */

    $parts = parse_url(
        $websiteToValidate
    );

    if (
        !$parts ||
        empty($parts['host']) ||
        !in_array(
            strtolower($parts['scheme'] ?? ''),
            ['http', 'https'],
            true
        )
    ) {
        respond([
            'success' => false,
            'message' => 'L’adresse du site internet semble invalide.',
        ], 422);
    }

    $website = $websiteToValidate;
}


/*
|--------------------------------------------------------------------------
| VALIDATION DU CONSENTEMENT
|--------------------------------------------------------------------------
*/

if (!$consent) {
    respond([
        'success' => false,
        'message' => 'Vous devez accepter le règlement du Grand + pour participer.',
    ], 422);
}


/*
|--------------------------------------------------------------------------
| RATE LIMIT
|--------------------------------------------------------------------------
*/

$clientIp = get_client_ip();

check_rate_limit(
    $clientIp
);


/*
|--------------------------------------------------------------------------
| STOCKAGE
|--------------------------------------------------------------------------
*/

ensure_data_directory();

$participations = read_json_file(
    PARTICIPATIONS_FILE,
    []
);

if (!is_array($participations)) {
    $participations = [];
}


$monthKey = current_month_key();

$normalizedCompany = normalize_company(
    $company
);

$normalizedEmail = normalize_email(
    $email
);


/*
|--------------------------------------------------------------------------
| DÉTECTION DES DOUBLONS
|--------------------------------------------------------------------------
*/

foreach ($participations as $participation) {
    if (!is_array($participation)) {
        continue;
    }

    if (
        ($participation['month_key'] ?? '') !==
        $monthKey
    ) {
        continue;
    }

    $existingEmail = normalize_email(
        (string) (
            $participation['email'] ?? ''
        )
    );

    $existingCompany = normalize_company(
        (string) (
            $participation['company'] ?? ''
        )
    );

    /*
     * Même adresse e-mail :
     * participation déjà enregistrée.
     */

    if (
        $existingEmail !== '' &&
        hash_equals(
            hash('sha256', $existingEmail),
            hash('sha256', $normalizedEmail)
        )
    ) {
        respond([
            'success' => false,
            'message' => 'Une participation a déjà été enregistrée avec cette adresse e-mail pour ce mois.',
        ], 409);
    }

    /*
     * Même entreprise :
     * une seule participation par entreprise et par mois.
     */

    if (
        $existingCompany !== '' &&
        $existingCompany === $normalizedCompany
    ) {
        respond([
            'success' => false,
            'message' => 'Une participation a déjà été enregistrée pour cette entreprise ce mois-ci.',
        ], 409);
    }
}


/*
|--------------------------------------------------------------------------
| CRÉATION DE LA PARTICIPATION
|--------------------------------------------------------------------------
*/

$participationId =
    generate_participation_id();

$createdAt =
    date('c');

$ipHash =
    hash_ip($clientIp);


$participation = [
    'id' => $participationId,

    'month_key' => $monthKey,

    'month_label' =>
        current_month_label(),

    'created_at' =>
        $createdAt,

    'name' =>
        $name,

    'company' =>
        $company,

    'email' =>
        $email,

    'phone' =>
        $phone,

    'sector' =>
        $sector,

    'website' =>
        $website,

    'problem' =>
        $problem,

    /*
     * Consentements enregistrés séparément.
     */

    'consent_rules' =>
        true,

    'marketing_consent' =>
        $marketing,

    /*
     * L'IP réelle n'est jamais enregistrée.
     */

    'ip_hash' =>
        $ipHash,
];


/*
|--------------------------------------------------------------------------
| ÉCRITURE ATOMIQUE
|--------------------------------------------------------------------------
*/

$participations[] =
    $participation;

if (
    !write_json_file(
        PARTICIPATIONS_FILE,
        $participations
    )
) {
    respond([
        'success' => false,
        'message' => "Votre participation n'a pas pu être enregistrée. Merci de réessayer.",
    ], 500);
}


/*
|--------------------------------------------------------------------------
| E-MAILS
|--------------------------------------------------------------------------
*/

$mailConfig =
    load_mail_config();


$toEmail =
    (string) (
        $mailConfig['to_email']
        ?? DEFAULT_TO_EMAIL
    );


/*
|--------------------------------------------------------------------------
| E-MAIL ADMIN
|--------------------------------------------------------------------------
*/

$adminSubject =
    'Nouveau participant — Le Grand + — ' .
    $company;


$adminBody =
    "NOUVELLE PARTICIPATION — LE GRAND +\n" .
    "====================================\n\n" .

    "Identifiant : " .
    $participationId .
    "\n" .

    "Période : " .
    current_month_label() .
    "\n" .

    "Date : " .
    $createdAt .
    "\n\n" .

    "PARTICIPANT\n" .
    "-----------\n" .

    "Nom : " .
    $name .
    "\n" .

    "Entreprise : " .
    $company .
    "\n" .

    "E-mail : " .
    $email .
    "\n" .

    "Téléphone : " .
    $phone .
    "\n" .

    "Secteur : " .
    $sector .
    "\n" .

    "Site internet : " .
    (
        $website !== ''
            ? $website
            : 'Non renseigné'
    ) .
    "\n\n" .

    "PROBLÉMATIQUE\n" .
    "-------------\n" .

    $problem .
    "\n\n" .

    "CONSENTEMENTS\n" .
    "-------------\n" .

    "Règlement accepté : Oui\n" .

    "Prospection commerciale : " .
    (
        $marketing
            ? 'Oui'
            : 'Non'
    ) .
    "\n\n" .

    "Le participant est enregistré dans le système " .
    "du Grand +.\n";


$adminSent =
    smtp_send_mail(
        $mailConfig,
        $toEmail,
        $adminSubject,
        $adminBody,
        $email
    );


/*
|--------------------------------------------------------------------------
| E-MAIL DE CONFIRMATION AU PARTICIPANT
|--------------------------------------------------------------------------
*/

$confirmationSubject =
    'Votre participation au Grand + est enregistrée — Vitrine+';


$confirmationBody =
    "Bonjour " .
    $name .
    ",\n\n" .

    "Votre participation au Grand + de Vitrine+ " .
    "a bien été enregistrée.\n\n" .

    "Récapitulatif :\n" .
    "----------------\n" .

    "Entreprise : " .
    $company .
    "\n" .

    "Période : " .
    current_month_label() .
    "\n" .

    "Identifiant de participation : " .
    $participationId .
    "\n\n" .

    "La sélection du bénéficiaire est effectuée " .
    "selon les modalités prévues dans le règlement " .
    "du Grand +.\n\n" .

    "Si votre entreprise est sélectionnée, " .
    "Vitrine+ vous contactera directement aux " .
    "coordonnées indiquées lors de votre participation.\n\n" .

    "Votre participation est gratuite et ne vous " .
    "engage à aucune commande de prestation.\n\n" .

    "Merci pour votre participation.\n\n" .

    "Vitrine+\n" .
    "Votre entreprise. En mieux.\n\n" .

    "https://vitrineplus.fr/le-grand-plus\n";


$confirmationSent =
    smtp_send_mail(
        $mailConfig,
        $email,
        $confirmationSubject,
        $confirmationBody
    );


/*
|--------------------------------------------------------------------------
| RÉPONSE
|--------------------------------------------------------------------------
|
| Même si un e-mail rencontre temporairement un problème,
| la participation reste enregistrée.
|
|--------------------------------------------------------------------------
*/

respond([
    'success' => true,

    'participationId' =>
        $participationId,

    'month' =>
        current_month_label(),

    /*
     * Ces informations sont utiles pour le diagnostic
     * mais ne révèlent aucune donnée sensible.
     */

    'emailSent' =>
        $adminSent,

    'confirmationSent' =>
        $confirmationSent,
]);