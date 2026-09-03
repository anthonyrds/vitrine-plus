<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$action = trim($_POST['action'] ?? '');

/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const MAX_PAGES = 50;
const MAX_CONCURRENT = 8;
const MAX_CRAWL_SECONDS = 22;
const REQUEST_TIMEOUT = 7;
const CONNECT_TIMEOUT = 4;
const MAX_RESPONSE_BYTES = 2500000;

const AUDIT_USER_AGENT = 'VitrinePlus-Audit/3.0';

/*
|--------------------------------------------------------------------------
| RÉPONSE
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
| URL
|--------------------------------------------------------------------------
*/

function normalize_url(string $url): ?string
{
    $url = trim($url);

    if ($url === '') {
        return null;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return null;
    }

    $scheme = strtolower(
        $parts['scheme'] ?? ''
    );

    if (!in_array(
        $scheme,
        ['http', 'https'],
        true
    )) {
        return null;
    }

    $host = strtolower(
        $parts['host']
    );

    if (str_starts_with(
        $host,
        'www.'
    )) {
        $host = substr(
            $host,
            4
        );
    }

    $path =
        $parts['path'] ?? '/';

    if ($path === '') {
        $path = '/';
    }

    $query = '';

    if (!empty($parts['query'])) {
        $query =
            '?' .
            $parts['query'];
    }

    $result =
        $scheme .
        '://' .
        $host .
        $path .
        $query;

    return rtrim(
        $result,
        '/'
    ) ?: $scheme . '://' . $host;
}

function normalize_host(string $host): string
{
    $host =
        strtolower(
            trim($host)
        );

    if (str_starts_with(
        $host,
        'www.'
    )) {
        $host =
            substr(
                $host,
                4
            );
    }

    return $host;
}

function origin_from_url(string $url): ?string
{
    $parts =
        parse_url($url);

    if (
        !$parts ||
        empty($parts['scheme']) ||
        empty($parts['host'])
    ) {
        return null;
    }

    $origin =
        strtolower(
            $parts['scheme']
        ) .
        '://' .
        strtolower(
            $parts['host']
        );

    if (!empty($parts['port'])) {
        $origin .= ':' .
            $parts['port'];
    }

    return $origin;
}

/*
|--------------------------------------------------------------------------
| SÉCURITÉ
|--------------------------------------------------------------------------
*/

