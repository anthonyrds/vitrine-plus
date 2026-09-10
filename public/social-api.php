<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const BASE_DIR = __DIR__;

const CONFIG_FILE =
    BASE_DIR . '/vitrine-mail-config.php';

const SOCIAL_DIR =
    BASE_DIR . '/vitrine-data/social';

const SOCIAL_FILE =
    SOCIAL_DIR . '/contents.json';

const SOCIAL_PREVIEW_DIR =
    BASE_DIR . '/social-preview/generated';

const SOCIAL_PREVIEW_PUBLIC_PATH =
    '/social-preview/generated';


/**
 * ---------------------------------------------------------
 * RESPONSE
 * ---------------------------------------------------------
 */

function respond(
    array $data,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/**
 * ---------------------------------------------------------
 * CONFIG
 * ---------------------------------------------------------
 */

function load_config(): array
{
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    try {
        $config = require CONFIG_FILE;
    } catch (Throwable $e) {
        return [];
    }

    return is_array($config)
        ? $config
        : [];
}


function require_auth(): void
{
    $config = load_config();

    $expectedUser = trim(
        (string) (
            $config['grand_plus_admin_user']
            ?? ''
        )
    );

    $expectedPassword = (string) (
        $config['grand_plus_admin_password']
        ?? ''
    );

    if (
        $expectedUser === '' ||
        $expectedPassword === ''
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Configuration administrateur absente.',
            ],
            500
        );
    }

    $user =
        $_SERVER['PHP_AUTH_USER']
        ?? '';

    $password =
        $_SERVER['PHP_AUTH_PW']
        ?? '';

    if (
        !hash_equals(
            $expectedUser,
            (string) $user
        ) ||
        !hash_equals(
            $expectedPassword,
            (string) $password
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ Social Studio"'
        );

        respond(
            [
                'success' => false,
                'message' =>
                    'Authentification requise.',
            ],
            401
        );
    }
}


/**
 * ---------------------------------------------------------
 * SOCIAL CONTENT STORAGE
 * ---------------------------------------------------------
 */

function read_contents(): array
{
    if (!is_file(SOCIAL_FILE)) {
        return [];
    }

    $raw = @file_get_contents(
        SOCIAL_FILE
    );

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $data = json_decode(
        $raw,
        true
    );

    return is_array($data)
        ? $data
        : [];
}


function write_contents(
    array $contents
): void {
    if (!is_dir(SOCIAL_DIR)) {
        if (
            !mkdir(
                SOCIAL_DIR,
                0755,
                true
            )
        ) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Impossible de créer le dossier social.',
                ],
                500
            );
        }
    }

    $json = json_encode(
        array_values($contents),
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if (
        $json === false ||
        @file_put_contents(
            SOCIAL_FILE,
            $json,
            LOCK_EX
        ) === false
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer les contenus.',
            ],
            500
        );
    }
}


/**
 * ---------------------------------------------------------
 * REQUEST
 * ---------------------------------------------------------
 */

function request_json(): array
{
    $raw =
        file_get_contents('php://input');

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $data = json_decode(
        $raw,
        true
    );

    return is_array($data)
        ? $data
        : [];
}


function clean(
    mixed $value
): string {
    return trim(
        (string) $value
    );
}


function config_string(
    array $config,
    string $key
): string {
    return trim(
        (string) (
            $config[$key]
            ?? ''
        )
    );
}


/**
 * ---------------------------------------------------------
 * CURL
 * ---------------------------------------------------------
 */

