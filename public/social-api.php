<?php

declare(strict_types=1);

/*
 * =========================================================
 * VITRINE+ — SOCIAL STUDIO API
 * =========================================================
 *
 * Fonctions :
 * - génération de contenu Gemini
 * - génération de visuels
 * - génération de Reel
 * - upload de médias
 * - publication Instagram
 * - programmation
 * - bibliothèque de contenus
 *
 * IMPORTANT :
 * aucune API vidéo payante n'est utilisée.
 */

/* =========================================================
 * CONFIGURATION
 * ========================================================= */

header(
    'Content-Type: application/json; charset=utf-8'
);

const SOCIAL_DATA_DIR =
    __DIR__ . '/vitrine-data';

const SOCIAL_CONTENT_FILE =
    SOCIAL_DATA_DIR . '/social-contents.json';

const SOCIAL_MEDIA_PUBLIC =
    '/vitrine-social-media';

const SOCIAL_MEDIA_DIR =
    __DIR__ . SOCIAL_MEDIA_PUBLIC;


/* =========================================================
 * HELPERS
 * ========================================================= */

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


function clean(
    mixed $value
): string {
    return trim(
        (string) $value
    );
}


function make_id(
    string $prefix = 'content'
): string {
    return
        $prefix .
        '-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(5)
        );
}


function ensure_dirs(): void {
    if (
        !is_dir(
            SOCIAL_DATA_DIR
        )
    ) {
        @mkdir(
            SOCIAL_DATA_DIR,
            0755,
            true
        );
    }

    if (
        !is_dir(
            SOCIAL_MEDIA_DIR
        )
    ) {
        @mkdir(
            SOCIAL_MEDIA_DIR,
            0755,
            true
        );
    }

    if (
        !is_file(
            SOCIAL_CONTENT_FILE
        )
    ) {
        @file_put_contents(
            SOCIAL_CONTENT_FILE,
            json_encode(
                [],
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE
            )
        );
    }
}


