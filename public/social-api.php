<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const BASE_DIR = __DIR__;
const CONFIG_FILE = BASE_DIR . '/vitrine-mail-config.php';

const SOCIAL_DIR = BASE_DIR . '/vitrine-data/social';
const SOCIAL_FILE = SOCIAL_DIR . '/contents.json';

const SOCIAL_MEDIA_DIR = BASE_DIR . '/social-media';
const SOCIAL_MEDIA_PUBLIC = '/social-media';

const SOCIAL_PREVIEW_DIR = BASE_DIR . '/social-preview/generated';
const SOCIAL_PREVIEW_PUBLIC = '/social-preview/generated';


/* =========================================================
 * RESPONSE
 * ========================================================= */

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
 * CONFIG
 * ========================================================= */

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


/* =========================================================
 * AUTH
 * ========================================================= */

function require_auth(): void
{
    $config = load_config();

    $expectedUser =
        config_string(
            $config,
            'grand_plus_admin_user'
        );

    $expectedPassword =
        (string) (
            $config[
                'grand_plus_admin_password'
            ] ?? ''
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
        (string) (
            $_SERVER['PHP_AUTH_USER'] ?? ''
        );

    $password =
        (string) (
            $_SERVER['PHP_AUTH_PW'] ?? ''
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
            'WWW-Authenticate: Basic realm="Vitrine+ Administration"'
        );

        respond(
            [
                'success' => false,
                'message' =>
                    'Authentification administrateur requise.',
            ],
            401
        );
    }
}


/* =========================================================
 * DIRECTORIES
 * ========================================================= */

function ensure_dirs(): void
{
    $directories = [
        SOCIAL_DIR,
        SOCIAL_MEDIA_DIR,
        SOCIAL_PREVIEW_DIR,
    ];

    foreach ($directories as $dir) {
        if (
            !is_dir($dir) &&
            !mkdir(
                $dir,
                0755,
                true
            ) &&
            !is_dir($dir)
        ) {
            throw new RuntimeException(
                'Impossible de créer le dossier social : ' .
                $dir
            );
        }
    }
}


/* =========================================================
 * CONTENT STORAGE
 * ========================================================= */