function curl_request(
    string $url,
    string $method = 'GET',
    array $fields = [],
    array $headers = [],
    int $timeout = 60
): array {
    $ch = curl_init();

    if ($ch === false) {
        throw new RuntimeException(
            'Impossible d’initialiser cURL.'
        );
    }

    $method = strtoupper($method);

    $options = [
        CURLOPT_URL =>
            $url,

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_FOLLOWLOCATION =>
            true,

        CURLOPT_MAXREDIRS =>
            5,

        CURLOPT_CONNECTTIMEOUT =>
            15,

        CURLOPT_TIMEOUT =>
            $timeout,

        CURLOPT_CUSTOMREQUEST =>
            $method,

        CURLOPT_HTTPHEADER =>
            $headers,

        CURLOPT_USERAGENT =>
            'VitrinePlus-SocialStudio/1.0',
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

    $body = curl_exec($ch);

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
 * ---------------------------------------------------------
 * GEMINI
 * ---------------------------------------------------------
 *
 * Gemini est utilisé uniquement côté serveur.
 *
 * La clé n'est jamais envoyée au navigateur.
 *
 * ---------------------------------------------------------
 */

function extract_gemini_text(
    array $response
): string {
    $parts = [];

    if (
        isset($response['candidates']) &&
        is_array($response['candidates'])
    ) {
        foreach (
            $response['candidates']
            as $candidate
        ) {
            if (
                !is_array($candidate)
            ) {
                continue;
            }

            if (
                !isset($candidate['content']) ||
                !is_array($candidate['content'])
            ) {
                continue;
            }

            $content =
                $candidate['content'];

            if (
                !isset($content['parts']) ||
                !is_array($content['parts'])
            ) {
                continue;
            }

            foreach (
                $content['parts']
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
        respond(
            [
                'success' => false,
                'message' =>
                    'La clé Gemini n’est pas configurée dans vitrine-mail-config.php.',
            ],
            500
        );
    }

    $model =
        config_string(
            $config,
            'gemini_model'
        );

    if ($model === '') {
        $model =
            'gemini-3.8-flash';
    }

    $model =
        preg_replace(
            '#^models/#',
            '',
            $model
        );

    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/' .
        rawurlencode($model) .
        ':generateContent';

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => $prompt,
                    ],
                ],
            ],
        ],

        'generationConfig' => [
            'temperature' => 0.85,

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

                    'title' => [
                        'type' => 'STRING',
                    ],
                ],

                'required' => [
                    'caption',
                    'slides',
                    'script',
                    'title',
                ],
            ],
        ],
    ];

    $ch =
        curl_init($url);

    if ($ch === false) {
        throw new RuntimeException(
            'Impossible d’initialiser la connexion Gemini.'
        );
    }

    curl_setopt_array(
        $ch,
        [
            CURLOPT_POST =>
                true,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                15,

            CURLOPT_TIMEOUT =>
                90,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],

            CURLOPT_POSTFIELDS =>
                json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),
        ]
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
            'Erreur de connexion Gemini : ' .
            (
                $curlError !== ''
                    ? $curlError
                    : 'échec cURL.'
            )
        );
    }

    $response =
        json_decode(
            $body,
            true
        );

    if (!is_array($response)) {
        throw new RuntimeException(
            'Gemini a retourné une réponse invalide.'
        );
    }

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        $message =
            $response['error']['message']
            ?? 'Erreur Gemini inconnue.';

        throw new RuntimeException(
            'Gemini : ' .
            $message
        );
    }

    if (
        isset($response['promptFeedback']['blockReason'])
    ) {
        throw new RuntimeException(
            'Gemini a bloqué la génération : ' .
            (
                $response['promptFeedback']['blockReason']
            )
        );
    }

    $text =
        extract_gemini_text(
            $response
        );

    if ($text === '') {
        throw new RuntimeException(
            'Gemini n’a retourné aucun texte.'
        );
    }

    return $text;
}


/**
 * ---------------------------------------------------------
 * JSON GEMINI
 * ---------------------------------------------------------
 */

function clean_ai_json(
    string $raw
): string {
    $raw =
        trim($raw);

    if ($raw === '') {
        return '';
    }

    /*
     * Suppression éventuelle d'un bloc Markdown.
     */

    if (
        str_starts_with(
            $raw,
            '```'
        )
    ) {
        $raw =
            preg_replace(
                '/^```(?:json)?\s*/i',
                '',
                $raw
            );

        $raw =
            preg_replace(
                '/\s*```$/',
                '',
                (string) $raw
            );
    }

    $raw =
        trim(
            (string) $raw
        );

    /*
     * Gemini est configuré en JSON.
     * Cette partie sert simplement de garde-fou
     * si du texte parasite apparaît malgré tout.
     */

    $firstBrace =
        strpos(
            $raw,
            '{'
        );

    $lastBrace =
        strrpos(
            $raw,
            '}'
        );

    if (
        $firstBrace !== false &&
        $lastBrace !== false &&
        $lastBrace > $firstBrace
    ) {
        $raw =
            substr(
                $raw,
                $firstBrace,
                $lastBrace - $firstBrace + 1
            );
    }

    return trim($raw);
}


