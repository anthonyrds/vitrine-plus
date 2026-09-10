<?php

declare(strict_types=1);

/**
 * Vitrine+ — Social Studio API
 *
 * Fonctions :
 * - génération de contenu avec Gemini
 * - génération de visuels
 * - sauvegarde des contenus
 * - bibliothèque
 * - publication Instagram
 * - publication Post
 * - publication Story
 * - publication Carousel
 * - publication Reel
 * - programmation via cron
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

const SOCIAL_DATA_DIR = __DIR__ . '/vitrine-data/social';
const SOCIAL_CONTENT_FILE = SOCIAL_DATA_DIR . '/contents.json';
const SOCIAL_PREVIEW_DIR = __DIR__ . '/social-preview/generated';

function respond(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function clean(mixed $value): string
{
    return trim((string) $value);
}

function ensure_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException(
            'Impossible de créer le dossier : ' . $directory
        );
    }
}

function load_config(): array
{
    $configFile = __DIR__ . '/vitrine-mail-config.php';

    if (!is_file($configFile)) {
        throw new RuntimeException(
            'Le fichier vitrine-mail-config.php est introuvable.'
        );
    }

    $config = require $configFile;

    if (!is_array($config)) {
        throw new RuntimeException(
            'La configuration vitrine-mail-config.php est invalide.'
        );
    }

    return $config;
}

function config_string(
    array $config,
    string $key,
    string $default = ''
): string {
    return trim(
        (string) (
            $config[$key]
            ?? $default
        )
    );
}

function require_auth(): void
{
    $config = load_config();

    $expectedUser =
        config_string(
            $config,
            'grand_plus_admin_user'
        );

    $expectedPassword =
        config_string(
            $config,
            'grand_plus_admin_password'
        );

    if (
        $expectedUser === '' ||
        $expectedPassword === ''
    ) {
        throw new RuntimeException(
            'Les identifiants administrateur ne sont pas configurés.'
        );
    }

    $user =
        (string) (
            $_SERVER['PHP_AUTH_USER']
            ?? ''
        );

    $password =
        (string) (
            $_SERVER['PHP_AUTH_PW']
            ?? ''
        );

    if (
        !hash_equals(
            $expectedUser,
            $user
        ) ||
        !hash_equals(
            $expectedPassword,
            $password
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ Social Studio"'
        );

        respond(
            [
                'success' => false,
                'message' => 'Authentification requise.',
            ],
            401
        );
    }
}

function read_contents(): array
{
    ensure_directory(SOCIAL_DATA_DIR);

    if (!is_file(SOCIAL_CONTENT_FILE)) {
        @file_put_contents(
            SOCIAL_CONTENT_FILE,
            "[]",
            LOCK_EX
        );

        return [];
    }

    $raw =
        @file_get_contents(
            SOCIAL_CONTENT_FILE
        );

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded =
        json_decode(
            $raw,
            true
        );

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(
        array_filter(
            $decoded,
            static fn ($item): bool =>
                is_array($item)
        )
    );
}

function write_contents(array $contents): void
{
    ensure_directory(SOCIAL_DATA_DIR);

    $json =
        json_encode(
            array_values($contents),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );

    if ($json === false) {
        throw new RuntimeException(
            'Impossible d’encoder les contenus.'
        );
    }

    if (
        @file_put_contents(
            SOCIAL_CONTENT_FILE,
            $json,
            LOCK_EX
        ) === false
    ) {
        throw new RuntimeException(
            'Impossible d’enregistrer les contenus.'
        );
    }
}

function request_json(): array
{
    $raw =
        file_get_contents(
            'php://input'
        );

    if (
        $raw !== false &&
        trim($raw) !== ''
    ) {
        $decoded =
            json_decode(
                $raw,
                true
            );

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return [];
}

function http_request(
    string $url,
    string $method = 'GET',
    array $fields = [],
    array $headers = [],
    int $timeout = 60
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'cURL est nécessaire pour Social Studio.'
        );
    }

    $ch =
        curl_init();

    if ($ch === false) {
        throw new RuntimeException(
            'Impossible d’initialiser cURL.'
        );
    }

    $method =
        strtoupper(
            $method
        );

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT =>
            'VitrinePlus-SocialStudio/2.0',
    ];

    if (
        $method === 'POST' &&
        !empty($fields)
    ) {
        $options[CURLOPT_POSTFIELDS] =
            http_build_query(
                $fields,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    curl_setopt_array(
        $ch,
        $options
    );

    $body =
        curl_exec($ch);

    $curlError =
        curl_error($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException(
            'Erreur réseau : ' .
            (
                $curlError !== ''
                    ? $curlError
                    : 'échec cURL.'
            )
        );
    }

    $decoded =
        json_decode(
            $body,
            true
        );

    if (!is_array($decoded)) {
        $decoded = [
            'raw' => $body,
        ];
    }

    return [
        'http_code' => $httpCode,
        'body' => $decoded,
        'raw' => $body,
    ];
}

/**
 * =========================================================
 * GEMINI
 * =========================================================
 */