function is_private_or_reserved_ip(
    string $ip
): bool {
    if (!filter_var(
        $ip,
        FILTER_VALIDATE_IP
    )) {
        return true;
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE |
        FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function resolve_host_ips(
    string $host
): array {
    $ips = [];

    if (function_exists(
        'dns_get_record'
    )) {
        $records = @dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        if (is_array($records)) {
            foreach (
                $records as $record
            ) {
                if (!empty(
                    $record['ip']
                )) {
                    $ips[] =
                        $record['ip'];
                }

                if (!empty(
                    $record['ipv6']
                )) {
                    $ips[] =
                        $record['ipv6'];
                }
            }
        }
    }

    if (!$ips) {
        $resolved =
            @gethostbynamel(
                $host
            );

        if (is_array(
            $resolved
        )) {
            $ips =
                array_merge(
                    $ips,
                    $resolved
                );
        }
    }

    return array_values(
        array_unique($ips)
    );
}

function is_safe_host(
    string $host
): bool {
    $host =
        strtolower(
            trim($host)
        );

    if ($host === '') {
        return false;
    }

    $blocked = [
        'localhost',
        'localhost.localdomain',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata',
        'metadata.google.internal',
    ];

    if (in_array(
        $host,
        $blocked,
        true
    )) {
        return false;
    }

    $ips =
        resolve_host_ips($host);

    if (!$ips) {
        return false;
    }

    foreach (
        $ips as $ip
    ) {
        if (
            is_private_or_reserved_ip(
                $ip
            )
        ) {
            return false;
        }
    }

    return true;
}

function same_site(
    string $url,
    string $rootHost
): bool {
    $parts =
        parse_url($url);

    if (
        !$parts ||
        empty($parts['host'])
    ) {
        return false;
    }

    return normalize_host(
        $parts['host']
    ) === normalize_host(
        $rootHost
    );
}

/*
|--------------------------------------------------------------------------
| LIENS
|--------------------------------------------------------------------------
*/

function absolute_url(
    string $href,
    string $baseUrl
): ?string {
    $href =
        trim($href);

    if ($href === '') {
        return null;
    }

    if (
        str_starts_with(
            $href,
            '#'
        ) ||
        preg_match(
            '#^(mailto:|tel:|javascript:|data:|blob:)#i',
            $href
        )
    ) {
        return null;
    }

    $href =
        preg_replace(
            '/#.*$/',
            '',
            $href
        );

    if ($href === '') {
        return null;
    }

    if (
        preg_match(
            '#^https?://#i',
            $href
        )
    ) {
        return normalize_url(
            $href
        );
    }

    $base =
        parse_url(
            $baseUrl
        );

    if (
        !$base ||
        empty($base['scheme']) ||
        empty($base['host'])
    ) {
        return null;
    }

    $scheme =
        $base['scheme'];

    $host =
        $base['host'];

    if (
        str_starts_with(
            $href,
            '//'
        )
    ) {
        return normalize_url(
            $scheme . ':' . $href
        );
    }

    if (
        str_starts_with(
            $href,
            '/'
        )
    ) {
        return normalize_url(
            $scheme .
            '://' .
            $host .
            $href
        );
    }

    $basePath =
        $base['path'] ?? '/';

    if (
        !str_ends_with(
            $basePath,
            '/'
        )
    ) {
        $basePath =
            dirname(
                $basePath
            ) . '/';
    }

    $combined =
        $basePath . $href;

    $segments = [];

    foreach (
        explode(
            '/',
            $combined
        ) as $segment
    ) {
        if (
            $segment === '' ||
            $segment === '.'
        ) {
            continue;
        }

        if ($segment === '..') {
            array_pop(
                $segments
            );
            continue;
        }

        $segments[] =
            $segment;
    }

    return normalize_url(
        $scheme .
        '://' .
        $host .
        '/' .
        implode(
            '/',
            $segments
        )
    );
}

function is_html_candidate(
    string $url
): bool {
    $path =
        strtolower(
            parse_url(
                $url,
                PHP_URL_PATH
            ) ?? ''
        );

    $extensions = [
        '.jpg',
        '.jpeg',
        '.png',
        '.gif',
        '.webp',
        '.svg',
        '.ico',
        '.avif',
        '.bmp',
        '.tiff',
        '.pdf',
        '.zip',
        '.rar',
        '.7z',
        '.mp4',
        '.mov',
        '.avi',
        '.mp3',
        '.wav',
        '.css',
        '.js',
        '.mjs',
        '.json',
        '.xml',
        '.csv',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.ppt',
        '.pptx',
        '.woff',
        '.woff2',
        '.ttf',
        '.eot'
    ];

    foreach (
        $extensions as $extension
    ) {
        if (
            str_ends_with(
                $path,
                $extension
            )
        ) {
            return false;
        }
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| FETCH
|--------------------------------------------------------------------------
*/

function fetch_url(
    string $url,
    int $timeout = REQUEST_TIMEOUT
): array {
    $start =
        microtime(true);

    $ch =
        curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_MAXFILESIZE => MAX_RESPONSE_BYTES,
            CURLOPT_USERAGENT => AUDIT_USER_AGENT,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]
    );

    $response =
        curl_exec($ch);

    $error =
        curl_error($ch);

    $status =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    $headerSize =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HEADER_SIZE
        );

    $contentType =
        (string) curl_getinfo(
            $ch,
            CURLINFO_CONTENT_TYPE
        );

    $effectiveUrl =
        (string) curl_getinfo(
            $ch,
            CURLINFO_EFFECTIVE_URL
        );

    curl_close($ch);

    $duration =
        round(
            microtime(true) -
            $start,
            3
        );

    if (
        $response === false
    ) {
        return [
            'success' => false,
            'status' => $status,
            'body' => '',
            'contentType' =>
                $contentType,
            'effectiveUrl' =>
                $effectiveUrl,
            'time' =>
                $duration,
            'error' =>
                $error,
        ];
    }

    return [
        'success' =>
            $status >= 200 &&
            $status < 400,
        'status' =>
            $status,
        'body' =>
            substr(
                $response,
                $headerSize
            ),
        'contentType' =>
            $contentType,
        'effectiveUrl' =>
            $effectiveUrl,
        'time' =>
            $duration,
        'error' => '',
    ];
}

/*
|--------------------------------------------------------------------------
| FETCH CONCURRENT
|--------------------------------------------------------------------------
*/

function fetch_batch(
    array $urls
): array {
    $multi =
        curl_multi_init();

    $handles = [];

    foreach (
        $urls as $url
    ) {
        $ch =
            curl_init($url);

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
                CURLOPT_MAXFILESIZE => MAX_RESPONSE_BYTES,
                CURLOPT_USERAGENT => AUDIT_USER_AGENT,
                CURLOPT_ENCODING => '',
                CURLOPT_HEADER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]
        );

        curl_multi_add_handle(
            $multi,
            $ch
        );

        $handles[(int) $ch] = [
            'handle' => $ch,
            'url' => $url,
            'start' =>
                microtime(true),
        ];
    }

    $running = null;

    do {
        $status =
            curl_multi_exec(
                $multi,
                $running
            );

        if ($running) {
            $select =
                curl_multi_select(
                    $multi,
                    0.5
                );

            if ($select === -1) {
                usleep(10000);
            }
        }
    } while (
        $running &&
        $status === CURLM_OK
    );

    $results = [];

    foreach (
        $handles as $data
    ) {
        $ch =
            $data['handle'];

        $url =
            $data['url'];

        $raw =
            curl_multi_getcontent(
                $ch
            );

        $statusCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $headerSize =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HEADER_SIZE
            );

        $contentType =
            (string) curl_getinfo(
                $ch,
                CURLINFO_CONTENT_TYPE
            );

        $duration =
            round(
                microtime(true) -
                $data['start'],
                3
            );

        $body = '';

        if ($raw !== false) {
            $body =
                substr(
                    $raw,
                    $headerSize
                );
        }

        $results[] = [
            'url' =>
                $url,
            'success' =>
                $raw !== false &&
                $statusCode >= 200 &&
                $statusCode < 400,
            'status' =>
                $statusCode,
            'body' =>
                $body,
            'contentType' =>
                $contentType,
            'time' =>
                $duration,
            'error' =>
                curl_error($ch),
        ];

        curl_multi_remove_handle(
            $multi,
            $ch
        );

        curl_close($ch);
    }

    curl_multi_close(
        $multi
    );

    return $results;
}