function read_contents(): array
{
    ensure_dirs();

    if (!is_file(SOCIAL_FILE)) {
        return [];
    }

    $raw =
        file_get_contents(
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
        ? array_values(
            array_filter(
                $data,
                'is_array'
            )
        )
        : [];
}


function write_contents(
    array $contents
): void {
    ensure_dirs();

    $tmp =
        SOCIAL_FILE . '.tmp';

    $json =
        json_encode(
            array_values(
                $contents
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );

    if (
        $json === false ||
        file_put_contents(
            $tmp,
            $json,
            LOCK_EX
        ) === false ||
        !rename(
            $tmp,
            SOCIAL_FILE
        )
    ) {
        throw new RuntimeException(
            'Impossible d’enregistrer les contenus sociaux.'
        );
    }
}


/* =========================================================
 * REQUEST HELPERS
 * ========================================================= */

function request_json(): array
{
    $raw =
        file_get_contents(
            'php://input'
        );

    if (
        !is_string($raw) ||
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


function clean(mixed $value): string
{
    return trim(
        (string) $value
    );
}


/* =========================================================
 * PUBLIC URL
 * ========================================================= */

function public_base_url(): string
{
    $config =
        load_config();

    $configured =
        rtrim(
            config_string(
                $config,
                'site_url'
            ),
            '/'
        );

    if ($configured !== '') {
        return $configured;
    }

    $host =
        (string) (
            $_SERVER['HTTP_HOST']
            ?? 'vitrineplus.fr'
        );

    return 'https://' . $host;
}


function public_url(
    string $path
): string {
    return
        public_base_url() .
        '/' .
        ltrim(
            $path,
            '/'
        );
}


/* =========================================================
 * IDS
 * ========================================================= */

function make_id(
    string $prefix = 'social'
): string {
    return
        $prefix .
        '-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(4)
        );
}


/* =========================================================
 * CURL
 * ========================================================= */

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
        strtoupper(
            $method
        );

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
            'VitrinePlus-SocialStudio/3.0',
    ];

    if (
        $method === 'POST'
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

    if (
        $body === false
    ) {
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


/* =========================================================
 * GEMINI
 * ========================================================= */

function extract_gemini_text(
    array $response
): string {
    $parts = [];

    foreach (
        (
            $response['candidates']
            ?? []
        )
        as $candidate
    ) {
        if (
            !is_array(
                $candidate
            )
        ) {
            continue;
        }

        foreach (
            (
                $candidate[
                    'content'
                ]['parts']
                ?? []
            )
            as $part
        ) {
            if (
                is_array($part) &&
                isset(
                    $part['text']
                ) &&
                is_string(
                    $part['text']
                )
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

    if (
        $apiKey === ''
    ) {
        throw new RuntimeException(
            'La clé Gemini n’est pas configurée dans vitrine-mail-config.php.'
        );
    }

    $configured =
        config_string(
            $config,
            'gemini_model'
        );

    $models =
        array_values(
            array_unique(
                array_filter(
                    [
                        $configured,
                        'gemini-3.8-flash',
                        'gemini-3.7-flash',
                        'gemini-3.6-flash',
                        'gemini-3.5-flash',
                        'gemini-3.1-flash-lite',
                        'gemini-2.5-flash-lite',
                    ]
                )
            )
        );

    $errors = [];

    foreach (
        $models as $model
    ) {
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
                            'text' =>
                                $prompt,
                        ],
                    ],
                ],
            ],

            'generationConfig' => [
                'temperature' =>
                    0.8,

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

        if (
            $json === false
        ) {
            $errors[] =
                $model .
                ': impossible d’encoder la requête JSON.';

            continue;
        }

        $ch =
            curl_init(
                $url
            );

        if (
            $ch === false
        ) {
            $errors[] =
                $model .
                ': impossible d’initialiser cURL.';

            continue;
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_FOLLOWLOCATION =>
                    true,

                CURLOPT_MAXREDIRS =>
                    5,

                CURLOPT_POST =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    15,

                CURLOPT_TIMEOUT =>
                    90,

                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'x-goog-api-key: ' .
                        $apiKey,
                ],

                CURLOPT_POSTFIELDS =>
                    $json,

                CURLOPT_USERAGENT =>
                    'VitrinePlus-SocialStudio/3.0',
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

        if (
            $body === false
        ) {
            $errors[] =
                $model .
                ': ' .
                (
                    $curlError !== ''
                        ? $curlError
                        : 'erreur réseau'
                );

            continue;
        }

        $response =
            json_decode(
                $body,
                true
            );

        if (
            !is_array(
                $response
            )
        ) {
            $errors[] =
                $model .
                ' [' .
                $httpCode .
                ']: réponse Gemini invalide.';

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

            if (
                $text !== ''
            ) {
                return $text;
            }

            $errors[] =
                $model .
                ': réponse Gemini vide.';

            continue;
        }

        $error =
            $response['error']
            ?? [];

        $message =
            (string) (
                $error['message']
                ?? 'erreur Gemini inconnue'
            );

        $status =
            (string) (
                $error['status']
                ?? ''
            );

        $details =
            $model .
            ' [' .
            $httpCode .
            ']: ' .
            $message;

        if (
            $status !== ''
        ) {
            $details .=
                ' | ' .
                $status;
        }

        $errors[] =
            $details;
    }

    throw new RuntimeException(
        'Gemini est temporairement indisponible sur les modèles gratuits. ' .
        'Aucune API payante n’a été utilisée. ' .
        'Détail : ' .
        implode(
            ' | ',
            $errors
        )
    );
}


/* =========================================================
 * AI CONTENT GENERATION
 * ========================================================= */

function generate_content(
    string $type,
    string $topic,
    string $objective
): array {
    $format =
        match ($type) {
            'post' =>
                'une publication Instagram simple',

            'carousel' =>
                'un carrousel Instagram de 5 à 7 slides',

            'reel' =>
                'un Reel Instagram vertical de 20 à 45 secondes avec hook, script parlé, indications visuelles et CTA',

            'story' =>
                'une Story Instagram verticale, concise et engageante',

            default =>
                'une publication Instagram',
        };

    $prompt = <<<PROMPT
Tu es le directeur éditorial et social media de Vitrine+, agence digitale française.

Promesse de marque :
« Votre entreprise. En mieux. »

Ton :
premium, moderne, humain, direct, utile, jamais robotique, jamais agressif commercialement.

Objectif :
{$objective}

Sujet :
{$topic}

Format :
{$format}

Crée un contenu prêt à être utilisé sur Instagram.

Règles :

- La légende doit être naturelle en français.
- Commence par une accroche forte.
- Évite les banalités.
- Évite les formulations génériques d'IA.
- Termine par un appel à l'action pertinent.
- Utilise les hashtags avec parcimonie.
- Pour un carrousel, génère 5 à 7 textes de slides courts et structurés.
- Pour un Reel, génère un script réellement exploitable pour une vidéo verticale.
- Pour un Reel, structure le script avec :
  1. Hook
  2. Déroulé
  3. CTA
- Le script doit être suffisamment court pour environ 20 à 45 secondes.
- Pour post/story, slides peut rester vide.
- Génère également un titre court et accrocheur.

Retourne uniquement le JSON demandé.
PROMPT;

    $raw =
        call_gemini(
            $prompt
        );

    $raw =
        preg_replace(
            '/^```(?:json)?\s*/i',
            '',
            $raw
        ) ?? $raw;

    $raw =
        preg_replace(
            '/\s*```$/',
            '',
            $raw
        ) ?? $raw;

    $decoded =
        json_decode(
            trim($raw),
            true
        );

    if (
        !is_array(
            $decoded
        )
    ) {
        throw new RuntimeException(
            'L’IA a retourné un format inattendu.'
        );
    }

    $slides = [];

    foreach (
        (
            $decoded['slides']
            ?? []
        )
        as $slide
    ) {
        $slide =
            trim(
                (string) $slide
            );

        if (
            $slide !== ''
        ) {
            $slides[] =
                $slide;
        }
    }

    return [
        'id' =>
            make_id(),

        'type' =>
            $type,

        'topic' =>
            $topic,

        'objective' =>
            $objective,

        'caption' =>
            trim(
                (string) (
                    $decoded[
                        'caption'
                    ] ?? ''
                )
            ),

        'slides' =>
            $slides,

        'script' =>
            trim(
                (string) (
                    $decoded[
                        'script'
                    ] ?? ''
                )
            ),

        'title' =>
            trim(
                (string) (
                    $decoded[
                        'title'
                    ] ?? ''
                )
            ),
    ];
}


/* =========================================================
 * IMAGE GENERATION
 * ========================================================= */

function font_path(): ?string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
    ];

    foreach (
        $candidates as $path
    ) {
        if (
            is_file($path)
        ) {
            return $path;
        }
    }

    return null;
}


function draw_wrapped_text(
    $image,
    string $text,
    int $x,
    int $y,
    int $maxWidth,
    int $fontSize,
    $color,
    ?string $font
): int {
    if (
        $font &&
        function_exists(
            'imagettftext'
        )
    ) {
        $words =
            preg_split(
                '/\s+/u',
                trim($text)
            ) ?: [];

        $lines = [];
        $line = '';

        foreach (
            $words as $word
        ) {
            $candidate =
                trim(
                    $line .
                    ' ' .
                    $word
                );

            $box =
                imagettfbbox(
                    $fontSize,
                    0,
                    $font,
                    $candidate
                );

            $width =
                abs(
                    $box[2] -
                    $box[0]
                );

            if (
                $line !== '' &&
                $width >
                    $maxWidth
            ) {
                $lines[] =
                    $line;

                $line =
                    $word;
            } else {
                $line =
                    $candidate;
            }
        }

        if (
            $line !== ''
        ) {
            $lines[] =
                $line;
        }

        foreach (
            $lines as $current
        ) {
            imagettftext(
                $image,
                $fontSize,
                0,
                $x,
                $y,
                $color,
                $font,
                $current
            );

            $y +=
                (int) (
                    $fontSize *
                    1.45
                );
        }

        return $y;
    }

    $lines =
        wordwrap(
            $text,
            35,
            "\n",
            true
        );

    foreach (
        explode(
            "\n",
            $lines
        ) as $current
    ) {
        imagestring(
            $image,
            5,
            $x,
            $y,
            $current,
            $color
        );

        $y += 22;
    }

    return $y;
}


function generate_image_file(
    string $text,
    string $type,
    string $suffix = ''
): string {
    if (
        !function_exists(
            'imagecreatetruecolor'
        )
    ) {
        throw new RuntimeException(
            'PHP GD n’est pas disponible sur l’hébergement.'
        );
    }

    ensure_dirs();

    $vertical =
        $type === 'story' ||
        $type === 'reel';

    $width = 1080;
    $height =
        $vertical
            ? 1920
            : 1080;

    $image =
        imagecreatetruecolor(
            $width,
            $height
        );

    if (
        $image === false
    ) {
        throw new RuntimeException(
            'Impossible de créer le visuel.'
        );
    }

    $bg =
        imagecolorallocate(
            $image,
            9,
            9,
            9
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
            248,
            248,
            248
        );

    $muted =
        imagecolorallocate(
            $image,
            160,
            160,
            160
        );

    imagefill(
        $image,
        0,
        0,
        $bg
    );

    imagefilledrectangle(
        $image,
        70,
        70,
        1010,
        76,
        $gold
    );

    imagefilledellipse(
        $image,
        $width - 135,
        115,
        90,
        90,
        $gold
    );

    $font =
        font_path();

    $small =
        $font
            ? 26
            : 5;

    $large =
        $font
            ? (
                $vertical
                    ? 56
                    : 52
            )
            : 5;

    $y =
        $vertical
            ? 300
            : 210;

    if ($font) {
        imagettftext(
            $image,
            $small,
            0,
            80,
            160,
            $gold,
            $font,
            'VITRINE+'
        );
    } else {
        imagestring(
            $image,
            5,
            80,
            145,
            'VITRINE+',
            $gold
        );
    }

    $clean =
        trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            ) ?? $text
        );

    draw_wrapped_text(
        $image,
        $clean,
        80,
        $y,
        900,
        $large,
        $white,
        $font
    );

    $footerY =
        $height - 170;

    if ($font) {
        imagettftext(
            $image,
            24,
            0,
            80,
            $footerY,
            $muted,
            $font,
            'Votre entreprise. En mieux.'
        );

        imagettftext(
            $image,
            22,
            0,
            80,
            $footerY + 42,
            $gold,
            $font,
            'vitrineplus.fr'
        );
    } else {
        imagestring(
            $image,
            4,
            80,
            $footerY,
            'Votre entreprise. En mieux.',
            $muted
        );

        imagestring(
            $image,
            4,
            80,
            $footerY + 28,
            'vitrineplus.fr',
            $gold
        );
    }

    $filename =
        make_id(
            'visual'
        ) .
        (
            $suffix !== ''
                ? '-' . $suffix
                : ''
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
        imagedestroy(
            $image
        );

        throw new RuntimeException(
            'Impossible d’enregistrer le visuel.'
        );
    }

    imagedestroy(
        $image
    );

    return public_url(
        SOCIAL_PREVIEW_PUBLIC .
        '/' .
        $filename
    );
}