function extract_gemini_text(
    array $response
): string {
    $parts = [];

    $candidates =
        $response['candidates']
        ?? [];

    if (!is_array($candidates)) {
        return '';
    }

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $content =
            $candidate['content']
            ?? null;

        if (!is_array($content)) {
            continue;
        }

        $candidateParts =
            $content['parts']
            ?? [];

        if (!is_array($candidateParts)) {
            continue;
        }

        foreach (
            $candidateParts
            as $part
        ) {
            if (
                is_array($part) &&
                isset($part['text']) &&
                is_string($part['text'])
            ) {
                $parts[] =
                    $part['text'];
            }
        }
    }

    return trim(
        implode(
            "\n",
            $parts
        )
    );
}

function call_gemini(
    string $prompt
): string {
    $config =
        load_config();

    $apiKey =
        config_string(
            $config,
            'gemini_api_key'
        );

    if ($apiKey === '') {
        throw new RuntimeException(
            'La clé Gemini n’est pas configurée dans vitrine-mail-config.php.'
        );
    }

    $configuredModel =
        config_string(
            $config,
            'gemini_model'
        );

    $models = [];

    if ($configuredModel !== '') {
        $models[] =
            $configuredModel;
    }

    $models = array_merge(
        $models,
        [
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.1-flash-lite',
            'gemini-2.5-flash-lite',
        ]
    );

    $models =
        array_values(
            array_unique($models)
        );

    $lastError =
        'Gemini n’a retourné aucune réponse.';

    foreach ($models as $model) {
        $url =
            'https://generativelanguage.googleapis.com/v1beta/models/' .
            rawurlencode($model) .
            ':generateContent';

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'responseMimeType' =>
                    'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'caption' => [
                            'type' => 'STRING',
                        ],
                        'slides' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'STRING',
                            ],
                        ],
                        'script' => [
                            'type' => 'STRING',
                        ],
                    ],
                    'required' => [
                        'caption',
                    ],
                ],
            ],
        ];

        $json =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if ($json === false) {
            throw new RuntimeException(
                'Impossible de préparer la requête Gemini.'
            );
        }

        $result =
            http_request(
                $url,
                'POST',
                [],
                [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $apiKey,
                ],
                90
            );

        if (
            $result['http_code'] < 200 ||
            $result['http_code'] >= 300
        ) {
            $message =
                $result['body']['error']['message']
                ?? 'Erreur Gemini inconnue.';

            $lastError =
                'Gemini (' .
                $model .
                ') : ' .
                $message;

            continue;
        }

        $text =
            extract_gemini_text(
                $result['body']
            );

        if ($text === '') {
            $lastError =
                'Gemini (' .
                $model .
                ') n’a retourné aucun texte.';

            continue;
        }

        return $text;
    }

    throw new RuntimeException(
        $lastError
    );
}

function generate_content(
    string $type,
    string $topic,
    string $objective
): array {
    $formatInstructions = '';

    switch ($type) {
        case 'carousel':
            $formatInstructions = <<<TXT
Crée un carrousel Instagram de 6 à 8 slides.
Chaque slide doit être court, lisible et utile.
Le premier slide doit être un hook fort.
Le dernier slide doit contenir un appel à l'action.
TXT;
            break;

        case 'reel':
            $formatInstructions = <<<TXT
Crée un script de Reel Instagram de 30 à 60 secondes.
Structure :
- hook immédiat
- développement
- exemple concret
- conclusion
- appel à l'action.
TXT;
            break;

        case 'story':
            $formatInstructions = <<<TXT
Crée une Story Instagram courte et engageante.
La légende doit être concise et adaptée à une Story.
TXT;
            break;

        default:
            $formatInstructions = <<<TXT
Crée une publication Instagram professionnelle.
La légende doit être engageante, naturelle et orientée conversion sans être agressive.
TXT;
            break;
    }

    $prompt = <<<PROMPT
Tu es le directeur éditorial et social media de Vitrine+,
une agence digitale française spécialisée dans la création
de sites internet et l'accompagnement des entreprises dans
leur présence en ligne.

Promesse de marque :
« Votre entreprise. En mieux. »

Sujet :
{$topic}

Objectif :
{$objective}

Format :
{$type}

{$formatInstructions}

Règles :
- écris en français naturel ;
- ton premium, professionnel et accessible ;
- évite les formulations artificielles ;
- pas de promesses mensongères ;
- ne prétends jamais qu'une action a déjà été effectuée ;
- favorise l'engagement ;
- termine avec un appel à l'action pertinent ;
- utilise quelques emojis maximum ;
- ajoute des hashtags pertinents sans en mettre une quantité excessive ;
- le contenu doit être directement exploitable par Vitrine+.

Retourne UNIQUEMENT un JSON valide avec :
{
  "caption": "...",
  "slides": ["...", "..."],
  "script": "..."
}
PROMPT;

    $text =
        call_gemini(
            $prompt
        );

    $decoded =
        json_decode(
            $text,
            true
        );

    if (!is_array($decoded)) {
        $text =
            preg_replace(
                '/^```(?:json)?\s*/i',
                '',
                $text
            );

        $text =
            preg_replace(
                '/\s*```$/',
                '',
                $text
            );

        $decoded =
            json_decode(
                trim($text),
                true
            );
    }

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Gemini a retourné une réponse JSON invalide.'
        );
    }

    return [
        'caption' =>
            trim(
                (string) (
                    $decoded['caption']
                    ?? ''
                )
            ),
        'slides' =>
            is_array(
                $decoded['slides']
                ?? null
            )
                ? array_values(
                    array_map(
                        'strval',
                        $decoded['slides']
                    )
                )
                : [],
        'script' =>
            trim(
                (string) (
                    $decoded['script']
                    ?? ''
                )
            ),
    ];
}