/**
 * ---------------------------------------------------------
 * GENERATION IA
 * ---------------------------------------------------------
 */

function generate_content(
    string $type,
    string $topic,
    string $objective
): array {
    $formatInstructions =
        match ($type) {
            'carousel' => <<<TXT
Tu dois produire un carrousel Instagram de 6 à 8 slides.

Chaque slide doit être courte, lisible et avoir une idée forte.

Le premier slide doit fonctionner comme un HOOK.

Le dernier slide doit comporter un CTA.
TXT,

            'reel' => <<<TXT
Tu dois produire un script de Reel Instagram de 30 à 60 secondes.

Le script doit être découpé en scènes courtes avec :

HOOK
Développement
Preuve, exemple ou conseil
CTA final

Le champ "caption" doit contenir la légende Instagram du Reel.

Le champ "script" doit contenir le script complet.
TXT,

            'story' => <<<TXT
Tu dois produire une séquence Story de 5 à 7 écrans.

Chaque écran doit contenir une phrase courte.

Le dernier écran doit avoir un CTA.

Le champ "slides" doit contenir les différents écrans.
TXT,

            default => <<<TXT
Tu dois produire une publication Instagram classique.

Le contenu doit être directement publiable.

Le champ "slides" doit rester vide.
Le champ "script" doit rester vide.
TXT,
        };

    $prompt = <<<PROMPT
Tu es le directeur éditorial et social media de Vitrine+.

MARQUE

Vitrine+ est une agence digitale française.

Promesse :
« Votre entreprise. En mieux. »

POSITIONNEMENT

Vitrine+ accompagne les entrepreneurs, artisans,
commerçants et entreprises dans leur présence en ligne.

Vitrine+ propose notamment :

- création de sites internet professionnels ;
- refonte de sites internet ;
- sites vitrines ;
- identité visuelle ;
- logos ;
- marketing digital ;
- accompagnement numérique.

OBJECTIF DU CONTENU

{$objective}

SUJET DEMANDÉ PAR L'UTILISATEUR

{$topic}

FORMAT

{$type}

{$formatInstructions}

TON DE MARQUE

- français naturel ;
- professionnel mais humain ;
- moderne ;
- premium ;
- direct ;
- intelligent ;
- accessible ;
- jamais robotique ;
- jamais générique ;
- jamais excessivement commercial ;
- pas de jargon inutile ;
- pas de phrases creuses.

RÈGLES IMPORTANTES

- Le début doit immédiatement donner envie de continuer.
- Utilise des paragraphes courts.
- Utilise quelques emojis seulement lorsqu'ils apportent quelque chose.
- Termine par un CTA cohérent avec l'objectif.
- Ajoute 5 à 10 hashtags réellement pertinents.
- Les hashtags doivent être intégrés à la fin de "caption".
- N'invente aucun chiffre.
- N'invente aucun résultat client.
- N'invente aucun témoignage.
- N'invente aucune fonctionnalité de Vitrine+.
- Ne prétends pas qu'un client existe si l'utilisateur ne l'a pas mentionné.
- Ne mets pas de guillemets autour de la publication.
- Ne commence pas par « Voici votre publication ».
- Le contenu doit être directement utilisable par Vitrine+.
- Évite les formulations typiques d'une IA.
- Écris comme un excellent directeur social media français.

POUR VITRINE+

Le contenu doit chercher à donner envie aux entrepreneurs
de suivre Vitrine+ et, lorsque c'est pertinent,
de découvrir ses services.

Il ne faut pas transformer chaque publication
en publicité agressive.

La priorité est :

1. apporter de la valeur ;
2. créer de l'intérêt ;
3. donner envie de suivre Vitrine+ ;
4. créer naturellement une opportunité commerciale.

FORMAT DE RÉPONSE

Réponds exclusivement avec l'objet JSON demandé par le schéma.

Le champ "caption" doit contenir la publication complète.

Le champ "slides" doit contenir uniquement les textes
des slides lorsqu'il s'agit d'un carousel ou d'une story.

Le champ "script" doit contenir uniquement le script
lorsqu'il s'agit d'un Reel.

Le champ "title" doit être un titre interne court
permettant d'identifier le contenu dans le Social Studio.

PROMPT;


/**
 * Appel Gemini.
 */

    try {
        $raw =
            call_gemini(
                $prompt
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

    $raw =
        clean_ai_json(
            $raw
        );

    $decoded =
        json_decode(
            $raw,
            true
        );

    if (
        !is_array($decoded)
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Gemini a retourné un format JSON inattendu.',
                'raw' =>
                    $raw,
            ],
            500
        );
    }

    $caption =
        trim(
            (string) (
                $decoded['caption']
                ?? ''
            )
        );

    if ($caption === '') {
        respond(
            [
                'success' => false,
                'message' =>
                    'Gemini n’a généré aucune légende.',
            ],
            500
        );
    }

    $slides = [];

    if (
        isset($decoded['slides']) &&
        is_array($decoded['slides'])
    ) {
        foreach (
            $decoded['slides']
            as $slide
        ) {
            $slide =
                trim(
                    (string) $slide
                );

            if ($slide !== '') {
                $slides[] =
                    $slide;
            }
        }
    }

    $script =
        trim(
            (string) (
                $decoded['script']
                ?? ''
            )
        );

    $title =
        trim(
            (string) (
                $decoded['title']
                ?? ''
            )
        );

    return [
        'id' =>
            'social-' .
            date('YmdHis') .
            '-' .
            bin2hex(
                random_bytes(4)
            ),

        'type' =>
            $type,

        'title' =>
            $title,

        'topic' =>
            $topic,

        'objective' =>
            $objective,

        'caption' =>
            $caption,

        'slides' =>
            $slides,

        'script' =>
            $script,

        'status' =>
            'draft',

        'scheduledAt' =>
            '',

        'createdAt' =>
            date('c'),

        'updatedAt' =>
            date('c'),
    ];
}