function read_contents(): array {
    ensure_dirs();

    $raw =
        @file_get_contents(
            SOCIAL_CONTENT_FILE
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

    return
        is_array($data)
            ? $data
            : [];
}


function write_contents(
    array $contents
): void {
    ensure_dirs();

    $json =
        json_encode(
            array_values($contents),
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if (
        $json === false
    ) {
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


function request_json(): array {
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

    return
        is_array($data)
            ? $data
            : [];
}


/* =========================================================
 * AUTH
 * ========================================================= */

function require_auth(): void {
    $configuredUser = '';
    $configuredPassword = '';

    $configPath =
        __DIR__ .
        '/vitrine-mail-config.php';

    if (
        is_file(
            $configPath
        )
    ) {
        $config =
            require $configPath;

        if (
            is_array($config)
        ) {
            $configuredUser =
                clean(
                    $config[
                        'grand_plus_admin_user'
                    ] ??
                    ''
                );

            $configuredPassword =
                (string) (
                    $config[
                        'grand_plus_admin_password'
                    ] ??
                    ''
                );
        }
    }

    $user =
        $_SERVER[
            'PHP_AUTH_USER'
        ] ?? '';

    $password =
        $_SERVER[
            'PHP_AUTH_PW'
        ] ?? '';

    if (
        $configuredUser === '' ||
        $configuredPassword === '' ||
        !hash_equals(
            $configuredUser,
            (string) $user
        ) ||
        !hash_equals(
            $configuredPassword,
            (string) $password
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ Administration"'
        );

        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Authentification requise.',
            ],
            401
        );
    }
}


/* =========================================================
 * CONFIG
 * ========================================================= */

function load_config(): array {
    $path =
        __DIR__ .
        '/vitrine-mail-config.php';

    if (
        !is_file($path)
    ) {
        throw new RuntimeException(
            'Le fichier vitrine-mail-config.php est introuvable.'
        );
    }

    $config =
        require $path;

    if (
        !is_array($config)
    ) {
        throw new RuntimeException(
            'La configuration Vitrine+ est invalide.'
        );
    }

    return $config;
}


function config_string(
    array $config,
    string $key
): string {
    return clean(
        $config[$key] ?? ''
    );
}


/* =========================================================
 * HTTP
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

    if (
        $ch === false
    ) {
        throw new RuntimeException(
            'Impossible d’initialiser cURL.'
        );
    }

    $method =
        strtoupper($method);

    $finalUrl =
        $url;

    if (
        $method === 'GET' &&
        $fields
    ) {
        $finalUrl .=
            (
                str_contains(
                    $finalUrl,
                    '?'
                )
                    ? '&'
                    : '?'
            ) .
            http_build_query(
                $fields,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    $options = [
        CURLOPT_URL =>
            $finalUrl,

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

        CURLOPT_USERAGENT =>
            'VitrinePlus-SocialStudio/2.0',

        CURLOPT_HTTPHEADER =>
            array_merge(
                [
                    'Accept: application/json',
                ],
                $headers
            ),
    ];

    if (
        $method === 'POST'
    ) {
        $options[
            CURLOPT_POST
        ] = true;

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
            $curlError !== ''
                ? $curlError
                : 'Erreur réseau.'
        );
    }

    $decoded =
        json_decode(
            $body,
            true
        );

    return [
        'http_code' =>
            $httpCode,

        'body' =>
            is_array($decoded)
                ? $decoded
                : [],

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
    $parts =
        $response[
            'candidates'
        ][0][
            'content'
        ][
            'parts'
        ] ?? [];

    if (
        !is_array($parts)
    ) {
        return '';
    }

    $text = '';

    foreach (
        $parts as $part
    ) {
        if (
            isset(
                $part['text']
            )
        ) {
            $text .=
                (string) $part['text'];
        }
    }

    return trim($text);
}


function call_gemini(string $prompt): string
{
    $config = load_config();

    $apiKey = config_string($config, 'gemini_api_key');

    if ($apiKey === '') {
        throw new RuntimeException(
            'TEST GEMINI — clé API absente.'
        );
    }

    $url =
        'https://generativelanguage.googleapis.com/v1beta/models?key=' .
        rawurlencode($apiKey);

    $start = microtime(true);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 15,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_FOLLOWLOCATION => false,

        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,

        CURLOPT_USERAGENT => 'VitrinePlus-Gemini-Test/1.0'
    ]);

    $response = curl_exec($ch);

    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $primaryIp = curl_getinfo(
        $ch,
        CURLINFO_PRIMARY_IP
    );

    $totalTime = (float) curl_getinfo(
        $ch,
        CURLINFO_TOTAL_TIME
    );

    curl_close($ch);

    if ($response === false) {

        throw new RuntimeException(
            'TEST GEMINI MODELS — cURL #' .
            $curlErrno .
            ' — ' .
            ($curlError ?: 'erreur inconnue') .
            ' — HTTP ' .
            $httpCode .
            ' — IP ' .
            ($primaryIp ?: 'aucune') .
            ' — durée ' .
            round($totalTime, 3) .
            's'
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {

        throw new RuntimeException(
            'TEST GEMINI MODELS — HTTP ' .
            $httpCode .
            ' — réponse : ' .
            substr($response, 0, 3000)
        );
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {

        throw new RuntimeException(
            'TEST GEMINI MODELS — JSON invalide : ' .
            substr($response, 0, 3000)
        );
    }

    $models = [];

    if (
        isset($decoded['models']) &&
        is_array($decoded['models'])
    ) {

        foreach ($decoded['models'] as $model) {

            if (!is_array($model)) {
                continue;
            }

            $name = $model['name'] ?? '';

            if ($name !== '') {
                $models[] = $name;
            }
        }
    }

    if (count($models) === 0) {

        throw new RuntimeException(
            'TEST GEMINI MODELS — Google répond, mais aucun modèle trouvé. ' .
            'Réponse : ' .
            substr($response, 0, 3000)
        );
    }

    throw new RuntimeException(
        'TEST GEMINI MODELS — SUCCÈS ! ' .
        count($models) .
        ' modèles disponibles. ' .
        'Premiers modèles : ' .
        implode(', ', array_slice($models, 0, 20)) .
        ' — durée ' .
        round($totalTime, 3) .
        's'
    );
}


/* =========================================================
 * VISUELS
 * ========================================================= */

function generate_visuals(
    string $type,
    string $topic,
    string $caption,
    array $slides = []
): array {
    if (
        $type === 'reel'
    ) {
        $items =
            array_values(
                array_filter(
                    array_map(
                        fn($value) =>
                            trim(
                                (string)
                                $value
                            ),
                        $slides
                    ),
                    fn($value) =>
                        $value !== ''
                )
            );

        if (
            count($items) === 0
        ) {
            $items = [
                'Ton site fait peut-être fuir tes clients.',
                'Erreur n°1 : un site trop lent.',
                'Erreur n°2 : une offre difficile à comprendre.',
                'Erreur n°3 : aucun appel à l’action.',
                'Ton site mérite mieux. Vitrine+.',
            ];
        }

        $fallbackSlides = [
            'Ton site fait peut-être fuir tes clients.',
            'Erreur n°1 : un site trop lent.',
            'Erreur n°2 : une offre difficile à comprendre.',
            'Erreur n°3 : aucun appel à l’action.',
            'Ton site mérite mieux. Vitrine+.',
        ];

        for (
            $i = count($items);
            $i < 5;
            $i++
        ) {
            $items[] =
                $fallbackSlides[$i];
        }

        $items =
            array_slice(
                $items,
                0,
                5
            );

        $urls = [];

        foreach (
            $items as $i => $item
        ) {
            $urls[] =
                generate_image_file(
                    $item,
                    'reel',
                    'scene-' .
                        (string) (
                            $i + 1
                        )
                );
        }

        return $urls;
    }

    if (
        $type === 'carousel'
    ) {
        $items =
            array_values(
                array_filter(
                    array_map(
                        fn($value) =>
                            trim(
                                (string)
                                $value
                            ),
                        $slides
                    ),
                    fn($value) =>
                        trim(
                            (string)
                            $value
                        ) !== ''
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
 * URL PUBLIQUE
 * ========================================================= */

function public_url(
    string $path
): string {
    $scheme =
        (
            (
                $_SERVER[
                    'HTTPS'
                ] ?? ''
            ) !== '' &&
            $_SERVER[
                'HTTPS'
            ] !== 'off'
        )
            ? 'https'
            : 'http';

    $host =
        $_SERVER[
            'HTTP_HOST'
        ] ??
        'vitrineplus.fr';

    return
        $scheme .
        '://' .
        $host .
        '/' .
        ltrim(
            $path,
            '/'
        );
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
            : 'v' .
                $version;
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
        $result[
            'http_code'
        ] < 200 ||
        $result[
            'http_code'
        ] >= 300 ||
        empty(
            $result[
                'body'
            ]['id']
        )
    ) {
        throw new RuntimeException(
            meta_error(
                $result,
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
        $error[
            'message'
        ] ??
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
        as $key =>
        $label
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
        $result[
            'http_code'
        ] < 200 ||
        $result[
            'http_code'
        ] >= 300 ||
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
                $url
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
        $result[
            'http_code'
        ] < 200 ||
        $result[
            'http_code'
        ] >= 300 ||
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
 * PUBLICATION INSTAGRAM
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
            $content[
                'type'
            ] ?? 'post'
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
     * POST
     * ----------------------------------------------------- */

    if (
        $type ===
        'post'
    ) {
        if (
            !$images
        ) {
            throw new RuntimeException(
                'Aucun visuel public n’a été fourni.'
            );
        }

        $container =
            create_instagram_container(
                $accountId,
                $version,
                $token,
                [
                    'image_url' =>
                        $images[0],

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
        ];
    }


    /* -----------------------------------------------------
     * STORY
     * ----------------------------------------------------- */

    if (
        $type ===
        'story'
    ) {
        if (
            !$images
        ) {
            throw new RuntimeException(
                'Aucun visuel public n’a été fourni.'
            );
        }

        $container =
            create_instagram_container(
                $accountId,
                $version,
                $token,
                [
                    'image_url' =>
                        $images[0],

                    'media_type' =>
                        'STORIES',

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

        $images =
            array_slice(
                $images,
                0,
                10
            );

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
            $file[
                'error'
            ] ??
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
            ] ??
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

        'video/webm' =>
            'webm',
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
            'Format non supporté. Utilise JPG, MP4 ou MOV.'
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
                $content[
                    'id'
                ] ?? ''
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

function process_scheduled(): array {
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
        ] ??
        'list'
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
     * GENERATE
     * ----------------------------------------------------- */

    if (
        $action ===
        'generate'
    ) {
        $type =
            clean(
                $input[
                    'type'
                ] ??
                'post'
            );

        $topic =
            clean(
                $input[
                    'topic'
                ] ??
                ''
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
                ] ??
                'post'
            );

        $topic =
            clean(
                $input[
                    'topic'
                ] ??
                ''
            );

        $caption =
            clean(
                $input[
                    'caption'
                ] ??
                ''
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
                    ] ??
                    'post'
                ),

            'topic' =>
                clean(
                    $content[
                        'topic'
                    ] ??
                    ''
                ),

            'objective' =>
                clean(
                    $content[
                        'objective'
                    ] ??
                    ''
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
                ?:
                date('c'),

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
                    fn(
                        $item
                    ) =>
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