/**
 * =========================================================
 * VISUELS
 * =========================================================
 */

function hex_to_rgb(
    string $hex
): array {
    $hex =
        ltrim(
            trim($hex),
            '#'
        );

    if (strlen($hex) === 3) {
        $hex =
            $hex[0] .
            $hex[0] .
            $hex[1] .
            $hex[1] .
            $hex[2] .
            $hex[2];
    }

    if (
        !preg_match(
            '/^[0-9a-fA-F]{6}$/',
            $hex
        )
    ) {
        return [
            200,
            164,
            93,
        ];
    }

    return [
        hexdec(
            substr($hex, 0, 2)
        ),
        hexdec(
            substr($hex, 2, 2)
        ),
        hexdec(
            substr($hex, 4, 2)
        ),
    ];
}

function draw_centered_text(
    $image,
    string $text,
    int $fontSize,
    int $y,
    int $width,
    int $color
): void {
    $fontCandidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
    ];

    $font = '';

    foreach ($fontCandidates as $candidate) {
        if (is_file($candidate)) {
            $font = $candidate;
            break;
        }
    }

    if ($font === '') {
        imagestring(
            $image,
            5,
            40,
            $y,
            $text,
            $color
        );

        return;
    }

    $words =
        preg_split(
            '/\s+/',
            trim($text)
        );

    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate =
            $current === ''
                ? $word
                : $current . ' ' . $word;

        $box =
            imagettfbbox(
                $fontSize,
                0,
                $font,
                $candidate
            );

        $candidateWidth =
            abs($box[2] - $box[0]);

        if (
            $candidateWidth >
            ($width - 120)
        ) {
            if ($current !== '') {
                $lines[] =
                    $current;
            }

            $current =
                $word;
        } else {
            $current =
                $candidate;
        }
    }

    if ($current !== '') {
        $lines[] =
            $current;
    }

    $lineHeight =
        (int) (
            $fontSize *
            1.35
        );

    foreach ($lines as $index => $line) {
        $box =
            imagettfbbox(
                $fontSize,
                0,
                $font,
                $line
            );

        $lineWidth =
            abs($box[2] - $box[0]);

        $x =
            (int) (
                ($width - $lineWidth) / 2
            );

        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $y +
                (
                    $index *
                    $lineHeight
                ),
            $color,
            $font,
            $line
        );
    }
}

