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


/*
|--------------------------------------------------------------------------
| RÉPONSE JSON
|--------------------------------------------------------------------------
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


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
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


function config_string(
    array $config,
    string $key
): string {

    return trim(
        (string) (
            $config[$key] ?? ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/

function require_auth(): void
{
    $config = load_config();

    $expectedUser = config_string(
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


/*
|--------------------------------------------------------------------------
| JSON STORAGE
|--------------------------------------------------------------------------
*/

function read_contents(): array
{
    if (!is_file(SOCIAL_FILE)) {
        return [];
    }

    $raw =
        @file_get_contents(
            SOCIAL_FILE
        );

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $data =
        json_decode(
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

    $json =
        json_encode(
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


/*
|--------------------------------------------------------------------------
| REQUÊTE JSON
|--------------------------------------------------------------------------
*/

function request_json(): array
{
    $raw =
        file_get_contents(
            'php://input'
        );

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $data =
        json_decode(
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


/*
|--------------------------------------------------------------------------
| CURL
|--------------------------------------------------------------------------
*/

function curl_request(
    string $url,
    string $method = 'GET',
    array $fields = [],
    array $headers = [],
    int $timeout = 60
): array {

    $ch =
        curl_init();

    if ($ch === false) {

        throw new RuntimeException(
            'Impossible d’initialiser cURL.'
        );
    }

    $method =
        strtoupper($method);

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
            'VitrinePlus-SocialStudio/2.0',
    ];

    if (
        $method === 'POST' &&
        !empty($fields)
    ) {

        $options[
            CURLOPT_POSTFIELDS
        ] =
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

        'http_code' =>
            $httpCode,

        'body' =>
            $decoded,

        'raw' =>
            $body,
    ];
}


/*
|--------------------------------------------------------------------------
| GEMINI
|--------------------------------------------------------------------------
|
| Aucun appel OpenAI.
|
| Le serveur essaie plusieurs modèles Gemini
| afin de rester fonctionnel lorsqu'un modèle
| gratuit est temporairement indisponible.
|--------------------------------------------------------------------------
*/

function extract_gemini_text(
    array $response
): string {

    if (
        isset(
            $response['candidates'][0]['content']['parts']
        ) &&
        is_array(
            $response['candidates'][0]['content']['parts']
        )
    ) {

        $parts = [];

        foreach (
            $response['candidates'][0]['content']['parts']
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

        return trim(
            implode(
                "\n",
                $parts
            )
        );
    }

    return '';
}


function gemini_models(
    array $config
): array {

    $configured =
        config_string(
            $config,
            'gemini_model'
        );

    $models = [];

    if ($configured !== '') {

        $models[] =
            $configured;
    }

    $defaults = [

        'gemini-3.8-flash',

        'gemini-3.7-flash',

        'gemini-3.6-flash',

        'gemini-3.5-flash',

        'gemini-3.1-flash-lite',

        'gemini-2.5-flash-lite',
    ];

    foreach ($defaults as $model) {

        if (
            !in_array(
                $model,
                $models,
                true
            )
        ) {

            $models[] =
                $model;
        }
    }

    return $models;
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

    $models =
        gemini_models(
            $config
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

                    'role' =>
                        'user',

                    'parts' => [

                        [

                            'text' =>
                                $prompt,

                        ],

                    ],

                ],

            ],

            'generationConfig' => [

                'temperature' =>
                    0.85,

                'responseMimeType' =>
                    'application/json',

                'responseSchema' => [

                    'type' =>
                        'OBJECT',

                    'properties' => [

                        'caption' => [

                            'type' =>
                                'STRING',

                        ],

                        'slides' => [

                            'type' =>
                                'ARRAY',

                            'items' => [

                                'type' =>
                                    'STRING',

                            ],

                        ],

                        'script' => [

                            'type' =>
                                'STRING',

                        ],

                        'title' => [

                            'type' =>
                                'STRING',

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

            $lastError =
                'Impossible d’initialiser la connexion Gemini.';

            continue;
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

                    'x-goog-api-key: ' .
                        $apiKey,

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

            $lastError =
                'Erreur de connexion Gemini : ' .
                (
                    $curlError !== ''
                        ? $curlError
                        : 'échec cURL.'
                );

            continue;
        }

        $response =
            json_decode(
                $body,
                true
            );

        if (
            !is_array($response)
        ) {

            $lastError =
                'Gemini a retourné une réponse invalide.';

            continue;
        }

        if (
            $httpCode >= 200 &&
            $httpCode < 300
        ) {

            $text =
                extract_gemini_text(
                    $response
                );

            if ($text !== '') {

                return $text;
            }

            $lastError =
                'Gemini n’a retourné aucun texte.';

            continue;
        }

        $message =
            $response['error']['message']
            ?? 'Erreur Gemini inconnue.';

        $lastError =
            'Gemini (' .
            $model .
            ') : ' .
            $message;

        /*
        |--------------------------------------------------------------------------
        | On essaie automatiquement le modèle suivant
        |--------------------------------------------------------------------------
        */

        if (
            $httpCode === 400 ||
            $httpCode === 401 ||
            $httpCode === 403 ||
            $httpCode === 404 ||
            $httpCode === 429 ||
            $httpCode >= 500
        ) {

            continue;
        }

        break;
    }

    throw new RuntimeException(
        $lastError
    );
}


/*
|--------------------------------------------------------------------------
| NETTOYAGE JSON IA
|--------------------------------------------------------------------------
*/

function clean_ai_json(
    string $text
): string {

    $text =
        trim($text);

    if (
        str_starts_with(
            $text,
            '```'
        )
    ) {

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
                (string) $text
            );
    }

    return trim(
        (string) $text
    );
}


/*
|--------------------------------------------------------------------------
| GÉNÉRATION DU CONTENU
|--------------------------------------------------------------------------
*/

function generate_content(
    string $type,
    string $topic,
    string $objective
): array {

    $formatInstructions =
        match ($type) {

            'carousel' => <<<'TXT'
Tu dois produire un carrousel Instagram de 6 à 8 slides.

Chaque slide doit être courte, lisible et avoir une idée forte.

Le premier slide doit servir de HOOK.

Le dernier slide doit contenir un CTA.
TXT,

            'reel' => <<<'TXT'
Tu dois produire un script de Reel Instagram de 30 à 60 secondes.

Le script doit être découpé en scènes courtes avec :

HOOK
DÉVELOPPEMENT
PREUVE OU CONSEIL
CTA FINAL
TXT,

            'story' => <<<'TXT'
Tu dois produire une séquence Story de 5 à 7 écrans.

Chaque écran doit contenir une phrase très courte.

Le dernier écran doit avoir un CTA.
TXT,

            default => <<<'TXT'
Tu dois produire une publication Instagram classique.

La publication doit avoir une accroche forte,
un développement utile et un CTA naturel.
TXT,
        };

    $prompt = <<<PROMPT
Tu es le directeur éditorial et social media de Vitrine+.

MARQUE

Vitrine+ est une agence digitale française.

Promesse :

« Votre entreprise. En mieux. »

POSITIONNEMENT

Vitrine+ aide les entrepreneurs, artisans et entreprises
à améliorer leur présence en ligne.

Vitrine+ propose notamment :

- création de sites internet ;
- refonte de sites ;
- identité visuelle ;
- logos ;
- SEO ;
- marketing digital ;
- accompagnement numérique.

TON

- français naturel ;
- professionnel mais humain ;
- moderne ;
- premium ;
- direct ;
- intelligent ;
- concret ;
- jamais générique ;
- jamais robotique.

OBJECTIF

Le contenu doit donner envie de suivre Vitrine+,
de découvrir ses contenus et, lorsque c'est pertinent,
de faire appel à Vitrine+.

IMPORTANT

Ne pas transformer chaque publication en publicité.

Le contenu doit d'abord apporter de la valeur.

Ne pas écrire des phrases artificielles du type :

« Chez Vitrine+, nous sommes ravis... »

Évite les clichés marketing.

Évite les hashtags inutiles.

Sujet :

{$topic}

Objectif :

{$objective}

Format :

{$formatInstructions}

RÈGLES DE LA LÉGENDE

- commence par une accroche forte ;
- paragraphes courts ;
- lisibilité mobile ;
- emojis avec modération ;
- CTA naturel ;
- maximum environ 2 000 caractères ;
- hashtags pertinents uniquement ;
- maximum 8 hashtags.

RÈGLES DU CARROUSEL

Si le type est carousel :

- 6 à 8 slides ;
- slide 1 = hook ;
- slides suivantes = valeur ;
- dernier slide = CTA ;
- texte très court ;
- pas de paragraphe interminable.

RÈGLES DU REEL

Si le type est reel :

- hook dans les 3 premières secondes ;
- rythme rapide ;
- phrases parlées naturelles ;
- indications visuelles simples ;
- CTA final.

RÈGLES DE LA STORY

Si le type est story :

- 5 à 7 écrans ;
- texte très court ;
- progression logique ;
- dernier écran = CTA.

RÉPONSE

Retourne UNIQUEMENT un JSON valide.

Structure exacte :

{
  "title": "titre interne du contenu",
  "caption": "légende Instagram complète",
  "slides": [
    "slide 1",
    "slide 2"
  ],
  "script": "script du Reel ou texte vide"
}

Si le contenu n'est pas un carrousel,
retourne "slides": [].

Si le contenu n'est pas un Reel,
retourne "script": "".
PROMPT;

    $text =
        call_gemini(
            $prompt
        );

    $text =
        clean_ai_json(
            $text
        );

    $decoded =
        json_decode(
            $text,
            true
        );

    if (
        !is_array($decoded)
    ) {

        throw new RuntimeException(
            'Gemini a retourné un JSON invalide.'
        );
    }

    $caption =
        clean(
            $decoded['caption']
            ?? ''
        );

    $title =
        clean(
            $decoded['title']
            ?? ''
        );

    $script =
        clean(
            $decoded['script']
            ?? ''
        );

    $slides = [];

    if (
        isset(
            $decoded['slides']
        ) &&
        is_array(
            $decoded['slides']
        )
    ) {

        foreach (
            $decoded['slides']
            as $slide
        ) {

            $slide =
                clean($slide);

            if (
                $slide !== ''
            ) {

                $slides[] =
                    $slide;
            }
        }
    }

    if (
        $caption === ''
    ) {

        throw new RuntimeException(
            'Gemini n’a généré aucune légende.'
        );
    }

    return [

        'title' =>
            $title !== ''
                ? $title
                : $topic,

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


/*
|--------------------------------------------------------------------------
| TEXTE VISUEL
|--------------------------------------------------------------------------
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

    if (
        !is_array($words)
    ) {

        return [
            $text
        ];
    }

    $lines = [];

    $current =
        '';

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

            if (
                $current !== ''
            ) {

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

    if (
        $current !== ''
    ) {

        $lines[] =
            $current;
    }

    return $lines;
}


/*
|--------------------------------------------------------------------------
| GÉNÉRATION VISUELLE
|--------------------------------------------------------------------------
*/

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

    if (
        !is_dir(
            SOCIAL_PREVIEW_DIR
        )
    ) {

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

    $width =
        1080;

    $height =
        1080;

    $image =
        imagecreatetruecolor(
            $width,
            $height
        );

    if (
        $image === false
    ) {

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

    /*
    |--------------------------------------------------------------------------
    | Ligne dorée
    |--------------------------------------------------------------------------
    */

    imagefilledrectangle(
        $image,
        80,
        80,
        210,
        86,
        $gold
    );

    /*
    |--------------------------------------------------------------------------
    | Titre
    |--------------------------------------------------------------------------
    */

    $title =
        $topic !== ''
            ? $topic
            : 'Vitrine+';

    $lines =
        wrap_text(
            $title,
            28
        );

    $fontPath =
        '';

    $fontCandidates = [

        BASE_DIR .
            '/fonts/Inter-Bold.ttf',

        BASE_DIR .
            '/fonts/Montserrat-Bold.ttf',

        BASE_DIR .
            '/assets/Inter-Bold.ttf',

        BASE_DIR .
            '/assets/Montserrat-Bold.ttf',

        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',

    ];

    foreach (
        $fontCandidates
        as $candidate
    ) {

        if (
            is_file($candidate)
        ) {

            $fontPath =
                $candidate;

            break;
        }
    }

    $y =
        360;

    if (
        $fontPath !== ''
    ) {

        foreach (
            array_slice(
                $lines,
                0,
                4
            )
            as $line
        ) {

            $fontSize =
                62;

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

            $y +=
                78;
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

    /*
    |--------------------------------------------------------------------------
    | Fichier
    |--------------------------------------------------------------------------
    */

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

        imagedestroy(
            $image
        );

        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer le visuel.',
            ],
            500
        );
    }

    imagedestroy(
        $image
    );

    $config =
        load_config();

    $baseUrl =
        config_string(
            $config,
            'public_base_url'
        );

    if (
        $baseUrl === ''
    ) {

        $host =
            $_SERVER['HTTP_HOST']
            ?? 'vitrineplus.fr';

        $baseUrl =
            'https://' .
            $host;
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
        rawurlencode(
            $filename
        );
}


/*
|--------------------------------------------------------------------------
| INSTAGRAM
|--------------------------------------------------------------------------
*/

function instagram_api_version(
    array $config
): string {

    $version =
        config_string(
            $config,
            'instagram_api_version'
        );

    if (
        $version === ''
    ) {

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
            'v' .
            $version;
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


/*
|--------------------------------------------------------------------------
| RÉSOLUTION AUTOMATIQUE DU COMPTE INSTAGRAM
|--------------------------------------------------------------------------
*/

function instagram_user_id(
    array $config
): string {

    $configured =
        config_string(
            $config,
            'instagram_account_id'
        );

    $token =
        instagram_token(
            $config
        );

    if (
        $token === ''
    ) {

        throw new RuntimeException(
            'Le token Instagram est absent.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Si un ID est explicitement configuré,
    | on l'utilise d'abord.
    |--------------------------------------------------------------------------
    */

    if (
        $configured !== ''
    ) {

        return $configured;
    }

    /*
    |--------------------------------------------------------------------------
    | Instagram Login : /me
    |--------------------------------------------------------------------------
    */

    $url =
        'https://graph.instagram.com/' .
        instagram_api_version($config) .
        '/me?fields=id,user_id,username&access_token=' .
        rawurlencode($token);

    $result =
        curl_request(
            $url,
            'GET',
            [],
            [],
            30
        );

    if (
        $result['http_code'] >= 200 &&
        $result['http_code'] < 300
    ) {

        $body =
            $result['body'];

        $id =
            clean(
                $body['id']
                ?? ''
            );

        if (
            $id !== ''
        ) {

            return $id;
        }
    }

    $message =
        $result['body']['error']['message']
        ?? 'Impossible de récupérer le compte Instagram associé au token.';

    throw new RuntimeException(
        'Instagram : ' .
        $message
    );
}


/*
|--------------------------------------------------------------------------
| INSTAGRAM — CRÉATION DU CONTAINER
|--------------------------------------------------------------------------
*/

function instagram_create_image_container(
    array $config,
    string $imageUrl,
    string $caption,
    string $mediaType = 'IMAGE'
): string {

    $token =
        instagram_token(
            $config
        );

    if (
        $token === ''
    ) {

        throw new RuntimeException(
            'Le token Instagram est absent.'
        );
    }

    $account =
        instagram_user_id(
            $config
        );

    $version =
        instagram_api_version(
            $config
        );

    $url =
        'https://graph.instagram.com/' .
        $version .
        '/' .
        rawurlencode($account) .
        '/media';

    $fields = [

        'image_url' =>
            $imageUrl,

        'caption' =>
            $caption,

        'access_token' =>
            $token,

    ];

    if (
        strtoupper($mediaType) === 'STORIES'
    ) {

        $fields['media_type'] =
            'STORIES';
    }

    $result =
        curl_request(
            $url,
            'POST',
            $fields,
            [],
            60
        );

    if (
        $result['http_code'] < 200 ||
        $result['http_code'] >= 300
    ) {

        $message =
            $result['body']['error']['message']
            ?? 'Impossible de créer le média Instagram.';

        $code =
            $result['body']['error']['code']
            ?? '';

        $subcode =
            $result['body']['error']['error_subcode']
            ?? '';

        $details =
            'Instagram : ' .
            $message;

        if (
            $code !== ''
        ) {

            $details .=
                ' (code ' .
                $code .
                ')';
        }

        if (
            $subcode !== ''
        ) {

            $details .=
                ' (sous-code ' .
                $subcode .
                ')';
        }

        throw new RuntimeException(
            $details
        );
    }

    $id =
        clean(
            $result['body']['id']
            ?? ''
        );

    if (
        $id === ''
    ) {

        throw new RuntimeException(
            'Instagram n’a retourné aucun identifiant de média.'
        );
    }

    return $id;
}


/*
|--------------------------------------------------------------------------
| INSTAGRAM — STATUT CONTAINER
|--------------------------------------------------------------------------
*/

function instagram_container_status(
    array $config,
    string $containerId
): array {

    $token =
        instagram_token(
            $config
        );

    $version =
        instagram_api_version(
            $config
        );

    $url =
        'https://graph.instagram.com/' .
        $version .
        '/' .
        rawurlencode($containerId) .
        '?fields=status_code,status&access_token=' .
        rawurlencode($token);

    $result =
        curl_request(
            $url,
            'GET',
            [],
            [],
            30
        );

    return
        $result['body'];
}


/*
|--------------------------------------------------------------------------
| INSTAGRAM — PUBLICATION
|--------------------------------------------------------------------------
*/

function instagram_publish_container(
    array $config,
    string $containerId
): array {

    $token =
        instagram_token(
            $config
        );

    $account =
        instagram_user_id(
            $config
        );

    $version =
        instagram_api_version(
            $config
        );

    $url =
        'https://graph.instagram.com/' .
        $version .
        '/' .
        rawurlencode($account) .
        '/media_publish';

    $result =
        curl_request(
            $url,
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

    if (
        $result['http_code'] < 200 ||
        $result['http_code'] >= 300
    ) {

        $message =
            $result['body']['error']['message']
            ?? 'Impossible de publier le média Instagram.';

        throw new RuntimeException(
            'Instagram : ' .
            $message
        );
    }

    return
        $result['body'];
}


/*
|--------------------------------------------------------------------------
| PUBLICATION INSTAGRAM COMPLÈTE
|--------------------------------------------------------------------------
*/

function publish_instagram_image(
    string $imageUrl,
    string $caption,
    string $type = 'post'
): array {

    $config =
        load_config();

    $token =
        instagram_token(
            $config
        );

    if (
        $token === ''
    ) {

        throw new RuntimeException(
            'Instagram n’est pas configuré : token absent.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Vérification de l'URL
    |--------------------------------------------------------------------------
    */

    if (
        !filter_var(
            $imageUrl,
            FILTER_VALIDATE_URL
        )
    ) {

        throw new RuntimeException(
            'L’URL du visuel Instagram est invalide.'
        );
    }

    $mediaType =
        strtolower($type) === 'story'
            ? 'STORIES'
            : 'IMAGE';

    /*
    |--------------------------------------------------------------------------
    | Création
    |--------------------------------------------------------------------------
    */

    $containerId =
        instagram_create_image_container(
            $config,
            $imageUrl,
            $caption,
            $mediaType
        );

    /*
    |--------------------------------------------------------------------------
    | Attente du traitement Meta
    |--------------------------------------------------------------------------
    */

    $maxAttempts =
        10;

    for (
        $attempt = 0;
        $attempt < $maxAttempts;
        $attempt++
    ) {

        sleep(2);

        $status =
            instagram_container_status(
                $config,
                $containerId
            );

        $statusCode =
            strtoupper(
                clean(
                    $status['status_code']
                    ?? ''
                )
            );

        if (
            $statusCode === 'FINISHED'
        ) {

            break;
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

            $detail =
                clean(
                    $status['status']
                    ?? ''
                );

            throw new RuntimeException(
                'Instagram n’a pas pu traiter le média.' .
                (
                    $detail !== ''
                        ? ' ' . $detail
                        : ''
                )
            );
        }

        if (
            $attempt ===
            $maxAttempts - 1
        ) {

            throw new RuntimeException(
                'Instagram met trop longtemps à traiter le média. Réessaie dans quelques instants.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Publication
    |--------------------------------------------------------------------------
    */

    $published =
        instagram_publish_container(
            $config,
            $containerId
        );

    return [

        'containerId' =>
            $containerId,

        'mediaId' =>
            $published['id']
            ?? '',

    ];
}


/*
|--------------------------------------------------------------------------
| ROUTER
|--------------------------------------------------------------------------
*/

try {

    require_auth();

    $request =
        request_json();

    $action =
        clean(
            $request['action']
            ?? ''
        );

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'list'
    ) {

        respond(
            [
                'success' =>
                    true,

                'contents' =>
                    read_contents(),
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'generate'
    ) {

        $type =
            clean(
                $request['type']
                ?? 'post'
            );

        $topic =
            clean(
                $request['topic']
                ?? ''
            );

        $objective =
            clean(
                $request['objective']
                ?? 'Gagner en visibilité'
            );

        if (
            $topic === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'Indique le sujet du contenu.',
                ],
                400
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


    /*
    |--------------------------------------------------------------------------
    | GENERATE VISUAL
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'generate_visual'
    ) {

        $type =
            clean(
                $request['type']
                ?? 'post'
            );

        $topic =
            clean(
                $request['topic']
                ?? ''
            );

        $caption =
            clean(
                $request['caption']
                ?? ''
            );

        if (
            $topic === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'Le sujet du visuel est obligatoire.',
                ],
                400
            );
        }

        $imageUrl =
            generate_visual(
                $type,
                $topic,
                $caption
            );

        respond(
            [
                'success' =>
                    true,

                'imageUrl' =>
                    $imageUrl,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'save'
    ) {

        $contents =
            read_contents();

        $id =
            clean(
                $request['id']
                ?? ''
            );

        $type =
            clean(
                $request['type']
                ?? 'post'
            );

        $topic =
            clean(
                $request['topic']
                ?? ''
            );

        $objective =
            clean(
                $request['objective']
                ?? ''
            );

        $title =
            clean(
                $request['title']
                ?? $topic
            );

        $caption =
            clean(
                $request['caption']
                ?? ''
            );

        $script =
            clean(
                $request['script']
                ?? ''
            );

        $scheduledAt =
            clean(
                $request['scheduledAt']
                ?? ''
            );

        $imageUrl =
            clean(
                $request['imageUrl']
                ?? ''
            );

        $slides = [];

        if (
            isset(
                $request['slides']
            ) &&
            is_array(
                $request['slides']
            )
        ) {

            foreach (
                $request['slides']
                as $slide
            ) {

                $slide =
                    clean($slide);

                if (
                    $slide !== ''
                ) {

                    $slides[] =
                        $slide;
                }
            }
        }

        if (
            $caption === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'La légende est vide.',
                ],
                400
            );
        }

        if (
            $id === ''
        ) {

            $id =
                'social-' .
                date('YmdHis') .
                '-' .
                bin2hex(
                    random_bytes(4)
                );
        }

        $now =
            date('c');

        $existing =
            $contents[$id]
            ?? [];

        if (
            !is_array($existing)
        ) {

            $existing = [];
        }

        $content = array_merge(

            $existing,

            [

                'id' =>
                    $id,

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

                'imageUrl' =>
                    $imageUrl,

                'scheduledAt' =>
                    $scheduledAt,

                'status' =>
                    $scheduledAt !== ''
                        ? 'scheduled'
                        : (
                            $existing['status']
                            ?? 'draft'
                        ),

                'createdAt' =>
                    $existing['createdAt']
                    ?? $now,

                'updatedAt' =>
                    $now,

            ]
        );

        $contents[$id] =
            $content;

        write_contents(
            $contents
        );

        respond(
            [
                'success' =>
                    true,

                'content' =>
                    $content,

                'message' =>
                    'Contenu enregistré.',
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete'
    ) {

        $id =
            clean(
                $request['id']
                ?? ''
            );

        if (
            $id === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'Identifiant manquant.',
                ],
                400
            );
        }

        $contents =
            read_contents();

        if (
            isset(
                $contents[$id]
            )
        ) {

            unset(
                $contents[$id]
            );

            write_contents(
                $contents
            );
        }

        respond(
            [
                'success' =>
                    true,

                'message' =>
                    'Contenu supprimé.',
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PUBLISH
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'publish'
    ) {

        $type =
            strtolower(
                clean(
                    $request['type']
                    ?? 'post'
                )
            );

        $caption =
            clean(
                $request['caption']
                ?? ''
            );

        $image =
            clean(
                $request['image']
                ?? ''
            );

        if (
            $caption === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'La légende est vide.',
                ],
                400
            );
        }

        if (
            $image === ''
        ) {

            respond(
                [
                    'success' => false,
                    'message' =>
                        'Aucun visuel n’est associé à cette publication.',
                ],
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pour l'instant :
        | post image
        | story image
        |--------------------------------------------------------------------------
        */

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
                    'success' => false,
                    'message' =>
                        'Ce type de publication n’est pas encore pris en charge pour la publication automatique Instagram.',
                ],
                400
            );
        }

        $publication =
            publish_instagram_image(
                $image,
                $caption,
                $type
            );

        respond(
            [
                'success' =>
                    true,

                'message' =>
                    $type === 'story'
                        ? 'Story Instagram publiée avec succès.'
                        : 'Publication Instagram publiée avec succès.',

                'instagram' =>
                    $publication,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTION INCONNUE
    |--------------------------------------------------------------------------
    */

    respond(
        [
            'success' => false,
            'message' =>
                'Action inconnue.',
        ],
        400
    );

} catch (
    Throwable $e
) {

    respond(
        [
            'success' => false,
            'message' =>
                $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Une erreur serveur est survenue.',
        ],
        500
    );
}