/**
 * ---------------------------------------------------------
 * VISUEL VITRINE+
 * ---------------------------------------------------------
 */

function wrap_text(
    string $text,
    int $maxLength
): array {
    $words =
        preg_split(
            '/\s+/u',
            trim($text)
        );

    if (!is_array($words)) {
        return [$text];
    }

    $lines = [];

    $current = '';

    foreach (
        $words
        as $word
    ) {
        $candidate =
            $current === ''
                ? $word
                : $current . ' ' . $word;

        if (
            mb_strlen(
                $candidate
            ) > $maxLength
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

    return $lines;
}


function generate_visual(
    string $type,
    string $topic,
    string $caption
): string {
    if (
        !function_exists(
            'imagecreatetruecolor'
        )
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'PHP GD n’est pas disponible sur l’hébergement. Le visuel automatique ne peut pas être créé.',
            ],
            500
        );
    }

    if (!is_dir(SOCIAL_PREVIEW_DIR)) {
        if (
            !mkdir(
                SOCIAL_PREVIEW_DIR,
                0755,
                true
            )
        ) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Impossible de créer le dossier des visuels.',
                ],
                500
            );
        }
    }

    $width = 1080;
    $height = 1080;

    $image =
        imagecreatetruecolor(
            $width,
            $height
        );

    if ($image === false) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible de créer le visuel.',
            ],
            500
        );
    }

    $background =
        imagecolorallocate(
            $image,
            10,
            10,
            10
        );

    $gold =
        imagecolorallocate(
            $image,
            200,
            164,
            93
        );

    $white =
        imagecolorallocate(
            $image,
            245,
            245,
            245
        );

    $muted =
        imagecolorallocate(
            $image,
            155,
            155,
            155
        );

    imagefill(
        $image,
        0,
        0,
        $background
    );

    imagefilledrectangle(
        $image,
        0,
        0,
        $width,
        12,
        $gold
    );

    imagefilledrectangle(
        $image,
        0,
        $height - 12,
        $width,
        $height,
        $gold
    );

    $title =
        $topic !== ''
            ? $topic
            : 'Votre entreprise. En mieux.';

    $titleLines =
        wrap_text(
            $title,
            26
        );

    $fontPath = null;

    $possibleFonts = [
        BASE_DIR . '/fonts/Inter-Bold.ttf',

        BASE_DIR . '/fonts/Montserrat-Bold.ttf',

        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ];

    foreach (
        $possibleFonts
        as $candidate
    ) {
        if (is_file($candidate)) {
            $fontPath =
                $candidate;

            break;
        }
    }

    if ($fontPath !== null) {
        $fontSize = 52;

        $y = 350;

        foreach (
            array_slice(
                $titleLines,
                0,
                5
            )
            as $line
        ) {
            imagettftext(
                $image,
                $fontSize,
                0,
                80,
                $y,
                $white,
                $fontPath,
                $line
            );

            $y += 72;
        }

        imagettftext(
            $image,
            24,
            0,
            80,
            860,
            $gold,
            $fontPath,
            'VITRINE+'
        );

        imagettftext(
            $image,
            22,
            0,
            80,
            910,
            $muted,
            $fontPath,
            'Votre entreprise. En mieux.'
        );
    } else {
        imagestring(
            $image,
            5,
            80,
            360,
            mb_substr(
                $title,
                0,
                70
            ),
            $white
        );

        imagestring(
            $image,
            5,
            80,
            860,
            'VITRINE+',
            $gold
        );
    }

    $filename =
        'social-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(5)
        ) .
        '.jpg';

    $absolute =
        SOCIAL_PREVIEW_DIR .
        '/' .
        $filename;

    if (
        !imagejpeg(
            $image,
            $absolute,
            92
        )
    ) {
        imagedestroy($image);

        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer le visuel.',
            ],
            500
        );
    }

    imagedestroy($image);

    $config =
        load_config();

    $baseUrl =
        config_string(
            $config,
            'public_base_url'
        );

    if ($baseUrl === '') {
        $baseUrl =
            'https://' .
            (
                $_SERVER['HTTP_HOST']
                ?? 'vitrineplus.fr'
            );
    }

    $baseUrl =
        rtrim(
            $baseUrl,
            '/'
        );

    return
        $baseUrl .
        SOCIAL_PREVIEW_PUBLIC_PATH .
        '/' .
        rawurlencode($filename);
}