function generate_visual(
    string $type,
    string $topic,
    string $caption,
    int $slide = 1,
    int $total = 1
): string {
    ensure_directory(
        SOCIAL_PREVIEW_DIR
    );

    if (
        !function_exists(
            'imagecreatetruecolor'
        )
    ) {
        throw new RuntimeException(
            'GD n’est pas disponible sur le serveur.'
        );
    }

    $width = 1080;
    $height = 1080;

    if ($type === 'story' || $type === 'reel') {
        $width = 1080;
        $height = 1920;
    }

    $image =
        imagecreatetruecolor(
            $width,
            $height
        );

    if ($image === false) {
        throw new RuntimeException(
            'Impossible de créer le visuel.'
        );
    }

    [$r, $g, $b] =
        hex_to_rgb(
            '#080808'
        );

    $background =
        imagecolorallocate(
            $image,
            $r,
            $g,
            $b
        );

    imagefill(
        $image,
        0,
        0,
        $background
    );

    [$goldR, $goldG, $goldB] =
        hex_to_rgb(
            '#C8A45D'
        );

    $gold =
        imagecolorallocate(
            $image,
            $goldR,
            $goldG,
            $goldB
        );

    $white =
        imagecolorallocate(
            $image,
            255,
            255,
            255
        );

    $muted =
        imagecolorallocate(
            $image,
            150,
            150,
            150
        );

    imagefilledrectangle(
        $image,
        0,
        0,
        $width,
        12,
        $gold
    );

    imagefilledellipse(
        $image,
        (int) ($width / 2),
        180,
        150,
        150,
        $gold
    );

    draw_centered_text(
        $image,
        'V+',
        52,
        202,
        $width,
        $background
    );

    $mainText =
        $caption !== ''
            ? $caption
            : $topic;

    if ($type === 'carousel') {
        $mainText =
            $caption !== ''
                ? $caption
                : $topic;
    }

    if ($type === 'reel') {
        $mainText =
            $topic !== ''
                ? $topic
                : 'Vitrine+';
    }

    draw_centered_text(
        $image,
        $mainText,
        $type === 'reel'
            ? 54
            : 48,
        $type === 'reel'
            ? 760
            : 650,
        $width,
        $white
    );

    draw_centered_text(
        $image,
        'Votre entreprise. En mieux.',
        24,
        $height - 170,
        $width,
        $muted
    );

    if ($type === 'carousel') {
        draw_centered_text(
            $image,
            $slide . ' / ' . $total,
            22,
            $height - 90,
            $width,
            $gold
        );
    }

    $filename =
        'social-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(4)
        ) .
        '.jpg';

    $path =
        SOCIAL_PREVIEW_DIR .
        '/' .
        $filename;

    if (
        !imagejpeg(
            $image,
            $path,
            92
        )
    ) {
        imagedestroy($image);

        throw new RuntimeException(
            'Impossible d’enregistrer le visuel.'
        );
    }

    imagedestroy($image);

    return
        'https://vitrineplus.fr/social-preview/generated/' .
        rawurlencode($filename);
}

/**
 * =========================================================
 * INSTAGRAM
 * =========================================================
 */

function instagram_config(): array
{
    $config =
        load_config();

    $token =
        config_string(
            $config,
            'instagram_access_token'
        );

    if ($token === '') {
        throw new RuntimeException(
            'Le token Instagram n’est pas configuré.'
        );
    }

    $apiVersion =
        config_string(
            $config,
            'instagram_api_version',
            'v24.0'
        );

    return [
        'token' => $token,
        'api_version' => $apiVersion,
    ];
}

function get_instagram_account(): array
{
    $config =
        instagram_config();

    $url =
        'https://graph.instagram.com/' .
        $config['api_version'] .
        '/me?fields=id,username&access_token=' .
        rawurlencode(
            $config['token']
        );

    $result =
        http_request(
            $url,
            'GET',
            [],
            [],
            45
        );

    if (
        $result['http_code'] < 200 ||
        $result['http_code'] >= 300
    ) {
        $error =
            $result['body']['error']
            ?? [];

        throw new RuntimeException(
            'Instagram : ' .
            (
                $error['message']
                ?? 'Impossible de récupérer le compte Instagram.'
            )
        );
    }

    $account =
        $result['body'];

    if (
        empty($account['id'])
    ) {
        throw new RuntimeException(
            'Instagram n’a pas retourné l’identifiant du compte.'
        );
    }

    return [
        'id' =>
            (string) $account['id'],
        'username' =>
            (string) (
                $account['username']
                ?? ''
            ),
    ];
}

function instagram_error_message(
    array $response,
    string $fallback
): string {
    $error =
        $response['body']['error']
        ?? [];

    $message =
        (string) (
            $error['message']
            ?? $fallback
        );

    $type =
        (string) (
            $error['type']
            ?? ''
        );

    $code =
        (string) (
            $error['code']
            ?? ''
        );

    $subcode =
        (string) (
            $error['error_subcode']
            ?? ''
        );

    $trace =
        (string) (
            $error['fbtrace_id']
            ?? ''
        );

    $details =
        'Instagram : ' .
        $message;

    if ($type !== '') {
        $details .=
            ' | Type : ' .
            $type;
    }

    if ($code !== '') {
        $details .=
            ' | Code : ' .
            $code;
    }

    if ($subcode !== '') {
        $details .=
            ' | Sous-code : ' .
            $subcode;
    }

    if ($trace !== '') {
        $details .=
            ' | FBTrace ID : ' .
            $trace;
    }

    return $details;
}