function generate_visuals(
    string $type,
    string $topic,
    string $caption,
    array $slides = []
): array {
    if (
        $type === 'carousel'
    ) {
        $items =
            array_values(
                array_filter(
                    array_map(
                        fn($v) =>
                            (string) $v,
                        $slides
                    ),
                    fn($v) =>
                        trim($v) !== ''
                )
            );

        if (
            count($items) < 2
        ) {
            $items = [
                $topic,
                $caption,
            ];
        }

        $items =
            array_slice(
                $items,
                0,
                10
            );

        $urls = [];

        foreach (
            $items as $i => $item
        ) {
            $urls[] =
                generate_image_file(
                    $item,
                    'post',
                    (string) (
                        $i + 1
                    )
                );
        }

        return $urls;
    }

    return [
        generate_image_file(
            $topic !== ''
                ? $topic
                : $caption,
            $type
        ),
    ];
}


/* =========================================================
 * FFMPEG
 * ========================================================= */

function find_ffmpeg(): ?string
{
    $candidates = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/bin/ffmpeg',
        'ffmpeg',
    ];

    foreach (
        $candidates as $candidate
    ) {
        if (
            $candidate !==
            'ffmpeg'
        ) {
            if (
                is_file(
                    $candidate
                ) &&
                is_executable(
                    $candidate
                )
            ) {
                return $candidate;
            }

            continue;
        }

        $output = [];
        $code = 1;

        @exec(
            'command -v ffmpeg 2>/dev/null',
            $output,
            $code
        );

        if (
            $code === 0 &&
            !empty($output[0])
        ) {
            return trim(
                $output[0]
            );
        }
    }

    return null;
}