/**
 * ---------------------------------------------------------
 * INSTAGRAM
 * ---------------------------------------------------------
 */

function instagram_api_version(
    array $config
): string {
    $version =
        config_string(
            $config,
            'instagram_api_version'
        );

    if ($version === '') {
        $version =
            'v24.0';
    }

    if (
        !str_starts_with(
            $version,
            'v'
        )
    ) {
        $version =
            'v' . $version;
    }

    return $version;
}


function instagram_token(
    array $config
): string {
    return config_string(
        $config,
        'instagram_access_token'
    );
}


function get_instagram_account(
    string $token,
    string $apiVersion
): array {
    $url =
        'https://graph.instagram.com/' .
        $apiVersion .
        '/me?' .
        http_build_query(
            [
                'fields' =>
                    'id,username',

                'access_token' =>
                    $token,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    try {
        $result =
            curl_request(
                $url,
                'GET',
                [],
                [],
                30
            );
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Impossible de contacter Instagram : ' .
            $e->getMessage()
        );
    }

    if (
        $result['http_code'] < 200 ||
        $result['http_code'] >= 300
    ) {
        $message =
            $result['body']['error']['message']
            ?? 'Instagram a refusé la requête.';

        throw new RuntimeException(
            'Instagram : ' .
            $message
        );
    }

    $id =
        trim(
            (string) (
                $result['body']['id']
                ?? ''
            )
        );

    if ($id === '') {
        throw new RuntimeException(
            'Instagram n’a retourné aucun identifiant de compte.'
        );
    }

    return [
        'id' =>
            $id,

        'username' =>
            trim(
                (string) (
                    $result['body']['username']
                    ?? ''
                )
            ),
    ];
}


function wait_for_container(
    string $containerId,
    string $token,
    string $apiVersion
): array {
    $last = [];

    for (
        $attempt = 0;
        $attempt < 12;
        $attempt++
    ) {
        $url =
            'https://graph.instagram.com/' .
            $apiVersion .
            '/' .
            rawurlencode(
                $containerId
            ) .
            '?' .
            http_build_query(
                [
                    'fields' =>
                        'status_code,status',

                    'access_token' =>
                        $token,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );

        $result =
            curl_request(
                $url,
                'GET',
                [],
                [],
                30
            );

        $last =
            $result['body'];

        $statusCode =
            strtoupper(
                trim(
                    (string) (
                        $result['body']['status_code']
                        ?? ''
                    )
                )
            );

        if (
            $statusCode === 'FINISHED'
        ) {
            return $result['body'];
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
                'Instagram : ' .
                (
                    $result['body']['status']
                    ?? 'Le container Instagram a échoué.'
                )
            );
        }

        sleep(2);
    }

    throw new RuntimeException(
        'Instagram n’a pas terminé le traitement du média. Statut : ' .
        (
            $last['status']
            ?? 'inconnu'
        )
    );
}


function publish_instagram_image(
    string $imageUrl,
    string $caption,
    string $type
): array {
    $config =
        load_config();

    $token =
        instagram_token(
            $config
        );

    if ($token === '') {
        throw new RuntimeException(
            'Le token Instagram n’est pas configuré.'
        );
    }

    $apiVersion =
        instagram_api_version(
            $config
        );

    $account =
        get_instagram_account(
            $token,
            $apiVersion
        );

    $accountId =
        $account['id'];

    $mediaType = '';

    if ($type === 'story') {
        $mediaType =
            'STORIES';
    }

    $fields = [
        'image_url' =>
            $imageUrl,

        'caption' =>
            $caption,

        'access_token' =>
            $token,
    ];

    if ($mediaType !== '') {
        $fields['media_type'] =
            $mediaType;
    }

    $containerUrl =
        'https://graph.instagram.com/' .
        $apiVersion .
        '/' .
        rawurlencode(
            $accountId
        ) .
        '/media';

    try {
        $container =
            curl_request(
                $containerUrl,
                'POST',
                $fields,
                [],
                60
            );
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Erreur lors de la création du média Instagram : ' .
            $e->getMessage()
        );
    }

    if (
        $container['http_code'] < 200 ||
        $container['http_code'] >= 300 ||
        empty(
            $container['body']['id']
        )
    ) {
        $metaMessage =
            $container['body']['error']['message']
            ?? 'Instagram n’a pas retourné de container.';

        $metaType =
            $container['body']['error']['type']
            ?? '';

        $metaCode =
            $container['body']['error']['code']
            ?? '';

        $details =
            'Instagram : ' .
            $metaMessage;

        if ($metaType !== '') {
            $details .=
                ' | Type : ' .
                $metaType;
        }

        if ($metaCode !== '') {
            $details .=
                ' | Code : ' .
                $metaCode;
        }

        throw new RuntimeException(
            $details
        );
    }

    $containerId =
        (string) (
            $container['body']['id']
        );

    $status =
        wait_for_container(
            $containerId,
            $token,
            $apiVersion
        );

    $publishUrl =
        'https://graph.instagram.com/' .
        $apiVersion .
        '/' .
        rawurlencode(
            $accountId
        ) .
        '/media_publish';

    try {
        $publish =
            curl_request(
                $publishUrl,
                'POST',
                [
                    'creation_id' =>
                        $containerId,

                    'access_token' =>
                        $token,
                ],
                [],
                60
            );
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Erreur lors de la publication Instagram : ' .
            $e->getMessage()
        );
    }

    if (
        $publish['http_code'] < 200 ||
        $publish['http_code'] >= 300 ||
        empty(
            $publish['body']['id']
        )
    ) {
        $metaMessage =
            $publish['body']['error']['message']
            ?? 'Instagram n’a pas publié le média.';

        throw new RuntimeException(
            'Instagram : ' .
            $metaMessage
        );
    }

    return [
        'account' =>
            $account,

        'account_id' =>
            $accountId,

        'container_id' =>
            $containerId,

        'container_status' =>
            $status,

        'media_id' =>
            $publish['body']['id'],
    ];
}