function wait_instagram_container(
    string $containerId,
    array $config
): string {
    $lastStatus = '';

    for ($i = 0; $i < 30; $i++) {
        $url =
            'https://graph.instagram.com/' .
            $config['api_version'] .
            '/' .
            rawurlencode(
                $containerId
            ) .
            '?fields=status_code,status&access_token=' .
            rawurlencode(
                $config['token']
            );

        $result =
            http_request(
                $url,
                'GET',
                [],
                [],
                45
            );

        if (
            $result['http_code'] < 200 ||
            $result['http_code'] >= 300
        ) {
            throw new RuntimeException(
                instagram_error_message(
                    $result,
                    'Impossible de vérifier le média Instagram.'
                )
            );
        }

        $statusCode =
            strtoupper(
                (string) (
                    $result['body']['status_code']
                    ?? ''
                )
            );

        $status =
            (string) (
                $result['body']['status']
                ?? ''
            );

        $lastStatus =
            $statusCode !== ''
                ? $statusCode
                : $status;

        if (
            in_array(
                $statusCode,
                [
                    'FINISHED',
                    'PUBLISHED',
                ],
                true
            )
        ) {
            return $statusCode;
        }

        if (
            in_array(
                $statusCode,
                [
                    'ERROR',
                    'EXPIRED',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Instagram a refusé ou n’a pas pu traiter le média. Statut : ' .
                $statusCode
            );
        }

        sleep(2);
    }

    throw new RuntimeException(
        'Instagram n’a pas terminé le traitement du média dans le délai prévu. Dernier statut : ' .
        $lastStatus
    );
}

function create_instagram_image_container(
    string $imageUrl,
    string $caption,
    string $type
): array {
    $config =
        instagram_config();

    $account =
        get_instagram_account();

    $accountId =
        $account['id'];

    $fields = [
        'image_url' =>
            $imageUrl,
        'caption' =>
            $caption,
        'access_token' =>
            $config['token'],
    ];

    if ($type === 'story') {
        $fields['is_stories'] = 'true';
    }

    $url =
        'https://graph.instagram.com/' .
        $config['api_version'] .
        '/' .
        rawurlencode(
            $accountId
        ) .
        '/media';

    $response =
        http_request(
            $url,
            'POST',
            $fields,
            [],
            90
        );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300 ||
        empty($response['body']['id'])
    ) {
        throw new RuntimeException(
            instagram_error_message(
                $response,
                'Impossible de créer le média Instagram.'
            )
        );
    }

    return [
        'id' =>
            (string) $response['body']['id'],
        'account' =>
            $account,
    ];
}

function publish_instagram_container(
    string $containerId
): string {
    $config =
        instagram_config();

    $account =
        get_instagram_account();

    $url =
        'https://graph.instagram.com/' .
        $config['api_version'] .
        '/' .
        rawurlencode(
            $account['id']
        ) .
        '/media_publish';

    $response =
        http_request(
            $url,
            'POST',
            [
                'creation_id' =>
                    $containerId,
                'access_token' =>
                    $config['token'],
            ],
            [],
            90
        );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300 ||
        empty($response['body']['id'])
    ) {
        throw new RuntimeException(
            instagram_error_message(
                $response,
                'Instagram n’a pas publié le média.'
            )
        );
    }

    return
        (string) $response['body']['id'];
}

function publish_instagram_image(
    string $imageUrl,
    string $caption,
    string $type
): array {
    $config =
        instagram_config();

    $container =
        create_instagram_image_container(
            $imageUrl,
            $caption,
            $type
        );

    $containerId =
        $container['id'];

    $status =
        wait_instagram_container(
            $containerId,
            $config
        );

    $mediaId =
        publish_instagram_container(
            $containerId
        );

    return [
        'account' =>
            $container['account'],
        'account_id' =>
            $container['account']['id'],
        'container_id' =>
            $containerId,
        'container_status' =>
            $status,
        'media_id' =>
            $mediaId,
    ];
}

function publish_instagram_carousel(
    array $images,
    string $caption
): array {
    if (
        count($images) < 2
    ) {
        throw new RuntimeException(
            'Un carrousel Instagram nécessite au moins 2 visuels.'
        );
    }

    if (
        count($images) > 10
    ) {
        throw new RuntimeException(
            'Instagram autorise au maximum 10 éléments dans un carrousel.'
        );
    }

    $config =
        instagram_config();

    $account =
        get_instagram_account();

    $children = [];

    foreach ($images as $imageUrl) {
        $imageUrl =
            trim(
                (string) $imageUrl
            );

        if (
            !filter_var(
                $imageUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new RuntimeException(
                'Un des visuels du carrousel possède une URL invalide.'
            );
        }

        $url =
            'https://graph.instagram.com/' .
            $config['api_version'] .
            '/' .
            rawurlencode(
                $account['id']
            ) .
            '/media';

        $response =
            http_request(
                $url,
                'POST',
                [
                    'image_url' =>
                        $imageUrl,
                    'is_carousel_item' =>
                        'true',
                    'access_token' =>
                        $config['token'],
                ],
                [],
                90
            );

        if (
            $response['http_code'] < 200 ||
            $response['http_code'] >= 300 ||
            empty($response['body']['id'])
        ) {
            throw new RuntimeException(
                instagram_error_message(
                    $response,
                    'Impossible de créer un élément du carrousel.'
                )
            );
        }

        $children[] =
            (string) $response['body']['id'];
    }

    foreach ($children as $child) {
        wait_instagram_container(
            $child,
            $config
        );
    }

    $url =
        'https://graph.instagram.com/' .
        $config['api_version'] .
        '/' .
        rawurlencode(
            $account['id']
        ) .
        '/media';

    $response =
        http_request(
            $url,
            'POST',
            [
                'media_type' =>
                    'CAROUSEL',
                'children' =>
                    implode(
                        ',',
                        $children
                    ),
                'caption' =>
                    $caption,
                'access_token' =>
                    $config['token'],
            ],
            [],
            90
        );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300 ||
        empty($response['body']['id'])
    ) {
        throw new RuntimeException(
            instagram_error_message(
                $response,
                'Impossible de créer le carrousel Instagram.'
            )
        );
    }

    $containerId =
        (string) $response['body']['id'];

    $status =
        wait_instagram_container(
            $containerId,
            $config
        );

    $mediaId =
        publish_instagram_container(
            $containerId
        );

    return [
        'account' =>
            $account,
        'account_id' =>
            $account['id'],
        'container_id' =>
            $containerId,
        'container_status' =>
            $status,
        'media_id' =>
            $mediaId,
        'children' =>
            $children,
    ];
}