function shell_quote(
    string $value
): string {
    return escapeshellarg(
        $value
    );
}


/**
 * Télécharge un fichier distant vers
 * un fichier temporaire local.
 */
function download_remote_file(
    string $url,
    string $destination
): void {
    $fp =
        fopen(
            $destination,
            'wb'
        );

    if (
        $fp === false
    ) {
        throw new RuntimeException(
            'Impossible de créer le fichier temporaire.'
        );
    }

    $ch =
        curl_init(
            $url
        );

    if (
        $ch === false
    ) {
        fclose($fp);

        throw new RuntimeException(
            'Impossible d’initialiser cURL pour le téléchargement.'
        );
    }

    curl_setopt_array(
        $ch,
        [
            CURLOPT_FILE =>
                $fp,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_MAXREDIRS =>
                5,

            CURLOPT_CONNECTTIMEOUT =>
                15,

            CURLOPT_TIMEOUT =>
                120,

            CURLOPT_USERAGENT =>
                'VitrinePlus-SocialStudio/3.0',
        ]
    );

    $success =
        curl_exec($ch);

    $error =
        curl_error($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    fclose($fp);

    if (
        $success === false ||
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        @unlink(
            $destination
        );

        throw new RuntimeException(
            'Impossible de télécharger le visuel pour créer le Reel.' .
            (
                $error !== ''
                    ? ' ' . $error
                    : ''
            )
        );
    }

    if (
        !is_file($destination) ||
        filesize($destination) <= 0
    ) {
        @unlink(
            $destination
        );

        throw new RuntimeException(
            'Le visuel téléchargé est vide.'
        );
    }
}


/**
 * Crée une vidéo verticale MP4 à partir
 * d'un visuel.
 *
 * La vidéo dure 12 secondes.
 * L'image reçoit un léger zoom.
 * Aucun service vidéo payant n'est utilisé.
 */
function generate_reel_mp4(
    string $imageUrl,
    string $script
): string {
    ensure_dirs();

    $ffmpeg =
        find_ffmpeg();

    if (
        $ffmpeg === null
    ) {
        throw new RuntimeException(
            'FFmpeg n’est pas disponible sur cet hébergement. Le script et le visuel ont bien été générés, mais la vidéo MP4 ne peut pas encore être assemblée automatiquement.'
        );
    }

    $tempImage =
        SOCIAL_MEDIA_DIR .
        '/' .
        make_id(
            'tmp-image'
        ) .
        '.jpg';

    $filename =
        make_id(
            'reel'
        ) .
        '.mp4';

    $output =
        SOCIAL_MEDIA_DIR .
        '/' .
        $filename;

    try {
        download_remote_file(
            $imageUrl,
            $tempImage
        );

        /*
         * Vidéo :
         * - 1080x1920
         * - 30 FPS
         * - 12 secondes
         * - H.264
         * - AAC
         * - mouvement de zoom très léger
         * - pixel format yuv420p
         * - faststart pour Instagram
         */
        $filter =
            "scale=1080:1920:force_original_aspect_ratio=increase," .
            "crop=1080:1920," .
            "zoompan=" .
            "z='min(zoom+0.0008,1.04)':" .
            "x='iw/2-(iw/zoom/2)':" .
            "y='ih/2-(ih/zoom/2)':" .
            "d=360:" .
            "s=1080x1920:" .
            "fps=30";

        $command =
            shell_quote(
                $ffmpeg
            ) .
            ' -y' .
            ' -loop 1' .
            ' -i ' .
            shell_quote(
                $tempImage
            ) .
            ' -t 12' .
            ' -vf ' .
            shell_quote(
                $filter
            ) .
            ' -an' .
            ' -c:v libx264' .
            ' -preset veryfast' .
            ' -crf 22' .
            ' -pix_fmt yuv420p' .
            ' -movflags +faststart' .
            ' ' .
            shell_quote(
                $output
            ) .
            ' 2>&1';

        $outputLines = [];
        $exitCode = 1;

        @exec(
            $command,
            $outputLines,
            $exitCode
        );

        if (
            $exitCode !== 0 ||
            !is_file($output) ||
            filesize($output) <= 0
        ) {
            $details =
                trim(
                    implode(
                        "\n",
                        array_slice(
                            $outputLines,
                            -12
                        )
                    )
                );

            throw new RuntimeException(
                'FFmpeg n’a pas réussi à créer le Reel.' .
                (
                    $details !== ''
                        ? ' Détail : ' .
                            $details
                        : ''
                )
            );
        }

        return public_url(
            SOCIAL_MEDIA_PUBLIC .
            '/' .
            $filename
        );
    } finally {
        if (
            is_file(
                $tempImage
            )
        ) {
            @unlink(
                $tempImage
            );
        }
    }
}


/* =========================================================
 * AUTOMATIC REEL GENERATION
 * ========================================================= */

function generate_reel(
    string $topic,
    string $objective
): array {
    if (
        trim($topic) === ''
    ) {
        throw new RuntimeException(
            'Le sujet du Reel est obligatoire.'
        );
    }

    /*
     * 1. Gemini crée le contenu.
     */
    $content =
        generate_content(
            'reel',
            $topic,
            $objective
        );

    /*
     * 2. Génération du visuel vertical.
     */
    $visuals =
        generate_visuals(
            'reel',
            $topic,
            $content['caption'],
            []
        );

    $visualUrl =
        $visuals[0]
        ?? '';

    if (
        $visualUrl === ''
    ) {
        throw new RuntimeException(
            'Impossible de générer le visuel du Reel.'
        );
    }

    /*
     * 3. Assemblage automatique en MP4.
     */
    $videoUrl =
        generate_reel_mp4(
            $visualUrl,
            $content['script']
        );

    return [
        'id' =>
            $content['id'],

        'type' =>
            'reel',

        'topic' =>
            $topic,

        'objective' =>
            $objective,

        'caption' =>
            $content['caption'],

        'slides' =>
            [],

        'script' =>
            $content['script'],

        'title' =>
            $content['title'],

        'mediaUrls' =>
            [
                $visualUrl,
            ],

        'videoUrl' =>
            $videoUrl,

        'status' =>
            'draft',

        'createdAt' =>
            date('c'),

        'updatedAt' =>
            date('c'),
    ];
}


/* =========================================================
 * INSTAGRAM
 * ========================================================= */

function instagram_api_version(
    array $config
): string {
    $version =
        config_string(
            $config,
            'instagram_api_version'
        ) ?: 'v24.0';

    return
        str_starts_with(
            $version,
            'v'
        )
            ? $version
            : 'v' . $version;
}


function instagram_token(
    array $config
): string {
    return config_string(
        $config,
        'instagram_access_token'
    );
}


function instagram_account(
    string $token,
    string $version
): array {
    $url =
        'https://graph.instagram.com/' .
        $version .
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

    $result =
        curl_request(
            $url
        );

    if (
        $result['http_code'] < 200 ||
        $result['http_code'] >= 300 ||
        empty(
            $result[
                'body'
            ]['id']
        )
    ) {
        $error =
            $result[
                'body'
            ]['error']
            ?? [];

        throw new RuntimeException(
            'Instagram : ' .
            (
                $error[
                    'message'
                ]
                ??
                'Impossible de récupérer le compte Instagram.'
            )
        );
    }

    return [
        'id' =>
            (string) (
                $result[
                    'body'
                ]['id']
            ),

        'username' =>
            (string) (
                $result[
                    'body'
                ]['username']
                ?? ''
            ),
    ];
}


function meta_error(
    array $result,
    string $fallback
): string {
    $error =
        $result[
            'body'
        ]['error']
        ?? [];

    $details =
        $error['message']
        ??
        $fallback;

    foreach (
        [
            'type' =>
                'Type',

            'code' =>
                'Code',

            'error_subcode' =>
                'Sous-code',

            'fbtrace_id' =>
                'FBTrace ID',
        ]
        as $key => $label
    ) {
        if (
            !empty(
                $error[$key]
            )
        ) {
            $details .=
                ' | ' .
                $label .
                ': ' .
                $error[$key];
        }
    }

    return
        'Instagram : ' .
        $details;
}


function create_instagram_container(
    string $accountId,
    string $version,
    string $token,
    array $fields
): string {
    $url =
        'https://graph.instagram.com/' .
        $version .
        '/' .
        rawurlencode(
            $accountId
        ) .
        '/media';

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
        $result['http_code'] >= 300 ||
        empty(
            $result[
                'body'
            ]['id']
        )
    ) {
        throw new RuntimeException(
            meta_error(
                $result,
                'Instagram n’a pas créé le média.'
            )
        );
    }

    return (string) (
        $result[
            'body'
        ]['id']
    );
}