/*
|--------------------------------------------------------------------------
| DÉTECTION SPA
|--------------------------------------------------------------------------
*/

function detect_spa(
    string $html
): bool {
    $signals = 0;

    if (
        preg_match(
            '#<div[^>]+(?:id|class)=["\'][^"\']*(?:root|app|__next|__nuxt)[^"\']*["\']#i',
            $html
        )
    ) {
        $signals++;
    }

    if (
        preg_match(
            '#<script[^>]+src=["\'][^"\']*(?:/assets/|/_next/|/_nuxt/)[^"\']*["\']#i',
            $html
        )
    ) {
        $signals++;
    }

    $bodyText =
        trim(
            strip_tags($html)
        );

    $h1Count =
        preg_match_all(
            '#<h1\b[^>]*>#i',
            $html
        );

    if (
        strlen($bodyText) < 1200 &&
        $h1Count === 0
    ) {
        $signals++;
    }

    return $signals >= 2;
}

/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

function get_meta(
    DOMXPath $xpath,
    string $name
): string {
    $query = sprintf(
        '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@content',
        strtolower($name)
    );

    $node =
        $xpath
            ->query($query)
            ->item(0);

    return $node
        ? trim($node->nodeValue)
        : '';
}

function get_property(
    DOMXPath $xpath,
    string $property
): string {
    $query = sprintf(
        '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@content',
        strtolower($property)
    );

    $node =
        $xpath
            ->query($query)
            ->item(0);

    return $node
        ? trim($node->nodeValue)
        : '';
}

/*
|--------------------------------------------------------------------------
| ANALYSE HTML
|--------------------------------------------------------------------------
*/