function publish_instagram_reel(
    string $videoUrl,
    string $caption
): array {
    $config =
        instagram_config();

    $account =
        get_instagram_account();

    if (
        !filter_var(
            $videoUrl,
            FILTER_VALIDATE_URL
        )
    ) {
        throw new RuntimeException(
            'L’URL de la vidéo est invalide.'
        );
    }

    $url =
        'https://graph.instagram.com/' .
        $config['api_version'] .
        '/' .
        rawurlencode(
            $account['id']
        ) .
        '/media';

    $response =
        http_request(
            $url,
            'POST',
            [
                'media_type' =>
                    'REELS',
                'video_url' =>
                    $videoUrl,
                'caption' =>
                    $caption,
                'access_token' =>
                    $config['token'],
            ],
            [],
            90
        );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300 ||
        empty($response['body']['id'])
    ) {
        throw new RuntimeException(
            instagram_error_message(
                $response,
                'Impossible de créer le Reel Instagram.'
            )
        );
    }

    $containerId =
        (string) $response['body']['id'];

    $status =
        wait_instagram_container(
            $containerId,
            $config
        );

    $mediaId =
        publish_instagram_container(
            $containerId
        );

    return [
        'account' =>
            $account,
        'account_id' =>
            $account['id'],
        'container_id' =>
            $containerId,
        'container_status' =>
            $status,
        'media_id' =>
            $mediaId,
    ];
}

/**
 * =========================================================
 * ROUTER
 * =========================================================
 */