function wait_for_container(
    string $containerId,
    string $token,
    string $version
): array {
    $last = [];

    for (
        $i = 0;
        $i < 30;
        $i++
    ) {
        $url =
            'https://graph.instagram.com/' .
            $version .
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
            $result[
                'body'
            ];

        $status =
            strtoupper(
                (string) (
                    $result[
                        'body'
                    ]['status_code']
                    ?? ''
                )
            );

        if (
            $status ===
            'FINISHED'
        ) {
            return
                $result[
                    'body'
                ];
        }

        if (
            in_array(
                $status,
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
                    $result[
                        'body'
                    ]['status']
                    ??
                    'Le container Instagram a échoué.'
                )
            );
        }

        sleep(2);
    }

    throw new RuntimeException(
        'Instagram n’a pas terminé le traitement du média. Statut : ' .
        (
            $last[
                'status'
            ]
            ??
            'inconnu'
        )
    );
}


function publish_container(
    string $accountId,
    string $version,
    string $token,
    string $containerId
): string {
    $url =
        'https://graph.instagram.com/' .
        $version .
        '/' .
        rawurlencode(
            $accountId
        ) .
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
        $result['http_code'] >= 300 ||
        empty(
            $result[
                'body'
            ]['id']
        )
    ) {
        throw new RuntimeException(
            meta_error(
                $result,
                'Instagram n’a pas publié le média.'
            )
        );
    }

    return (string) (
        $result[
            'body'
        ]['id']
    );
}


/* =========================================================
 * INSTAGRAM PUBLISH
 * ========================================================= */