function analyze_html(
    string $url,
    string $html,
    float $responseTime
): array {
    libxml_use_internal_errors(true);

    $dom =
        new DOMDocument();

    @$dom->loadHTML(
        '<?xml encoding="UTF-8">' .
        $html,
        LIBXML_NOWARNING |
        LIBXML_NOERROR
    );

    $xpath =
        new DOMXPath($dom);

    $titleNode =
        $xpath
            ->query('//title')
            ->item(0);

    $title =
        $titleNode
            ? trim(
                $titleNode->textContent
            )
            : '';

    $description =
        get_meta(
            $xpath,
            'description'
        );

    $viewport =
        get_meta(
            $xpath,
            'viewport'
        );

    $canonicalNode =
        $xpath
            ->query(
                '//link[contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"canonical")]/@href'
            )
            ->item(0);

    $canonical =
        $canonicalNode
            ? trim(
                $canonicalNode->nodeValue
            )
            : '';

    $htmlNode =
        $xpath
            ->query('//html')
            ->item(0);

    $lang =
        '';

    if (
        $htmlNode &&
        $htmlNode->hasAttribute('lang')
    ) {
        $lang =
            trim(
                $htmlNode
                    ->getAttribute(
                        'lang'
                    )
            );
    }

    $h1Count =
        $xpath
            ->query('//h1')
            ->length;

    $h2Count =
        $xpath
            ->query('//h2')
            ->length;

    $h3Count =
        $xpath
            ->query('//h3')
            ->length;

    $viewportCount =
        $xpath
            ->query(
                '//meta[contains(translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"viewport")]'
            )
            ->length;

    $images =
        $xpath
            ->query('//img');

    $imagesTotal =
        $images->length;

    $imagesWithoutAlt =
        0;

    foreach (
        $images as $image
    ) {
        if (
            trim(
                $image->getAttribute(
                    'alt'
                )
            ) === ''
        ) {
            $imagesWithoutAlt++;
        }
    }

    $ogTitle =
        get_property(
            $xpath,
            'og:title'
        ) !== '';

    $ogDescription =
        get_property(
            $xpath,
            'og:description'
        ) !== '';

    $ogImage =
        get_property(
            $xpath,
            'og:image'
        ) !== '';

    $ogUrl =
        get_property(
            $xpath,
            'og:url'
        ) !== '';

    $twitterCard =
        get_meta(
            $xpath,
            'twitter:card'
        ) !== '';

    $links = [];

    foreach (
        $xpath
            ->query('//a[@href]')
        as $anchor
    ) {
        $href =
            trim(
                $anchor
                    ->getAttribute(
                        'href'
                    )
            );

        if ($href !== '') {
            $links[] =
                $href;
        }
    }

    $bodyText =
        trim(
            preg_replace(
                '/\s+/u',
                ' ',
                strip_tags($html)
            )
        );

    $decodedText =
        html_entity_decode(
            $bodyText,
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

    $wordCount =
        str_word_count(
            $decodedText
        );

    $isSpa =
        detect_spa($html);

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    */

    $seo = 0;

    if ($title !== '') {
        $seo += 25;
    }

    if (
        mb_strlen($title) >= 30 &&
        mb_strlen($title) <= 65
    ) {
        $seo += 15;
    } elseif (
        mb_strlen($title) >= 20
    ) {
        $seo += 8;
    }

    if ($description !== '') {
        $seo += 20;
    }

    if (
        mb_strlen($description) >= 80 &&
        mb_strlen($description) <= 170
    ) {
        $seo += 10;
    } elseif (
        mb_strlen($description) >= 50
    ) {
        $seo += 5;
    }

    if ($canonical !== '') {
        $seo += 10;
    }

    if ($lang !== '') {
        $seo += 10;
    }

    /*
    |--------------------------------------------------------------------------
    | STRUCTURE
    |--------------------------------------------------------------------------
    */

    $structure = 0;

    if ($h1Count === 1) {
        $structure += 35;
    } elseif ($h1Count > 1) {
        $structure += 20;
    } elseif ($isSpa) {
        $structure += 25;
    }

    if ($h2Count > 0) {
        $structure += 20;
    } elseif ($isSpa) {
        $structure += 15;
    }

    if ($h3Count > 0) {
        $structure += 10;
    } elseif ($isSpa) {
        $structure += 5;
    }

    if (count($links) > 0) {
        $structure += 20;
    }

    if ($canonical !== '') {
        $structure += 10;
    }

    if ($lang !== '') {
        $structure += 5;
    }

    /*
    |--------------------------------------------------------------------------
    | MOBILE
    |--------------------------------------------------------------------------
    */

    $mobile = 0;

    if ($viewportCount > 0) {
        $mobile += 65;
    }

    if (
        stripos(
            $viewport,
            'width=device-width'
        ) !== false
    ) {
        $mobile += 15;
    }

    if ($imagesTotal === 0) {
        $mobile += 20;
    } elseif (
        $imagesWithoutAlt /
        max(
            1,
            $imagesTotal
        ) < 0.25
    ) {
        $mobile += 20;
    } else {
        $mobile += 10;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTENU
    |--------------------------------------------------------------------------
    */

    $content = 0;

    if ($title !== '') {
        $content += 15;
    }

    if ($description !== '') {
        $content += 15;
    }

    if (
        $h1Count > 0
    ) {
        $content += 20;
    } elseif ($isSpa) {
        $content += 15;
    }

    if ($h2Count >= 2) {
        $content += 15;
    } elseif (
        $h2Count === 1 ||
        $isSpa
    ) {
        $content += 10;
    }

    if ($wordCount >= 600) {
        $content += 20;
    } elseif ($wordCount >= 300) {
        $content += 15;
    } elseif ($wordCount >= 150) {
        $content += 10;
    } elseif ($isSpa) {
        $content += 8;
    }

    if ($imagesTotal > 0) {
        $content += 15;
    } elseif ($isSpa) {
        $content += 5;
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE
    |--------------------------------------------------------------------------
    */

    if ($responseTime <= 0.5) {
        $performance = 100;
    } elseif ($responseTime <= 1) {
        $performance = 90;
    } elseif ($responseTime <= 1.5) {
        $performance = 80;
    } elseif ($responseTime <= 2.5) {
        $performance = 65;
    } elseif ($responseTime <= 4) {
        $performance = 45;
    } elseif ($responseTime <= 6) {
        $performance = 30;
    } else {
        $performance = 15;
    }

    /*
    |--------------------------------------------------------------------------
    | SOCIAL
    |--------------------------------------------------------------------------
    */

    $social = 0;

    if ($ogTitle) {
        $social += 25;
    }

    if ($ogDescription) {
        $social += 25;
    }

    if ($ogImage) {
        $social += 30;
    }

    if ($ogUrl) {
        $social += 10;
    }

    if ($twitterCard) {
        $social += 10;
    }

    /*
    |--------------------------------------------------------------------------
    | CATÉGORIES
    |--------------------------------------------------------------------------
    */

    $categories = [
        'seo' =>
            min(100, $seo),

        'structure' =>
            min(100, $structure),

        'mobile' =>
            min(100, $mobile),

        'content' =>
            min(100, $content),

        'performance' =>
            min(100, $performance),

        'social' =>
            min(100, $social),
    ];

    /*
    |--------------------------------------------------------------------------
    | PROBLÈMES
    |--------------------------------------------------------------------------
    */

    $issues = [];

    if ($title === '') {
        $issues[] = [
            'key' => 'missing_title',
            'category' => 'seo',
            'message' =>
                'Ajouter une balise title claire et optimisée.'
        ];
    } elseif (
        mb_strlen($title) < 20 ||
        mb_strlen($title) > 65
    ) {
        $issues[] = [
            'key' => 'title_length',
            'category' => 'seo',
            'message' =>
                'Optimiser la longueur de la balise title.'
        ];
    }

    if ($description === '') {
        $issues[] = [
            'key' => 'missing_description',
            'category' => 'seo',
            'message' =>
                'Ajouter une meta description pertinente.'
        ];
    } elseif (
        mb_strlen($description) < 50 ||
        mb_strlen($description) > 170
    ) {
        $issues[] = [
            'key' => 'description_length',
            'category' => 'seo',
            'message' =>
                'Optimiser la longueur de la meta description.'
        ];
    }

    if (
        $h1Count === 0 &&
        !$isSpa
    ) {
        $issues[] = [
            'key' => 'missing_h1',
            'category' => 'structure',
            'message' =>
                'Ajouter un titre H1 clairement identifiable.'
        ];
    }

    if ($h1Count > 1) {
        $issues[] = [
            'key' => 'multiple_h1',
            'category' => 'structure',
            'message' =>
                'Réduire la page à un H1 principal.'
        ];
    }

    if ($viewportCount === 0) {
        $issues[] = [
            'key' => 'missing_viewport',
            'category' => 'mobile',
            'message' =>
                'Ajouter la configuration viewport pour le mobile.'
        ];
    }

    if (
        $imagesTotal > 0 &&
        $imagesWithoutAlt > 0
    ) {
        $issues[] = [
            'key' => 'image_alt',
            'category' => 'content',
            'message' =>
                $imagesWithoutAlt .
                ' image(s) sans texte alternatif.'
        ];
    }

    if ($canonical === '') {
        $issues[] = [
            'key' => 'missing_canonical',
            'category' => 'seo',
            'message' =>
                'Ajouter une URL canonique.'
        ];
    }

    if (
        !$ogTitle ||
        !$ogDescription ||
        !$ogImage
    ) {
        $issues[] = [
            'key' => 'social',
            'category' => 'social',
            'message' =>
                'Compléter les balises Open Graph pour améliorer les partages sociaux.'
        ];
    }

    if ($responseTime > 2) {
        $issues[] = [
            'key' => 'server_response',
            'category' => 'performance',
            'message' =>
                'Réduire le temps de réponse du serveur.'
        ];
    }

    if (
        $wordCount < 150 &&
        !$isSpa
    ) {
        $issues[] = [
            'key' => 'thin_content',
            'category' => 'content',
            'message' =>
                'Renforcer le contenu textuel de cette page.'
        ];
    }

    return [
        'url' =>
            $url,

        'categories' =>
            $categories,

        'issues' =>
            $issues,

        'links' =>
            array_values(
                array_unique(
                    $links
                )
            ),

        'meta' => [
            'isSpa' =>
                $isSpa,

            'wordCount' =>
                $wordCount,

            'h1' =>
                $h1Count,

            'h2' =>
                $h2Count,

            'h3' =>
                $h3Count,

            'images' =>
                $imagesTotal,

            'imagesWithoutAlt' =>
                $imagesWithoutAlt,
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| SITEMAP
|--------------------------------------------------------------------------
*/

function get_sitemap_urls(
    string $origin,
    string $rootHost
): array {
    $sitemapUrl =
        rtrim(
            $origin,
            '/'
        ) .
        '/sitemap.xml';

    $result =
        fetch_url(
            $sitemapUrl,
            5
        );

    if (
        !$result['success']
    ) {
        return [];
    }

    preg_match_all(
        '#<loc>\s*(.*?)\s*</loc>#is',
        $result['body'],
        $matches
    );

    $urls = [];

    foreach (
        $matches[1] ?? []
        as $url
    ) {
        $url =
            normalize_url(
                html_entity_decode(
                    trim($url)
                )
            );

        if (!$url) {
            continue;
        }

        if (
            !same_site(
                $url,
                $rootHost
            )
        ) {
            continue;
        }

        if (
            !is_html_candidate(
                $url
            )
        ) {
            continue;
        }

        $urls[] =
            $url;
    }

    return array_values(
        array_unique($urls)
    );
}

/*
|--------------------------------------------------------------------------
| RECOMMANDATIONS
|--------------------------------------------------------------------------
*/

function build_recommendations(
    array $issueCounts,
    array $categories,
    int $pageCount
): array {
    $recommendations = [];

    $categoryLabels = [
        'seo' =>
            'SEO',

        'structure' =>
            'structure',

        'mobile' =>
            'expérience mobile',

        'content' =>
            'contenu',

        'performance' =>
            'performance',

        'social' =>
            'partage social',
    ];

    /*
    |--------------------------------------------------------------------------
    | Priorité aux vrais problèmes détectés
    |--------------------------------------------------------------------------
    */

    foreach (
        $issueCounts as $issue
    ) {
        if (
            count(
                $recommendations
            ) >= 3
        ) {
            break;
        }

        $category =
            $issue['category'] ??
            'content';

        $label =
            $categoryLabels[
                $category
            ] ??
            $category;

        $count =
            (int) (
                $issue['count'] ??
                0
            );

        $message =
            $issue['message'] ??
            '';

        if ($count > 1) {
            $recommendations[] =
                $message .
                ' ' .
                $count .
                ' page(s) concernée(s).';
        } else {
            $recommendations[] =
                $message;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Complément selon les scores
    |--------------------------------------------------------------------------
    */

    if (
        count(
            $recommendations
        ) < 3
    ) {
        $sorted =
            $categories;

        uasort(
            $sorted,
            function (
                $a,
                $b
            ) {
                return
                    $a['score'] <=>
                    $b['score'];
            }
        );

        foreach (
            $sorted as $key => $category
        ) {
            if (
                count(
                    $recommendations
                ) >= 3
            ) {
                break;
            }

            if (
                $category['score'] >= 80
            ) {
                continue;
            }

            $label =
                $categoryLabels[
                    $key
                ] ??
                $key;

            $recommendation =
                'Renforcer votre ' .
                $label .
                ' : la catégorie obtient ' .
                $category['score'] .
                '/100.';

            if (
                !in_array(
                    $recommendation,
                    $recommendations,
                    true
                )
            ) {
                $recommendations[] =
                    $recommendation;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Secours
    |--------------------------------------------------------------------------
    */

    $fallbacks = [
        'Continuer à optimiser les pages stratégiques du site.',
        'Maintenir une structure SEO cohérente sur les nouvelles pages.',
        'Optimiser les parcours et appels à l’action pour améliorer la conversion.',
    ];

    foreach (
        $fallbacks as $fallback
    ) {
        if (
            count(
                $recommendations
            ) >= 3
        ) {
            break;
        }

        if (
            !in_array(
                $fallback,
                $recommendations,
                true
            )
        ) {
            $recommendations[] =
                $fallback;
        }
    }

    return array_slice(
        $recommendations,
        0,
        3
    );
}

/*
|--------------------------------------------------------------------------
| AUDIT COMPLET
|--------------------------------------------------------------------------
*/

function run_full_audit(
    string $inputUrl
): array {
    $normalized =
        normalize_url(
            $inputUrl
        );

    if (!$normalized) {
        respond([
            'success' => false,
            'message' =>
                'L’adresse du site est invalide.'
        ], 422);
    }

    $parts =
        parse_url(
            $normalized
        );

    if (
        !$parts ||
        empty($parts['host'])
    ) {
        respond([
            'success' => false,
            'message' =>
                'Impossible de déterminer le domaine.'
        ], 422);
    }

    $rootHost =
        normalize_host(
            $parts['host']
        );

    if (
        !is_safe_host(
            $rootHost
        )
    ) {
        respond([
            'success' => false,
            'message' =>
                'Ce domaine ne peut pas être analysé.'
        ], 422);
    }

    $origin =
        origin_from_url(
            $normalized
        );

    if (!$origin) {
        respond([
            'success' => false,
            'message' =>
                'Impossible de déterminer l’origine du site.'
        ], 422);
    }

    $start =
        microtime(true);

    /*
    |--------------------------------------------------------------------------
    | HOME
    |--------------------------------------------------------------------------
    */

    $homepage =
        fetch_url(
            $normalized,
            8
        );

    if (
        !$homepage['success']
    ) {
        respond([
            'success' => false,
            'message' =>
                'Le site n’a pas pu être chargé. Vérifiez l’adresse et réessayez.'
        ], 502);
    }

    /*
    |--------------------------------------------------------------------------
    | QUEUE
    |--------------------------------------------------------------------------
    */

    $queue = [];
    $visited = [];
    $results = [];

    $homepageUrl =
        normalize_url(
            $normalized
        );

    $queue[] =
        $homepageUrl;

    /*
    |--------------------------------------------------------------------------
    | SITEMAP
    |--------------------------------------------------------------------------
    */

    $sitemapUrls =
        get_sitemap_urls(
            $origin,
            $rootHost
        );

    foreach (
        $sitemapUrls as $url
    ) {
        if (
            !isset(
                $visited[$url]
            )
        ) {
            $queue[] =
                $url;
        }
    }

    $queue =
        array_values(
            array_unique(
                $queue
            )
        );

    /*
    |--------------------------------------------------------------------------
    | HOME
    |--------------------------------------------------------------------------
    */

    $homepageAnalysis =
        analyze_html(
            $homepageUrl,
            $homepage['body'],
            $homepage['time']
        );

    $results[] =
        $homepageAnalysis;

    $visited[
        $homepageUrl
    ] = true;

    /*
    |--------------------------------------------------------------------------
    | LIENS HOME
    |--------------------------------------------------------------------------
    */

    foreach (
        $homepageAnalysis['links']
        as $href
    ) {
        $absolute =
            absolute_url(
                $href,
                $homepageUrl
            );

        if (!$absolute) {
            continue;
        }

        if (
            !same_site(
                $absolute,
                $rootHost
            )
        ) {
            continue;
        }

        if (
            !is_html_candidate(
                $absolute
            )
        ) {
            continue;
        }

        if (
            !isset(
                $visited[$absolute]
            )
        ) {
            $queue[] =
                $absolute;
        }
    }

    $queue =
        array_values(
            array_unique(
                $queue
            )
        );

    /*
    |--------------------------------------------------------------------------
    | CRAWL
    |--------------------------------------------------------------------------
    */

    while (
        count($results) <
            MAX_PAGES &&
        !empty($queue)
    ) {
        if (
            microtime(true) -
            $start >
            MAX_CRAWL_SECONDS
        ) {
            break;
        }

        $batch = [];

        while (
            count($batch) <
                MAX_CONCURRENT &&
            !empty($queue) &&
            count($results) +
                count($batch) <
                MAX_PAGES
        ) {
            $candidate =
                array_shift(
                    $queue
                );

            if (!$candidate) {
                continue;
            }

            if (
                isset(
                    $visited[$candidate]
                )
            ) {
                continue;
            }

            if (
                !same_site(
                    $candidate,
                    $rootHost
                )
            ) {
                continue;
            }

            if (
                !is_html_candidate(
                    $candidate
                )
            ) {
                continue;
            }

            $visited[
                $candidate
            ] = true;

            $batch[] =
                $candidate;
        }

        if (!$batch) {
            break;
        }

        $responses =
            fetch_batch(
                $batch
            );

        foreach (
            $responses as $response
        ) {
            if (
                !$response['success']
            ) {
                continue;
            }

            if (
                $response['status'] <
                    200 ||
                $response['status'] >=
                    300
            ) {
                continue;
            }

            if (
                $response['contentType'] !== '' &&
                stripos(
                    $response['contentType'],
                    'text/html'
                ) === false
            ) {
                continue;
            }

            $analysis =
                analyze_html(
                    $response['url'],
                    $response['body'],
                    $response['time']
                );

            $results[] =
                $analysis;

            foreach (
                $analysis['links']
                as $href
            ) {
                $absolute =
                    absolute_url(
                        $href,
                        $analysis['url']
                    );

                if (!$absolute) {
                    continue;
                }

                if (
                    !same_site(
                        $absolute,
                        $rootHost
                    )
                ) {
                    continue;
                }

                if (
                    !is_html_candidate(
                        $absolute
                    )
                ) {
                    continue;
                }

                if (
                    !isset(
                        $visited[$absolute]
                    )
                ) {
                    $queue[] =
                        $absolute;
                }
            }
        }

        $queue =
            array_values(
                array_unique(
                    $queue
                )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | AGRÉGATION
    |--------------------------------------------------------------------------
    */

    $keys = [
        'seo',
        'structure',
        'mobile',
        'content',
        'performance',
        'social',
    ];

    $totals = [];

    foreach (
        $keys as $key
    ) {
        $totals[$key] = 0;
    }

    $issueCounts = [];

    foreach (
        $results as $result
    ) {
        foreach (
            $result['categories']
            as $key => $score
        ) {
            $totals[$key] +=
                $score;
        }

        foreach (
            $result['issues']
            as $issue
        ) {
            $key =
                $issue['key'];

            if (
                !isset(
                    $issueCounts[$key]
                )
            ) {
                $issueCounts[$key] = [
                    'key' =>
                        $key,

                    'category' =>
                        $issue['category'],

                    'message' =>
                        $issue['message'],

                    'count' =>
                        0,
                ];
            }

            $issueCounts[$key]['count']++;
        }
    }

    $pageCount =
        max(
            1,
            count($results)
        );

    $categories = [];

    foreach (
        $totals as $key => $total
    ) {
        $categories[$key] = [
            'score' =>
                (int) round(
                    $total /
                    $pageCount
                ),

            'label' =>
                $key,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SCORE GLOBAL
    |--------------------------------------------------------------------------
    */

    $weights = [
        'seo' =>
            0.25,

        'structure' =>
            0.20,

        'mobile' =>
            0.15,

        'content' =>
            0.15,

        'performance' =>
            0.15,

        'social' =>
            0.10,
    ];

    $overallScore = 0;

    foreach (
        $weights as $key => $weight
    ) {
        $overallScore +=
            $categories[$key]['score'] *
            $weight;
    }

    $overallScore =
        (int) round(
            $overallScore
        );

    /*
    |--------------------------------------------------------------------------
    | TRI DES PROBLÈMES
    |--------------------------------------------------------------------------
    */

    usort(
        $issueCounts,
        function (
            $a,
            $b
        ) {
            return
                $b['count'] <=>
                $a['count'];
        }
    );

    /*
    |--------------------------------------------------------------------------
    | RECOMMANDATIONS
    |--------------------------------------------------------------------------
    */

    $recommendations =
        build_recommendations(
            $issueCounts,
            $categories,
            $pageCount
        );

    /*
    |--------------------------------------------------------------------------
    | FORCES
    |--------------------------------------------------------------------------
    */

    $strengths = [];

    $strengthMessages = [
        'seo' =>
            'Les fondamentaux SEO sont correctement présents.',

        'structure' =>
            'La structure du site présente de bons signaux techniques.',

        'mobile' =>
            'Les principaux signaux de compatibilité mobile sont présents.',

        'content' =>
            'Les pages disposent de bases de contenu exploitables.',

        'performance' =>
            'Les temps de réponse serveur observés sont rapides.',

        'social' =>
            'Les principales métadonnées de partage social sont présentes.',
    ];

    foreach (
        $categories as $key => $category
    ) {
        if (
            $category['score'] >= 80 &&
            isset(
                $strengthMessages[$key]
            )
        ) {
            $strengths[] =
                $strengthMessages[$key];
        }
    }

    if (!$strengths) {
        $strengths = [
            'Le domaine est accessible et a pu être analysé.',
            'Plusieurs signaux techniques ont pu être contrôlés.',
            'Le site dispose de bases exploitables pour poursuivre son optimisation.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SPA
    |--------------------------------------------------------------------------
    */

    $spaPages = 0;

    foreach (
        $results as $result
    ) {
        if (
            !empty(
                $result['meta']['isSpa']
            )
        ) {
            $spaPages++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TEMPS
    |--------------------------------------------------------------------------
    */

    $elapsed =
        round(
            microtime(true) -
            $start,
            1
        );

    /*
    |--------------------------------------------------------------------------
    | RÉSULTAT
    |--------------------------------------------------------------------------
    */

    return [
        'success' =>
            true,

        'score' =>
            max(
                0,
                min(
                    100,
                    $overallScore
                )
            ),

        'pagesAnalyzed' =>
            count(
                $results
            ),

        'pagesDiscovered' =>
            max(
                count($results),
                count(
                    array_unique(
                        array_merge(
                            array_keys(
                                $visited
                            ),
                            $queue
                        )
                    )
                )
            ),

        'categories' =>
            $categories,

        /*
        | IMPORTANT :
        | Le frontend actuel attend des strings.
        */

        'recommendations' =>
            $recommendations,

        'strengths' =>
            array_slice(
                $strengths,
                0,
                5
            ),

        'responseTime' =>
            $elapsed,

        'technical' => [
            'isSpa' =>
                $spaPages > 0,

            'spaPages' =>
                $spaPages,

            'pages' =>
                count(
                    $results
                ),
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| ROUTE AUDIT
|--------------------------------------------------------------------------
*/

if (
    $action === 'analyze'
) {
    $url =
        trim(
            $_POST['url'] ?? ''
        );

    if ($url === '') {
        respond([
            'success' => false,
            'message' =>
                'Veuillez renseigner l’adresse de votre site.'
        ], 422);
    }

    try {
        $result =
            run_full_audit(
                $url
            );

        respond(
            $result
        );
    } catch (
        Throwable $e
    ) {
        respond([
            'success' => false,
            'message' =>
                'Une erreur est survenue pendant l’analyse.'
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| ANCIEN FORMULAIRE
|--------------------------------------------------------------------------
*/

$to =
    'vitrineplus@hotmail.com';

$name =
    trim(
        $_POST['name'] ?? ''
    );

$company =
    trim(
        $_POST['company'] ?? ''
    );

$email =
    trim(
        $_POST['email'] ?? ''
    );

$phone =
    trim(
        $_POST['phone'] ?? ''
    );

$website =
    trim(
        $_POST['website'] ?? ''
    );

$goal =
    trim(
        $_POST['goal'] ?? ''
    );

$budget =
    trim(
        $_POST['budget'] ?? ''
    );

$message =
    trim(
        $_POST['message'] ?? ''
    );

if (
    $name === '' ||
    $company === '' ||
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) ||
    $message === ''
) {
    respond([
        'success' => false,
        'message' =>
            'Informations obligatoires manquantes.'
    ], 422);
}

$subject =
    'Nouvel audit digital Vitrine+ — ' .
    $company;

$body =
    "Nouvelle demande d'audit depuis vitrineplus.fr\n\n" .
    "Nom : $name\n" .
    "Entreprise : $company\n" .
    "Email : $email\n" .
    "Téléphone : $phone\n" .
    "Site : $website\n" .
    "Objectif : $goal\n" .
    "Budget : $budget\n\n" .
    "Projet :\n" .
    $message .
    "\n";

$headers =
    "From: Vitrine+ <no-reply@vitrineplus.fr>\r\n" .
    "Reply-To: " .
    $email .
    "\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

$sent =
    mail(
        $to,
        $subject,
        $body,
        $headers
    );

if (!$sent) {
    respond([
        'success' => false,
        'message' =>
            'Envoi impossible.'
    ], 500);
}

respond([
    'success' => true
]);