/**
 * ---------------------------------------------------------
 * ROUTER
 * ---------------------------------------------------------
 */

require_auth();

$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';


/**
 * ---------------------------------------------------------
 * GET
 * ---------------------------------------------------------
 */

if ($method === 'GET') {
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
            'success' =>
                true,

            'contents' =>
                $contents,
        ]
    );
}


if ($method !== 'POST') {
    respond(
        [
            'success' =>
                false,

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
 * ---------------------------------------------------------
 * LIST
 * ---------------------------------------------------------
 */

if ($action === 'list') {
    $contents =
        read_contents();

    respond(
        [
            'success' =>
                true,

            'contents' =>
                $contents,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * GENERATE
 * ---------------------------------------------------------
 */

if ($action === 'generate') {
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
                'success' =>
                    false,

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
                'success' =>
                    false,

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
            'success' =>
                true,

            'content' =>
                $content,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * GENERATE VISUAL
 * ---------------------------------------------------------
 */

if ($action === 'generate_visual') {
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

    try {
        $url =
            generate_visual(
                $type,
                $topic,
                $caption
            );
    } catch (Throwable $e) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    $e->getMessage(),
            ],
            500
        );
    }

    respond(
        [
            'success' =>
                true,

            'url' =>
                $url,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * SAVE
 * ---------------------------------------------------------
 */

if ($action === 'save') {
    $content =
        $input['content']
        ?? null;

    if (
        !is_array($content)
    ) {
        respond(
            [
                'success' =>
                    false,

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

    $content['mediaUrl'] =
        clean(
            $content['mediaUrl']
            ?? ''
        );

    $content['status'] =
        clean(
            $input['status']
            ?? 'draft'
        );

    $content['scheduledAt'] =
        clean(
            $input['scheduled_at']
            ?? ''
        );

    if (
        !isset($content['slides']) ||
        !is_array(
            $content['slides']
        )
    ) {
        $content['slides'] =
            [];
    }

    if (
        !isset($content['script'])
    ) {
        $content['script'] =
            '';
    }

    if (
        !isset($content['createdAt']) ||
        clean(
            $content['createdAt']
        ) === ''
    ) {
        $content['createdAt'] =
            date('c');
    }

    $content['updatedAt'] =
        date('c');

    $found =
        false;

    foreach (
        $contents
        as $index => $existing
    ) {
        if (
            (
                $existing['id']
                ?? ''
            )
            === $contentId
        ) {
            $contents[$index] =
                $content;

            $found =
                true;

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
            'success' =>
                true,

            'content' =>
                $content,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * DELETE
 * ---------------------------------------------------------
 */

if ($action === 'delete') {
    $id =
        clean(
            $input['id']
            ?? ''
        );

    if ($id === '') {
        respond(
            [
                'success' =>
                    false,

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
                        )
                        !== $id
                    );
                }
            )
        );

    write_contents(
        $contents
    );

    respond(
        [
            'success' =>
                true,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * PUBLISH
 * ---------------------------------------------------------
 */

if ($action === 'publish') {
    $caption =
        trim(
            (string) (
                $input['caption']
                ?? ''
            )
        );

    $image =
        clean(
            $input['image']
            ?? ''
        );

    $type =
        clean(
            $input['type']
            ?? 'post'
        );

    if ($caption === '') {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'La légende est vide.',
            ],
            422
        );
    }

    if ($image === '') {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Aucun visuel public n’a été fourni.',
            ],
            422
        );
    }

    if (
        !filter_var(
            $image,
            FILTER_VALIDATE_URL
        )
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'L’URL du visuel est invalide.',
            ],
            422
        );
    }

    if (
        !in_array(
            $type,
            [
                'post',
                'story',
            ],
            true
        )
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'La publication automatique de ce format nécessite encore un média vidéo ou un système de carrousel dédié.',
            ],
            422
        );
    }

    try {
        $result =
            publish_instagram_image(
                $image,
                $caption,
                $type
            );
    } catch (Throwable $e) {
        respond(
            [
                'success' =>
                    false,

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

    if ($contentId !== '') {
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
                )
                === $contentId
            ) {
                $contents[$index]['status'] =
                    'published';

                $contents[$index]['updatedAt'] =
                    date('c');

                $contents[$index]['instagram'] =
                    $result;

                break;
            }
        }

        write_contents(
            $contents
        );
    }

    respond(
        [
            'success' =>
                true,

            'message' =>
                'Publication publiée sur Instagram.',

            'result' =>
                $result,
        ]
    );
}


/**
 * ---------------------------------------------------------
 * UNKNOWN ACTION
 * ---------------------------------------------------------
 */

respond(
    [
        'success' =>
            false,

        'message' =>
            'Action inconnue.',
    ],
    400
);