function publish_instagram(
    array $content
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
            'Le token Instagram n’est pas configuré.'
        );
    }

    $version =
        instagram_api_version(
            $config
        );

    $account =
        instagram_account(
            $token,
            $version
        );

    $accountId =
        $account['id'];

    $type =
        (string) (
            $content['type']
            ?? 'post'
        );

    $caption =
        trim(
            (string) (
                $content[
                    'caption'
                ] ?? ''
            )
        );

    $images =
        array_values(
            array_filter(
                array_map(
                    'strval',
                    is_array(
                        $content[
                            'mediaUrls'
                        ] ?? null
                    )
                        ? $content[
                            'mediaUrls'
                        ]
                        : []
                )
            )
        );

    $video =
        trim(
            (string) (
                $content[
                    'videoUrl'
                ] ?? ''
            )
        );

    if (
        $caption === ''
    ) {
        throw new RuntimeException(
            'La légende est vide.'
        );
    }


    /* -----------------------------------------------------
     * POST / STORY
     * ----------------------------------------------------- */

    if (
        $type === 'post' ||
        $type === 'story'
    ) {
        if (!$images) {
            throw new RuntimeException(
                'Aucun visuel public n’a été fourni.'
            );
        }

        $fields = [
            'image_url' =>
                $images[0],

            'caption' =>
                $caption,

            'access_token' =>
                $token,
        ];

        if (
            $type ===
            'story'
        ) {
            $fields[
                'media_type'
            ] =
                'STORIES';
        }

        $container =
            create_instagram_container(
                $accountId,
                $version,
                $token,
                $fields
            );

        $status =
            wait_for_container(
                $container,
                $token,
                $version
            );

        $mediaId =
            publish_container(
                $accountId,
                $version,
                $token,
                $container
            );

        return [
            'account' =>
                $account,

            'container_id' =>
                $container,

            'container_status' =>
                $status,

            'media_id' =>
                $mediaId,
        ];
    }


    /* -----------------------------------------------------
     * CAROUSEL
     * ----------------------------------------------------- */

    if (
        $type ===
        'carousel'
    ) {
        if (
            count($images) < 2
        ) {
            throw new RuntimeException(
                'Un carrousel doit contenir au moins 2 visuels.'
            );
        }

        if (
            count($images) > 10
        ) {
            $images =
                array_slice(
                    $images,
                    0,
                    10
                );
        }

        $children = [];

        foreach (
            $images as $image
        ) {
            $child =
                create_instagram_container(
                    $accountId,
                    $version,
                    $token,
                    [
                        'image_url' =>
                            $image,

                        'is_carousel_item' =>
                            'true',

                        'access_token' =>
                            $token,
                    ]
                );

            wait_for_container(
                $child,
                $token,
                $version
            );

            $children[] =
                $child;
        }

        $container =
            create_instagram_container(
                $accountId,
                $version,
                $token,
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
                        $token,
                ]
            );

        $status =
            wait_for_container(
                $container,
                $token,
                $version
            );

        $mediaId =
            publish_container(
                $accountId,
                $version,
                $token,
                $container
            );

        return [
            'account' =>
                $account,

            'container_id' =>
                $container,

            'container_status' =>
                $status,

            'media_id' =>
                $mediaId,

            'children' =>
                $children,
        ];
    }


    /* -----------------------------------------------------
     * REEL
     * ----------------------------------------------------- */

    if (
        $type ===
        'reel'
    ) {
        if (
            $video === ''
        ) {
            throw new RuntimeException(
                'Aucune vidéo publique n’a été fournie pour ce Reel.'
            );
        }

        $container =
            create_instagram_container(
                $accountId,
                $version,
                $token,
                [
                    'media_type' =>
                        'REELS',

                    'video_url' =>
                        $video,

                    'caption' =>
                        $caption,

                    'share_to_feed' =>
                        'true',

                    'access_token' =>
                        $token,
                ]
            );

        $status =
            wait_for_container(
                $container,
                $token,
                $version
            );

        $mediaId =
            publish_container(
                $accountId,
                $version,
                $token,
                $container
            );

        return [
            'account' =>
                $account,

            'container_id' =>
                $container,

            'container_status' =>
                $status,

            'media_id' =>
                $mediaId,
        ];
    }

    throw new RuntimeException(
        'Format Instagram non pris en charge.'
    );
}


/* =========================================================
 * UPLOAD MEDIA
 * ========================================================= */

function save_uploaded_media(
    array $file
): array {
    if (
        (
            $file['error']
            ??
            UPLOAD_ERR_NO_FILE
        )
        !==
        UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Échec de l’envoi du média.'
        );
    }

    $tmp =
        (string) (
            $file[
                'tmp_name'
            ] ?? ''
        );

    if (
        $tmp === '' ||
        !is_uploaded_file(
            $tmp
        )
    ) {
        throw new RuntimeException(
            'Fichier uploadé invalide.'
        );
    }

    $mime =
        (string) (
            $file[
                'type'
            ]
            ??
            'application/octet-stream'
        );

    $allowed = [
        'image/jpeg' =>
            'jpg',

        'image/jpg' =>
            'jpg',

        'video/mp4' =>
            'mp4',

        'video/quicktime' =>
            'mov',
    ];

    if (
        !isset(
            $allowed[$mime]
        )
    ) {
        if (
            function_exists(
                'finfo_open'
            )
        ) {
            $f =
                finfo_open(
                    FILEINFO_MIME_TYPE
                );

            if (
                $f !== false
            ) {
                $detected =
                    finfo_file(
                        $f,
                        $tmp
                    );

                finfo_close(
                    $f
                );

                if (
                    is_string(
                        $detected
                    )
                ) {
                    $mime =
                        $detected;
                }
            }
        }
    }

    if (
        !isset(
            $allowed[$mime]
        )
    ) {
        throw new RuntimeException(
            'Format non supporté. Utilise JPG pour les images ou MP4/MOV pour les vidéos.'
        );
    }

    ensure_dirs();

    $name =
        make_id(
            'media'
        ) .
        '.' .
        $allowed[$mime];

    $path =
        SOCIAL_MEDIA_DIR .
        '/' .
        $name;

    if (
        !move_uploaded_file(
            $tmp,
            $path
        )
    ) {
        throw new RuntimeException(
            'Impossible d’enregistrer le média.'
        );
    }

    return [
        'url' =>
            public_url(
                SOCIAL_MEDIA_PUBLIC .
                '/' .
                $name
            ),

        'type' =>
            str_starts_with(
                $mime,
                'video/'
            )
                ? 'video'
                : 'image',

        'filename' =>
            $name,
    ];
}