try {
    require_auth();

    $method =
        strtoupper(
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        );

    if (
        $method === 'GET'
    ) {
        $contents =
            read_contents();

        usort(
            $contents,
            static function (
                array $a,
                array $b
            ): int {
                return strcmp(
                    (string) (
                        $b['createdAt']
                        ?? $b['created_at']
                        ?? ''
                    ),
                    (string) (
                        $a['createdAt']
                        ?? $a['created_at']
                        ?? ''
                    )
                );
            }
        );

        respond(
            [
                'success' => true,
                'contents' =>
                    $contents,
            ]
        );
    }

    if (
        $method !== 'POST'
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Méthode non autorisée.',
            ],
            405
        );
    }

    $input =
        request_json();

    $action =
        clean(
            $input['action']
            ?? ''
        );

    /**
     * LIST
     */
    if (
        $action === 'list'
    ) {
        respond(
            [
                'success' => true,
                'contents' =>
                    read_contents(),
            ]
        );
    }

    /**
     * GENERATE
     */
    if (
        $action === 'generate'
    ) {
        $type =
            clean(
                $input['type']
                ?? 'post'
            );

        $topic =
            clean(
                $input['topic']
                ?? ''
            );

        $objective =
            clean(
                $input['objective']
                ?? ''
            );

        if ($topic === '') {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Le sujet est obligatoire.',
                ],
                422
            );
        }

        $allowedTypes = [
            'post',
            'carousel',
            'reel',
            'story',
        ];

        if (
            !in_array(
                $type,
                $allowedTypes,
                true
            )
        ) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Format de contenu invalide.',
                ],
                422
            );
        }

        $content =
            generate_content(
                $type,
                $topic,
                $objective
            );

        respond(
            [
                'success' => true,
                'content' =>
                    $content,
            ]
        );
    }

    /**
     * GENERATE VISUAL
     */
    if (
        $action === 'generate_visual'
    ) {
        $type =
            clean(
                $input['type']
                ?? 'post'
            );

        $topic =
            clean(
                $input['topic']
                ?? ''
            );

        $caption =
            clean(
                $input['caption']
                ?? ''
            );

        $slide =
            max(
                1,
                (int) (
                    $input['slide']
                    ?? 1
                )
            );

        $total =
            max(
                1,
                (int) (
                    $input['total']
                    ?? 1
                )
            );

        try {
            $url =
                generate_visual(
                    $type,
                    $topic,
                    $caption,
                    $slide,
                    $total
                );
        } catch (Throwable $e) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        $e->getMessage(),
                ],
                500
            );
        }

        respond(
            [
                'success' => true,
                'url' =>
                    $url,
            ]
        );
    }

    /**
     * SAVE
     */
    if (
        $action === 'save'
    ) {
        $content =
            $input['content']
            ?? null;

        if (
            !is_array($content)
        ) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Contenu invalide.',
                ],
                422
            );
        }

        $contents =
            read_contents();

        $contentId =
            clean(
                $content['id']
                ?? ''
            );

        if ($contentId === '') {
            $contentId =
                'social-' .
                date('YmdHis') .
                '-' .
                bin2hex(
                    random_bytes(4)
                );

            $content['id'] =
                $contentId;
        }

        $content['type'] =
            clean(
                $content['type']
                ?? 'post'
            );

        $content['topic'] =
            clean(
                $content['topic']
                ?? ''
            );

        $content['objective'] =
            clean(
                $content['objective']
                ?? ''
            );

        $content['caption'] =
            trim(
                (string) (
                    $content['caption']
                    ?? ''
                )
            );

        $content['slides'] =
            is_array(
                $content['slides']
                ?? null
            )
                ? array_values(
                    array_map(
                        'strval',
                        $content['slides']
                    )
                )
                : [];

        $content['images'] =
            is_array(
                $content['images']
                ?? null
            )
                ? array_values(
                    array_map(
                        'strval',
                        $content['images']
                    )
                )
                : [];

        $content['script'] =
            trim(
                (string) (
                    $content['script']
                    ?? ''
                )
            );

        $content['image'] =
            clean(
                $content['image']
                ?? ''
            );

        $content['video'] =
            clean(
                $content['video']
                ?? ''
            );

        $content['createdAt'] =
            clean(
                $content['createdAt']
                ?? date(
                    DATE_ATOM
                )
            );

        $content['updatedAt'] =
            date(
                DATE_ATOM
            );

        if (
            clean(
                $content['scheduledAt']
                ?? ''
            ) !== ''
        ) {
            $content['status'] =
                'scheduled';
        } elseif (
            clean(
                $content['status']
                ?? ''
            ) !== 'published'
        ) {
            $content['status'] =
                'draft';
        }

        $found = false;

        foreach (
            $contents
            as $index => $existing
        ) {
            if (
                (
                    $existing['id']
                    ?? ''
                ) ===
                $contentId
            ) {
                $contents[$index] =
                    $content;

                $found = true;
                break;
            }
        }

        if (!$found) {
            $contents[] =
                $content;
        }

        write_contents(
            $contents
        );

        respond(
            [
                'success' => true,
                'content' =>
                    $content,
            ]
        );
    }

    /**
     * DELETE
     */
    if (
        $action === 'delete'
    ) {
        $id =
            clean(
                $input['id']
                ?? ''
            );

        if ($id === '') {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Identifiant manquant.',
                ],
                422
            );
        }

        $contents =
            read_contents();

        $contents =
            array_values(
                array_filter(
                    $contents,
                    static function (
                        array $content
                    ) use ($id): bool {
                        return (
                            (
                                $content['id']
                                ?? ''
                            ) !== $id
                        );
                    }
                )
            );

        write_contents(
            $contents
        );

        respond(
            [
                'success' => true,
            ]
        );
    }

    /**
     * PUBLISH
     */
    if (
        $action === 'publish'
    ) {
        $caption =
            trim(
                (string) (
                    $input['caption']
                    ?? ''
                )
            );

        $type =
            clean(
                $input['type']
                ?? 'post'
            );

        $image =
            clean(
                $input['image']
                ?? ''
            );

        $video =
            clean(
                $input['video']
                ?? ''
            );

        $images =
            is_array(
                $input['images']
                ?? null
            )
                ? array_values(
                    array_filter(
                        array_map(
                            'strval',
                            $input['images']
                        ),
                        static fn (
                            string $value
                        ): bool =>
                            trim($value) !== ''
                    )
                )
                : [];

        if ($caption === '') {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'La légende est vide.',
                ],
                422
            );
        }

        try {
            switch ($type) {
                case 'post':
                    if (
                        !filter_var(
                            $image,
                            FILTER_VALIDATE_URL
                        )
                    ) {
                        throw new RuntimeException(
                            'Aucun visuel public valide n’a été fourni.'
                        );
                    }

                    $result =
                        publish_instagram_image(
                            $image,
                            $caption,
                            'post'
                        );
                    break;

                case 'story':
                    if (
                        !filter_var(
                            $image,
                            FILTER_VALIDATE_URL
                        )
                    ) {
                        throw new RuntimeException(
                            'Aucun visuel public valide n’a été fourni pour la Story.'
                        );
                    }

                    $result =
                        publish_instagram_image(
                            $image,
                            $caption,
                            'story'
                        );
                    break;

                case 'carousel':
                    if (
                        count($images) < 2
                    ) {
                        throw new RuntimeException(
                            'Le carrousel doit contenir au moins 2 visuels.'
                        );
                    }

                    $result =
                        publish_instagram_carousel(
                            $images,
                            $caption
                        );
                    break;

                case 'reel':
                    if (
                        !filter_var(
                            $video,
                            FILTER_VALIDATE_URL
                        )
                    ) {
                        throw new RuntimeException(
                            'Aucune vidéo publique valide n’a été fournie pour le Reel.'
                        );
                    }

                    $result =
                        publish_instagram_reel(
                            $video,
                            $caption
                        );
                    break;

                default:
                    throw new RuntimeException(
                        'Format Instagram invalide.'
                    );
            }
        } catch (Throwable $e) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        $e->getMessage(),
                ],
                500
            );
        }

        $contentId =
            clean(
                $input['id']
                ?? ''
            );

        if (
            $contentId !== ''
        ) {
            $contents =
                read_contents();

            foreach (
                $contents
                as $index => $content
            ) {
                if (
                    (
                        $content['id']
                        ?? ''
                    ) !== $contentId
                ) {
                    continue;
                }

                $contents[$index]['status'] =
                    'published';

                $contents[$index]['updatedAt'] =
                    date(
                        DATE_ATOM
                    );

                $contents[$index]['instagramMediaId'] =
                    $result['media_id']
                    ?? '';

                $contents[$index]['instagramContainerId'] =
                    $result['container_id']
                    ?? '';

                break;
            }

            write_contents(
                $contents
            );
        }

        respond(
            [
                'success' => true,
                'message' =>
                    'Publication envoyée sur Instagram.',
                'media_id' =>
                    $result['media_id']
                    ?? '',
                'container_id' =>
                    $result['container_id']
                    ?? '',
                'account' =>
                    $result['account']
                    ?? null,
            ]
        );
    }

    /**
     * CRON
     */
    if (
        $action === 'cron'
    ) {
        $contents =
            read_contents();

        $now =
            time();

        $processed = 0;
        $published = 0;
        $errors = [];

        foreach (
            $contents
            as $index => $content
        ) {
            if (
                (
                    $content['status']
                    ?? ''
                ) !== 'scheduled'
            ) {
                continue;
            }

            $scheduledAt =
                clean(
                    $content['scheduledAt']
                    ?? ''
                );

            if ($scheduledAt === '') {
                continue;
            }

            $timestamp =
                strtotime(
                    $scheduledAt
                );

            if (
                $timestamp === false ||
                $timestamp > $now
            ) {
                continue;
            }

            $processed++;

            try {
                $caption =
                    trim(
                        (string) (
                            $content['caption']
                            ?? ''
                        )
                    );

                $type =
                    clean(
                        $content['type']
                        ?? 'post'
                    );

                if ($caption === '') {
                    throw new RuntimeException(
                        'La légende est vide.'
                    );
                }

                if (
                    $type === 'carousel'
                ) {
                    $result =
                        publish_instagram_carousel(
                            is_array(
                                $content['images']
                                ?? null
                            )
                                ? $content['images']
                                : [],
                            $caption
                        );
                } elseif (
                    $type === 'reel'
                ) {
                    $result =
                        publish_instagram_reel(
                            clean(
                                $content['video']
                                ?? ''
                            ),
                            $caption
                        );
                } else {
                    $result =
                        publish_instagram_image(
                            clean(
                                $content['image']
                                ?? ''
                            ),
                            $caption,
                            $type === 'story'
                                ? 'story'
                                : 'post'
                        );
                }

                $contents[$index]['status'] =
                    'published';

                $contents[$index]['updatedAt'] =
                    date(
                        DATE_ATOM
                    );

                $contents[$index]['instagramMediaId'] =
                    $result['media_id']
                    ?? '';

                $contents[$index]['instagramContainerId'] =
                    $result['container_id']
                    ?? '';

                $published++;
            } catch (Throwable $e) {
                $contents[$index]['status'] =
                    'draft';

                $contents[$index]['updatedAt'] =
                    date(
                        DATE_ATOM
                    );

                $contents[$index]['lastError'] =
                    $e->getMessage();

                $errors[] = [
                    'id' =>
                        $content['id']
                        ?? '',
                    'message' =>
                        $e->getMessage(),
                ];
            }
        }

        write_contents(
            $contents
        );

        respond(
            [
                'success' => true,
                'processed' =>
                    $processed,
                'published' =>
                    $published,
                'errors' =>
                    $errors,
            ]
        );
    }

    respond(
        [
            'success' => false,
            'message' =>
                'Action inconnue.',
        ],
        400
    );
} catch (Throwable $e) {
    respond(
        [
            'success' => false,
            'message' =>
                $e->getMessage(),
        ],
        500
    );
}