/* =========================================================
 * CONTENT STATUS
 * ========================================================= */

function update_content_status(
    string $id,
    string $status,
    array $extra = []
): void {
    if (
        $id === ''
    ) {
        return;
    }

    $contents =
        read_contents();

    foreach (
        $contents as $i =>
        $content
    ) {
        if (
            (
                string
            ) (
                $content['id']
                ?? ''
            )
            ===
            $id
        ) {
            $contents[
                $i
            ]['status'] =
                $status;

            $contents[
                $i
            ]['updatedAt'] =
                date('c');

            foreach (
                $extra as $key =>
                $value
            ) {
                $contents[
                    $i
                ][$key] =
                    $value;
            }

            break;
        }
    }

    write_contents(
        $contents
    );
}


/* =========================================================
 * SCHEDULER
 * ========================================================= */

function process_scheduled(): array
{
    $now =
        time();

    $contents =
        read_contents();

    $processed =
        0;

    $errors = [];

    foreach (
        $contents as $i =>
        $content
    ) {
        if (
            (
                $content[
                    'status'
                ] ?? ''
            )
            !==
            'scheduled'
        ) {
            continue;
        }

        $scheduled =
            strtotime(
                (string) (
                    $content[
                        'scheduledAt'
                    ] ?? ''
                )
            );

        if (
            $scheduled ===
            false ||
            $scheduled >
                $now
        ) {
            continue;
        }

        try {
            $result =
                publish_instagram(
                    $content
                );

            $contents[
                $i
            ]['status'] =
                'published';

            $contents[
                $i
            ]['updatedAt'] =
                date('c');

            $contents[
                $i
            ]['instagram'] =
                $result;

            $processed++;
        } catch (
            Throwable $e
        ) {
            $contents[
                $i
            ]['status'] =
                'scheduled_error';

            $contents[
                $i
            ]['updatedAt'] =
                date('c');

            $contents[
                $i
            ]['publishError'] =
                $e->getMessage();

            $errors[] =
                (
                    $content[
                        'title'
                    ]
                    ??
                    $content[
                        'topic'
                    ]
                    ??
                    $content[
                        'id'
                    ]
                    ??
                    'contenu'
                ) .
                ': ' .
                $e->getMessage();
        }
    }

    write_contents(
        $contents
    );

    return [
        'processed' =>
            $processed,

        'errors' =>
            $errors,
    ];
}


/* =========================================================
 * INPUT
 * ========================================================= */

$method =
    strtoupper(
        (string) (
            $_SERVER[
                'REQUEST_METHOD'
            ] ?? 'GET'
        )
    );

$contentType =
    strtolower(
        (string) (
            $_SERVER[
                'CONTENT_TYPE'
            ] ?? ''
        )
    );

$input =
    $method === 'POST'
        ? (
            str_contains(
                $contentType,
                'application/json'
            )
                ? request_json()
                : $_POST
        )
        : $_GET;

$action =
    clean(
        $input[
            'action'
        ] ?? 'list'
    );


/* =========================================================
 * AUTH
 * ========================================================= */

require_auth();


/* =========================================================
 * CRON
 * ========================================================= */

if (
    $action ===
    'cron'
) {
    try {
        respond(
            [
                'success' =>
                    true,
            ] +
            process_scheduled()
        );
    } catch (
        Throwable $e
    ) {
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
}


/* =========================================================
 * GET / LIST
 * ========================================================= */

if (
    $method === 'GET' ||
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


if (
    $method !== 'POST'
) {
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


/* =========================================================
 * ACTIONS
 * ========================================================= */

try {

    /* -----------------------------------------------------
     * GENERATE CONTENT
     * ----------------------------------------------------- */

    if (
        $action ===
        'generate'
    ) {
        $type =
            clean(
                $input[
                    'type'
                ] ?? 'post'
            );

        $topic =
            clean(
                $input[
                    'topic'
                ] ?? ''
            );

        $objective =
            clean(
                $input[
                    'objective'
                ]
                ??
                'Gagner en visibilité'
            );

        if (
            $topic === ''
        ) {
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

        if (
            !in_array(
                $type,
                [
                    'post',
                    'carousel',
                    'reel',
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
                        'Format de contenu invalide.',
                ],
                422
            );
        }

        respond(
            [
                'success' =>
                    true,

                'content' =>
                    generate_content(
                        $type,
                        $topic,
                        $objective
                    ),
            ]
        );
    }


    /* -----------------------------------------------------
     * GENERATE REEL COMPLET
     * ----------------------------------------------------- */

    if (
        $action ===
        'generate_reel'
    ) {
        $topic =
            clean(
                $input[
                    'topic'
                ] ?? ''
            );

        $objective =
            clean(
                $input[
                    'objective'
                ]
                ??
                'Gagner en visibilité'
            );

        if (
            $topic === ''
        ) {
            respond(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Le sujet du Reel est obligatoire.',
                ],
                422
            );
        }

        $content =
            generate_reel(
                $topic,
                $objective
            );

        respond(
            [
                'success' =>
                    true,

                'content' =>
                    $content,

                'videoUrl' =>
                    $content[
                        'videoUrl'
                    ],

                'message' =>
                    'Reel généré automatiquement.',
            ]
        );
    }


    /* -----------------------------------------------------
     * GENERATE VISUAL
     * ----------------------------------------------------- */

    if (
        $action ===
        'generate_visual'
    ) {
        $type =
            clean(
                $input[
                    'type'
                ] ?? 'post'
            );

        $topic =
            clean(
                $input[
                    'topic'
                ] ?? ''
            );

        $caption =
            clean(
                $input[
                    'caption'
                ] ?? ''
            );

        $slides =
            is_array(
                $input[
                    'slides'
                ] ?? null
            )
                ? $input[
                    'slides'
                ]
                : [];

        $urls =
            generate_visuals(
                $type,
                $topic,
                $caption,
                $slides
            );

        respond(
            [
                'success' =>
                    true,

                'urls' =>
                    $urls,

                'url' =>
                    $urls[0]
                    ?? '',
            ]
        );
    }


    /* -----------------------------------------------------
     * SAVE
     * ----------------------------------------------------- */

    if (
        $action ===
        'save'
    ) {
        $content =
            is_array(
                $input[
                    'content'
                ] ?? null
            )
                ? $input[
                    'content'
                ]
                : $input;

        $id =
            clean(
                $content[
                    'id'
                ] ?? ''
            );

        if (
            $id === ''
        ) {
            $id =
                make_id();
        }

        $existing =
            read_contents();

        $scheduledAt =
            clean(
                $content[
                    'scheduledAt'
                ] ?? ''
            );

        $status =
            clean(
                $content[
                    'status'
                ] ?? ''
            );

        if (
            $status === ''
        ) {
            $status =
                $scheduledAt !== ''
                    ? 'scheduled'
                    : 'draft';
        }

        $normalized = [
            'id' =>
                $id,

            'type' =>
                clean(
                    $content[
                        'type'
                    ] ?? 'post'
                ),

            'topic' =>
                clean(
                    $content[
                        'topic'
                    ] ?? ''
                ),

            'objective' =>
                clean(
                    $content[
                        'objective'
                    ] ?? ''
                ),

            'caption' =>
                trim(
                    (string) (
                        $content[
                            'caption'
                        ] ?? ''
                    )
                ),

            'slides' =>
                array_values(
                    array_map(
                        'strval',
                        is_array(
                            $content[
                                'slides'
                            ] ?? null
                        )
                            ? $content[
                                'slides'
                            ]
                            : []
                    )
                ),

            'script' =>
                trim(
                    (string) (
                        $content[
                            'script'
                        ] ?? ''
                    )
                ),

            'mediaUrls' =>
                array_values(
                    array_map(
                        'strval',
                        is_array(
                            $content[
                                'mediaUrls'
                            ] ?? null
                        )
                            ? $content[
                                'mediaUrls'
                            ]
                            : []
                    )
                ),

            'videoUrl' =>
                clean(
                    $content[
                        'videoUrl'
                    ] ?? ''
                ),

            'scheduledAt' =>
                $scheduledAt,

            'status' =>
                $status,

            'createdAt' =>
                clean(
                    $content[
                        'createdAt'
                    ] ?? ''
                )
                    ?: date('c'),

            'updatedAt' =>
                date('c'),
        ];

        if (
            isset(
                $content[
                    'title'
                ]
            )
        ) {
            $normalized[
                'title'
            ] =
                clean(
                    $content[
                        'title'
                    ]
                );
        }

        $found =
            false;

        foreach (
            $existing as $i =>
            $item
        ) {
            if (
                (
                    $item[
                        'id'
                    ] ?? ''
                )
                ===
                $id
            ) {
                $existing[
                    $i
                ] =
                    array_merge(
                        $item,
                        $normalized
                    );

                $found =
                    true;

                break;
            }
        }

        if (
            !$found
        ) {
            array_unshift(
                $existing,
                $normalized
            );
        }

        write_contents(
            $existing
        );

        respond(
            [
                'success' =>
                    true,

                'content' =>
                    $normalized,
            ]
        );
    }


    /* -----------------------------------------------------
     * DELETE
     * ----------------------------------------------------- */

    if (
        $action ===
        'delete'
    ) {
        $id =
            clean(
                $input[
                    'id'
                ] ?? ''
            );

        if (
            $id === ''
        ) {
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
            array_values(
                array_filter(
                    read_contents(),
                    fn($item) =>
                        (
                            string
                        ) (
                            $item[
                                'id'
                            ] ?? ''
                        )
                        !==
                        $id
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


    /* -----------------------------------------------------
     * UPLOAD
     * ----------------------------------------------------- */

    if (
        $action ===
        'upload_media'
    ) {
        if (
            !isset(
                $_FILES[
                    'media'
                ]
            )
        ) {
            respond(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Aucun média reçu.',
                ],
                422
            );
        }

        respond(
            [
                'success' =>
                    true,

                'media' =>
                    save_uploaded_media(
                        $_FILES[
                            'media'
                        ]
                    ),
            ]
        );
    }


    /* -----------------------------------------------------
     * PUBLISH
     * ----------------------------------------------------- */

    if (
        $action ===
        'publish'
    ) {
        $id =
            clean(
                $input[
                    'id'
                ] ?? ''
            );

        $content =
            is_array(
                $input[
                    'content'
                ] ?? null
            )
                ? $input[
                    'content'
                ]
                : $input;

        $result =
            publish_instagram(
                $content
            );

        update_content_status(
            $id,
            'published',
            [
                'instagram' =>
                    $result,
            ]
        );

        respond(
            [
                'success' =>
                    true,

                'message' =>
                    'Publication envoyée sur Instagram.',

                'result' =>
                    $result,
            ]
        );
    }


    /* -----------------------------------------------------
     * UNKNOWN
     * ----------------------------------------------------- */

    respond(
        [
            'success' =>
                false,

            'message' =>
                'Action inconnue.',
        ],
        404
    );

} catch (
    Throwable $e
) {
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