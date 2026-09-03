<?php

declare(strict_types=1);

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
const MAX_SITEMAP_BYTES = 1500000;

const AUDIT_USER_AGENT = 'VitrinePlus-Audit/4.0';

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

    $scheme = strtolower($parts['scheme'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower($parts['host']);

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    $path = $parts['path'] ?? '/';

    if ($path === '') {
        $path = '/';
    }

    $query = '';

    if (!empty($parts['query'])) {
        $query = '?' . $parts['query'];
    }

    $normalized =
        $scheme .
        '://' .
        $host .
        $path .
        $query;

    return rtrim($normalized, '/') ?: $scheme . '://' . $host;
}

function normalize_host(string $host): string
{
    $host = strtolower(trim($host));

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return $host;
}

function origin_from_url(string $url): ?string
{
    $parts = parse_url($url);

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }

    $origin =
        strtolower($parts['scheme']) .
        '://' .
        strtolower($parts['host']);

    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
}

/*
|--------------------------------------------------------------------------
| SÉCURITÉ SSRF
|--------------------------------------------------------------------------
*/

function is_private_or_reserved_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $ranges = [
            ['0.0.0.0', '0.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['100.64.0.0', '100.127.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.0.0', '192.0.0.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['198.18.0.0', '198.19.255.255'],
            ['224.0.0.0', '255.255.255.255'],
        ];

        foreach ($ranges as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);

            if ($long >= $startLong && $long <= $endLong) {
                return true;
            }
        }

        return false;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $lower = strtolower($ip);

        if ($lower === '::1') {
            return true;
        }

        if (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
            return true;
        }

        if (str_starts_with($lower, 'fe8') ||
            str_starts_with($lower, 'fe9') ||
            str_starts_with($lower, 'fea') ||
            str_starts_with($lower, 'feb')) {
            return true;
        }

        return false;
    }

    return true;
}

function resolve_host_ips(string $host): array
{
    $ips = [];

    $records = @dns_get_record(
        $host,
        DNS_A | DNS_AAAA
    );

    if (is_array($records)) {
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
    }

    if (!$ips) {
        $resolved = @gethostbynamel($host);

        if (is_array($resolved)) {
            $ips = array_merge($ips, $resolved);
        }
    }

    return array_values(array_unique($ips));
}

function is_safe_host(string $host): bool
{
    $blocked = [
        'localhost',
        'localhost.localdomain',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata',
        'metadata.google.internal',
    ];

    if (in_array($host, $blocked, true)) {
        return false;
    }

    if (
        filter_var(
            $host,
            FILTER_VALIDATE_IP
        )
    ) {
        return !is_private_or_reserved_ip($host);
    }

    $ips = resolve_host_ips($host);

    if (!$ips) {
        return false;
    }

    foreach ($ips as $ip) {
        if (is_private_or_reserved_ip($ip)) {
            return false;
        }
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| DOMAINE
|--------------------------------------------------------------------------
*/

function same_site(string $url, string $rootHost): bool
{
    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return false;
    }

    $host = normalize_host($parts['host']);

    return $host === $rootHost;
}

function is_html_candidate(string $url): bool
{
    $parts = parse_url($url);

    if (!$parts) {
        return false;
    }

    $path = strtolower($parts['path'] ?? '');

    $blockedExtensions = [
        '.jpg',
        '.jpeg',
        '.png',
        '.gif',
        '.webp',
        '.svg',
        '.ico',
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
        '.json',
        '.xml',
        '.txt',
        '.woff',
        '.woff2',
        '.ttf',
        '.eot',
        '.webmanifest',
    ];

    foreach ($blockedExtensions as $extension) {
        if (str_ends_with($path, $extension)) {
            return false;
        }
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| RÉSOLUTION DES LIENS
|--------------------------------------------------------------------------
*/

function resolve_url(string $baseUrl, string $href): ?string
{
    $href = trim(html_entity_decode($href));

    if ($href === '') {
        return null;
    }

    if (
        str_starts_with($href, '#') ||
        str_starts_with($href, 'mailto:') ||
        str_starts_with($href, 'tel:') ||
        str_starts_with($href, 'javascript:')
    ) {
        return null;
    }

    if (preg_match('#^https?://#i', $href)) {
        return normalize_url($href);
    }

    $base = parse_url($baseUrl);

    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }

    $origin =
        $base['scheme'] .
        '://' .
        $base['host'];

    if (!empty($base['port'])) {
        $origin .= ':' . $base['port'];
    }

    if (str_starts_with($href, '//')) {
        return normalize_url(
            $base['scheme'] . ':' . $href
        );
    }

    if (str_starts_with($href, '/')) {
        return normalize_url(
            $origin . $href
        );
    }

    $basePath = $base['path'] ?? '/';

    $directory = rtrim(
        dirname($basePath),
        '/'
    );

    if ($directory === '') {
        $directory = '';
    }

    return normalize_url(
        $origin .
        $directory .
        '/' .
        ltrim($href, '/')
    );
}

/*
|--------------------------------------------------------------------------
| HTTP
|--------------------------------------------------------------------------
*/

function fetch_url(
    string $url,
    int $timeout = REQUEST_TIMEOUT,
    int $maxBytes = MAX_RESPONSE_BYTES
): array {
    $started = microtime(true);

    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return [
            'success' => false,
            'status' => 0,
            'body' => '',
            'contentType' => '',
            'responseTime' => 0,
            'bytes' => 0,
            'error' => 'URL invalide'
        ];
    }

    $host = normalize_host($parts['host']);

    if (!is_safe_host($host)) {
        return [
            'success' => false,
            'status' => 0,
            'body' => '',
            'contentType' => '',
            'responseTime' => 0,
            'bytes' => 0,
            'error' => 'Domaine non autorisé'
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => AUDIT_USER_AGENT,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.7',
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body = curl_exec($ch);

    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $elapsed = microtime(true) - $started;

    if ($body === false) {
        curl_close($ch);

        return [
            'success' => false,
            'status' => $status,
            'body' => '',
            'contentType' => $contentType,
            'responseTime' => round($elapsed, 3),
            'bytes' => 0,
            'error' => $error ?: 'Erreur HTTP'
        ];
    }

    curl_close($ch);

    if (strlen($body) > $maxBytes) {
        $body = substr($body, 0, $maxBytes);
    }

    $redirectLocation = '';

    if (
        $status >= 300 &&
        $status < 400
    ) {
        /*
         * Nous ne suivons volontairement pas les redirections
         * afin d'éviter les risques SSRF.
         */
        $headers = substr(
            $body,
            0,
            min(strlen($body), $headerSize)
        );

        if (
            preg_match(
                '/^Location:\s*(.+)$/im',
                $headers,
                $matches
            )
        ) {
            $redirectLocation = trim($matches[1]);
        }
    }

    return [
        'success' => $status >= 200 && $status < 400,
        'status' => $status,
        'body' => $body,
        'contentType' => $contentType,
        'responseTime' => round($elapsed, 3),
        'bytes' => strlen($body),
        'error' => $error,
        'redirect' => $redirectLocation,
    ];
}

/*
|--------------------------------------------------------------------------
| CURL MULTI
|--------------------------------------------------------------------------
*/

function fetch_batch(array $urls): array
{
    $multi = curl_multi_init();

    $handles = [];
    $responses = [];

    foreach ($urls as $url) {
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            continue;
        }

        $host = normalize_host($parts['host']);

        if (!is_safe_host($host)) {
            continue;
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_USERAGENT => AUDIT_USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: fr-FR,fr;q=0.9,en;q=0.7',
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_multi_add_handle($multi, $ch);

        $handles[(int) $ch] = [
            'handle' => $ch,
            'url' => $url,
            'started' => microtime(true),
        ];
    }

    do {
        $status = curl_multi_exec($multi, $running);

        if ($running) {
            curl_multi_select($multi, 0.2);
        }
    } while (
        $running &&
        $status === CURLM_OK
    );

    foreach ($handles as $data) {
        $ch = $data['handle'];

        $body = curl_multi_getcontent($ch);

        $statusCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $contentType =
            (string) curl_getinfo(
                $ch,
                CURLINFO_CONTENT_TYPE
            );

        $totalTime =
            (float) curl_getinfo(
                $ch,
                CURLINFO_TOTAL_TIME
            );

        $error = curl_error($ch);

        if ($body === false) {
            $body = '';
        }

        if (strlen($body) > MAX_RESPONSE_BYTES) {
            $body = substr(
                $body,
                0,
                MAX_RESPONSE_BYTES
            );
        }

        $responses[] = [
            'url' => $data['url'],
            'success' =>
                $statusCode >= 200 &&
                $statusCode < 400 &&
                $error === '',
            'status' => $statusCode,
            'body' => $body,
            'contentType' => $contentType,
            'responseTime' => round($totalTime, 3),
            'bytes' => strlen($body),
            'error' => $error,
        ];

        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);

    return $responses;
}

/*
|--------------------------------------------------------------------------
| DOM
|--------------------------------------------------------------------------
*/

function get_meta(
    DOMXPath $xpath,
    string $name
): string {
    $query = sprintf(
        '//meta[
            translate(
                @name,
                "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                "abcdefghijklmnopqrstuvwxyz"
            )="%s"
        ]/@content',
        strtolower($name)
    );

    $node = $xpath
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
        '//meta[
            translate(
                @property,
                "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                "abcdefghijklmnopqrstuvwxyz"
            )="%s"
        ]/@content',
        strtolower($property)
    );

    $node = $xpath
        ->query($query)
        ->item(0);

    return $node
        ? trim($node->nodeValue)
        : '';
}

/*
|--------------------------------------------------------------------------
| SPA
|--------------------------------------------------------------------------
*/

function detect_spa(string $html): bool
{
    $signals = 0;

    if (
        preg_match(
            '#<(?:div|main|body)[^>]+(?:id|class)=["\'][^"\']*(?:root|app|__next|__nuxt)[^"\']*["\']#i',
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

    $text = trim(
        preg_replace(
            '/\s+/',
            ' ',
            strip_tags($html)
        )
    );

    $h1Count = preg_match_all(
        '#<h1\b[^>]*>#i',
        $html
    );

    if (
        strlen($text) < 1200 &&
        $h1Count === 0
    ) {
        $signals++;
    }

    return $signals >= 2;
}

/*
|--------------------------------------------------------------------------
| ANALYSE D'UNE PAGE
|--------------------------------------------------------------------------
*/

function analyze_html(
    string $url,
    string $html,
    float $responseTime,
    int $httpStatus,
    int $bytes
): array {
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    @$dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_NOWARNING |
        LIBXML_NOERROR
    );

    $xpath = new DOMXPath($dom);

    /*
     * TITLE
     */

    $titleNode = $xpath
        ->query('//title')
        ->item(0);

    $title = $titleNode
        ? trim($titleNode->textContent)
        : '';

    /*
     * META
     */

    $description = get_meta(
        $xpath,
        'description'
    );

    $viewport = get_meta(
        $xpath,
        'viewport'
    );

    $robots = get_meta(
        $xpath,
        'robots'
    );

    /*
     * CANONICAL
     */

    $canonicalNode = $xpath
        ->query(
            '//link[
                contains(
                    translate(
                        @rel,
                        "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                        "abcdefghijklmnopqrstuvwxyz"
                    ),
                    "canonical"
                )
            ]/@href'
        )
        ->item(0);

    $canonical = $canonicalNode
        ? trim($canonicalNode->nodeValue)
        : '';

    /*
     * LANG
     */

    $htmlNode = $xpath
        ->query('//html')
        ->item(0);

    $lang = '';

    if (
        $htmlNode &&
        $htmlNode->hasAttribute('lang')
    ) {
        $lang = trim(
            $htmlNode->getAttribute('lang')
        );
    }

    /*
     * HEADINGS
     */

    $h1Nodes = $xpath->query('//h1');
    $h2Nodes = $xpath->query('//h2');
    $h3Nodes = $xpath->query('//h3');

    $h1Count = $h1Nodes->length;
    $h2Count = $h2Nodes->length;
    $h3Count = $h3Nodes->length;

    $h1Texts = [];

    foreach ($h1Nodes as $node) {
        $text = trim(
            preg_replace(
                '/\s+/',
                ' ',
                $node->textContent
            )
        );

        if ($text !== '') {
            $h1Texts[] = $text;
        }
    }

    /*
     * IMAGES
     */

    $images = $xpath->query('//img');

    $imagesTotal = $images->length;
    $imagesWithoutAlt = 0;

    foreach ($images as $image) {
        if (
            !$image->hasAttribute('alt')
        ) {
            $imagesWithoutAlt++;
            continue;
        }

        /*
         * alt="" est acceptable pour une image décorative.
         */
        $alt = trim(
            $image->getAttribute('alt')
        );

        if (
            $alt === '' &&
            $image->hasAttribute('role') &&
            strtolower(
                $image->getAttribute('role')
            ) !== 'presentation'
        ) {
            $imagesWithoutAlt++;
        }
    }

    /*
     * SOCIAL
     */

    $ogTitle =
        get_property(
            $xpath,
            'og:title'
        );

    $ogDescription =
        get_property(
            $xpath,
            'og:description'
        );

    $ogImage =
        get_property(
            $xpath,
            'og:image'
        );

    $ogUrl =
        get_property(
            $xpath,
            'og:url'
        );

    $twitterCard =
        get_meta(
            $xpath,
            'twitter:card'
        );

    /*
     * LIENS
     */

    $links = [];

    foreach (
        $xpath->query('//a[@href]')
        as $anchor
    ) {
        $href = trim(
            $anchor->getAttribute('href')
        );

        $resolved = resolve_url(
            $url,
            $href
        );

        if ($resolved) {
            $links[] = $resolved;
        }
    }

    $links = array_values(
        array_unique($links)
    );

    /*
     * CONTENU
     */

    $bodyText = '';

    $bodyNode = $xpath
        ->query('//body')
        ->item(0);

    if ($bodyNode) {
        $bodyText =
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $bodyNode->textContent
                )
            );
    } else {
        $bodyText =
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    strip_tags($html)
                )
            );
    }

    $words = preg_split(
        '/\s+/u',
        $bodyText,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $wordCount = is_array($words)
        ? count($words)
        : 0;

    /*
     * SCRIPTS / STYLES
     */

    $scriptCount =
        $xpath->query('//script')->length;

    $stylesheetCount =
        $xpath->query('//link[@rel="stylesheet"]')->length;

    /*
     * SPA
     */

    $isSpa = detect_spa($html);

    /*
     * INDEXABILITÉ
     */

    $noindex =
        stripos(
            $robots,
            'noindex'
        ) !== false;

    /*
     * SEO
     *
     * 100 points
     */

    $seo = 0;

    if ($title !== '') {
        $seo += 20;
    }

    if (
        mb_strlen($title) >= 30 &&
        mb_strlen($title) <= 65
    ) {
        $seo += 10;
    }

    if ($description !== '') {
        $seo += 20;
    }

    if (
        mb_strlen($description) >= 70 &&
        mb_strlen($description) <= 170
    ) {
        $seo += 10;
    }

    if ($canonical !== '') {
        $seo += 10;
    }

    if ($lang !== '') {
        $seo += 10;
    }

    if (!$noindex) {
        $seo += 10;
    }

    if ($h1Count === 1) {
        $seo += 10;
    }

    /*
     * STRUCTURE
     */

    $structure = 0;

    if ($h1Count === 1) {
        $structure += 25;
    } elseif ($h1Count > 1) {
        $structure += 10;
    } elseif ($isSpa) {
        $structure += 15;
    }

    if ($h2Count > 0) {
        $structure += 20;
    } elseif ($isSpa) {
        $structure += 10;
    }

    if ($h3Count > 0) {
        $structure += 10;
    }

    if (count($links) >= 3) {
        $structure += 20;
    } elseif (count($links) > 0) {
        $structure += 10;
    }

    if ($canonical !== '') {
        $structure += 10;
    }

    if ($lang !== '') {
        $structure += 5;
    }

    if ($isSpa) {
        $structure += 10;
    }

    /*
     * MOBILE
     */

    $mobile = 0;

    if ($viewport !== '') {
        $mobile += 60;
    }

    if (
        stripos(
            $viewport,
            'width=device-width'
        ) !== false
    ) {
        $mobile += 20;
    }

    if (
        stripos(
            $viewport,
            'initial-scale=1'
        ) !== false
    ) {
        $mobile += 20;
    }

    /*
     * CONTENU
     */

    $content = 0;

    if ($wordCount >= 300) {
        $content += 40;
    } elseif ($wordCount >= 150) {
        $content += 30;
    } elseif ($wordCount >= 80) {
        $content += 20;
    } elseif ($isSpa) {
        $content += 15;
    }

    if ($h1Count === 1) {
        $content += 20;
    } elseif ($isSpa) {
        $content += 10;
    }

    if ($h2Count >= 2) {
        $content += 15;
    } elseif ($h2Count === 1) {
        $content += 10;
    }

    if ($imagesTotal === 0) {
        $content += 5;
    } elseif (
        $imagesWithoutAlt === 0
    ) {
        $content += 15;
    } else {
        $content += 5;
    }

    if (count($links) >= 3) {
        $content += 5;
    }

    /*
     * PERFORMANCE TECHNIQUE
     *
     * Important :
     * ce n'est PAS un Core Web Vitals.
     * On mesure le serveur + poids HTML.
     */

    $performance = 0;

    if ($responseTime <= 0.4) {
        $performance += 65;
    } elseif ($responseTime <= 0.8) {
        $performance += 55;
    } elseif ($responseTime <= 1.2) {
        $performance += 45;
    } elseif ($responseTime <= 2) {
        $performance += 30;
    } elseif ($responseTime <= 3) {
        $performance += 20;
    } else {
        $performance += 10;
    }

    if ($bytes <= 500000) {
        $performance += 35;
    } elseif ($bytes <= 1000000) {
        $performance += 25;
    } elseif ($bytes <= 1800000) {
        $performance += 15;
    } else {
        $performance += 5;
    }

    /*
     * SOCIAL
     */

    $social = 0;

    if ($ogTitle !== '') {
        $social += 25;
    }

    if ($ogDescription !== '') {
        $social += 25;
    }

    if ($ogImage !== '') {
        $social += 25;
    }

    if ($ogUrl !== '') {
        $social += 15;
    }

    if ($twitterCard !== '') {
        $social += 10;
    }

    /*
     * NORMALISATION
     */

    $categories = [
        'seo' => min(100, $seo),
        'structure' => min(100, $structure),
        'mobile' => min(100, $mobile),
        'content' => min(100, $content),
        'performance' => min(100, $performance),
        'social' => min(100, $social),
    ];

    /*
     * PROBLÈMES
     */

    $issues = [];

    if ($title === '') {
        $issues[] = [
            'key' => 'missing_title',
            'category' => 'seo',
            'message' => 'Ajouter une balise title sur cette page.'
        ];
    } elseif (
        mb_strlen($title) < 30 ||
        mb_strlen($title) > 65
    ) {
        $issues[] = [
            'key' => 'title_length',
            'category' => 'seo',
            'message' => 'Optimiser la longueur du title de cette page.'
        ];
    }

    if ($description === '') {
        $issues[] = [
            'key' => 'missing_description',
            'category' => 'seo',
            'message' => 'Ajouter une meta description pertinente.'
        ];
    } elseif (
        mb_strlen($description) < 70 ||
        mb_strlen($description) > 170
    ) {
        $issues[] = [
            'key' => 'description_length',
            'category' => 'seo',
            'message' => 'Optimiser la longueur de la meta description.'
        ];
    }

    if ($canonical === '') {
        $issues[] = [
            'key' => 'missing_canonical',
            'category' => 'seo',
            'message' => 'Ajouter une URL canonique à cette page.'
        ];
    }

    if ($lang === '') {
        $issues[] = [
            'key' => 'missing_lang',
            'category' => 'seo',
            'message' => 'Déclarer la langue du document avec l’attribut lang.'
        ];
    }

    if ($noindex) {
        $issues[] = [
            'key' => 'noindex',
            'category' => 'seo',
            'message' => 'Cette page contient une directive noindex.'
        ];
    }

    if (
        $h1Count === 0 &&
        !$isSpa
    ) {
        $issues[] = [
            'key' => 'missing_h1',
            'category' => 'structure',
            'message' => 'Ajouter un H1 principal à cette page.'
        ];
    }

    if ($h1Count > 1) {
        $issues[] = [
            'key' => 'multiple_h1',
            'category' => 'structure',
            'message' => 'Limiter la page à un H1 principal.'
        ];
    }

    if (
        $h2Count === 0 &&
        !$isSpa &&
        $wordCount >= 200
    ) {
        $issues[] = [
            'key' => 'missing_h2',
            'category' => 'structure',
            'message' => 'Structurer le contenu avec des titres H2.'
        ];
    }

    if ($viewport === '') {
        $issues[] = [
            'key' => 'missing_viewport',
            'category' => 'mobile',
            'message' => 'Ajouter une configuration viewport adaptée au mobile.'
        ];
    }

    if ($imagesWithoutAlt > 0) {
        $issues[] = [
            'key' => 'image_alt',
            'category' => 'content',
            'message' =>
                $imagesWithoutAlt .
                ' image(s) nécessitent un texte alternatif.'
        ];
    }

    if (
        $wordCount < 150 &&
        !$isSpa
    ) {
        $issues[] = [
            'key' => 'thin_content',
            'category' => 'content',
            'message' => 'Renforcer le contenu textuel de cette page.'
        ];
    }

    if ($responseTime > 2) {
        $issues[] = [
            'key' => 'slow_server',
            'category' => 'performance',
            'message' => 'Le temps de réponse serveur est élevé sur cette page.'
        ];
    }

    if ($bytes > 1800000) {
        $issues[] = [
            'key' => 'large_html',
            'category' => 'performance',
            'message' => 'Le document HTML est particulièrement volumineux.'
        ];
    }

    if ($ogTitle === '') {
        $issues[] = [
            'key' => 'missing_og_title',
            'category' => 'social',
            'message' => 'Ajouter og:title pour améliorer les partages sociaux.'
        ];
    }

    if ($ogDescription === '') {
        $issues[] = [
            'key' => 'missing_og_description',
            'category' => 'social',
            'message' => 'Ajouter og:description pour les partages sociaux.'
        ];
    }

    if ($ogImage === '') {
        $issues[] = [
            'key' => 'missing_og_image',
            'category' => 'social',
            'message' => 'Ajouter une image Open Graph.'
        ];
    }

    return [
        'url' => $url,
        'status' => $httpStatus,
        'categories' => $categories,
        'issues' => $issues,
        'links' => array_values(
            array_unique($links)
        ),
        'meta' => [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'lang' => $lang,
            'robots' => $robots,
            'isSpa' => $isSpa,
            'wordCount' => $wordCount,
            'h1' => $h1Count,
            'h1Texts' => $h1Texts,
            'h2' => $h2Count,
            'h3' => $h3Count,
            'images' => $imagesTotal,
            'imagesWithoutAlt' => $imagesWithoutAlt,
            'ogTitle' => $ogTitle !== '',
            'ogDescription' => $ogDescription !== '',
            'ogImage' => $ogImage !== '',
            'ogUrl' => $ogUrl !== '',
            'twitterCard' => $twitterCard !== '',
            'scripts' => $scriptCount,
            'stylesheets' => $stylesheetCount,
        ],
        'technical' => [
            'responseTime' => $responseTime,
            'htmlBytes' => $bytes,
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
        rtrim($origin, '/') .
        '/sitemap.xml';

    $result = fetch_url(
        $sitemapUrl,
        5,
        MAX_SITEMAP_BYTES
    );

    if (
        !$result['success'] ||
        $result['status'] < 200 ||
        $result['status'] >= 300
    ) {
        return [];
    }

    $body = $result['body'];

    preg_match_all(
        '#<loc>\s*(.*?)\s*</loc>#is',
        $body,
        $matches
    );

    $urls = [];

    foreach (
        $matches[1] ?? [] as $rawUrl
    ) {
        $url = normalize_url(
            html_entity_decode(
                trim($rawUrl)
            )
        );

        if (!$url) {
            continue;
        }

        if (!same_site($url, $rootHost)) {
            continue;
        }

        if (!is_html_candidate($url)) {
            continue;
        }

        $urls[] = $url;
    }

    return array_values(
        array_unique($urls)
    );
}

/*
|--------------------------------------------------------------------------
| PROBLÈMES AGRÉGÉS
|--------------------------------------------------------------------------
*/

function aggregate_issues(
    array $results
): array {
    $counts = [];

    foreach ($results as $result) {
        foreach (
            $result['issues'] ?? [] as $issue
        ) {
            $key = $issue['key'];

            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'key' => $key,
                    'category' =>
                        $issue['category'] ?? 'content',
                    'message' =>
                        $issue['message'] ?? '',
                    'count' => 0,
                ];
            }

            $counts[$key]['count']++;
        }
    }

    $counts = array_values($counts);

    usort(
        $counts,
        function ($a, $b) {
            return $b['count'] <=> $a['count'];
        }
    );

    return $counts;
}

/*
|--------------------------------------------------------------------------
| RECOMMANDATIONS
|--------------------------------------------------------------------------
*/

function build_recommendations(
    array $issueCounts
): array {
    $recommendations = [];

    foreach ($issueCounts as $issue) {
        if (count($recommendations) >= 3) {
            break;
        }

        $count = (int) (
            $issue['count'] ?? 0
        );

        $message =
            $issue['message'] ?? '';

        if ($message === '') {
            continue;
        }

        if ($count > 1) {
            $recommendation =
                $message .
                ' ' .
                $count .
                ' pages concernées.';
        } else {
            $recommendation = $message;
        }

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

    $fallbacks = [
        'Optimiser en priorité les pages qui génèrent le plus de visibilité et de conversions.',
        'Renforcer le maillage interne entre les pages stratégiques du site.',
        'Améliorer les parcours et appels à l’action sur les pages importantes.'
    ];

    foreach ($fallbacks as $fallback) {
        if (count($recommendations) >= 3) {
            break;
        }

        if (
            !in_array(
                $fallback,
                $recommendations,
                true
            )
        ) {
            $recommendations[] = $fallback;
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
| FORCES
|--------------------------------------------------------------------------
*/

function build_strengths(
    array $categories,
    array $results
): array {
    $strengths = [];

    $messages = [
        'seo' =>
            'Les fondamentaux SEO sont correctement présents.',
        'structure' =>
            'La structure technique du site présente de bons signaux.',
        'mobile' =>
            'La configuration mobile détectée est solide.',
        'content' =>
            'Le contenu des pages présente une base exploitable.',
        'performance' =>
            'Les temps de réponse serveur observés sont rapides.',
        'social' =>
            'Les métadonnées principales de partage social sont présentes.',
    ];

    foreach ($categories as $key => $category) {
        if (
            ($category['score'] ?? 0) >= 80 &&
            isset($messages[$key])
        ) {
            $strengths[] = $messages[$key];
        }
    }

    /*
     * Force supplémentaire si le crawl est conséquent.
     */

    if (
        count($results) >= 10
    ) {
        $strengths[] =
            'Le site dispose d’une architecture suffisamment accessible pour permettre un crawl multi-pages.';
    }

    /*
     * Détection SPA : ce n'est pas un défaut.
     */

    $spaPages = 0;

    foreach ($results as $result) {
        if (
            !empty(
                $result['meta']['isSpa']
            )
        ) {
            $spaPages++;
        }
    }

    if (
        $spaPages > 0 &&
        $spaPages === count($results)
    ) {
        $strengths[] =
            'Le site utilise une architecture applicative moderne de type SPA.';
    }

    if (!$strengths) {
        $strengths = [
            'Le domaine est accessible et a pu être analysé.',
            'Plusieurs signaux techniques ont pu être contrôlés.',
            'Le site dispose de bases exploitables pour poursuivre son optimisation.'
        ];
    }

    return array_slice(
        array_values(
            array_unique($strengths)
        ),
        0,
        5
    );
}

/*
|--------------------------------------------------------------------------
| SCORE GLOBAL
|--------------------------------------------------------------------------
*/

function calculate_overall_score(
    array $categories
): int {
    $weights = [
        'seo' => 0.25,
        'structure' => 0.20,
        'mobile' => 0.15,
        'content' => 0.15,
        'performance' => 0.15,
        'social' => 0.10,
    ];

    $score = 0;

    foreach ($weights as $key => $weight) {
        $score +=
            ($categories[$key]['score'] ?? 0) *
            $weight;
    }

    return (int) round(
        max(
            0,
            min(
                100,
                $score
            )
        )
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
    $started = microtime(true);

    $normalized =
        normalize_url($inputUrl);

    if (!$normalized) {
        respond([
            'success' => false,
            'message' =>
                'L’adresse du site est invalide.'
        ], 422);
    }

    $parts =
        parse_url($normalized);

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
        !is_safe_host($rootHost)
    ) {
        respond([
            'success' => false,
            'message' =>
                'Ce domaine ne peut pas être analysé.'
        ], 422);
    }

    $origin =
        origin_from_url($normalized);

    if (!$origin) {
        respond([
            'success' => false,
            'message' =>
                'Impossible de déterminer l’origine du site.'
        ], 422);
    }

    /*
     * HOME
     */

    $homepage =
        fetch_url(
            $normalized,
            8
        );

    if (
        !$homepage['success'] ||
        $homepage['status'] < 200 ||
        $homepage['status'] >= 300
    ) {
        respond([
            'success' => false,
            'message' =>
                'Le site n’a pas pu être chargé. Vérifiez l’adresse et réessayez.'
        ], 422);
    }

    /*
     * QUEUE
     */

    $queue = [];
    $queued = [];

    $addToQueue = function (
        string $url
    ) use (
        &$queue,
        &$queued,
        $rootHost
    ): void {
        $normalizedUrl =
            normalize_url($url);

        if (!$normalizedUrl) {
            return;
        }

        if (
            !same_site(
                $normalizedUrl,
                $rootHost
            )
        ) {
            return;
        }

        if (
            !is_html_candidate(
                $normalizedUrl
            )
        ) {
            return;
        }

        if (
            isset(
                $queued[$normalizedUrl]
            )
        ) {
            return;
        }

        if (
            count($queue) >= MAX_PAGES
        ) {
            return;
        }

        $queued[$normalizedUrl] = true;
        $queue[] = $normalizedUrl;
    };

    $addToQueue($normalized);

    /*
     * SITEMAP
     */

    $sitemapUrls =
        get_sitemap_urls(
            $origin,
            $rootHost
        );

    foreach ($sitemapUrls as $url) {
        $addToQueue($url);

        if (
            count($queue) >= MAX_PAGES
        ) {
            break;
        }
    }

    /*
     * PREMIÈRE PAGE
     */

    $results = [];

    $homepageAnalysis =
        analyze_html(
            $normalized,
            $homepage['body'],
            $homepage['responseTime'],
            $homepage['status'],
            $homepage['bytes']
        );

    $results[$normalized] =
        $homepageAnalysis;

    /*
     * Ajouter les liens de la home.
     */

    foreach (
        $homepageAnalysis['links'] as $link
    ) {
        $addToQueue($link);
    }

    /*
     * CRAWL
     */

    $processed = [
        $normalized => true
    ];

    while (
        count($results) < MAX_PAGES &&
        (microtime(true) - $started) <
            MAX_CRAWL_SECONDS
    ) {
        $batch = [];

        foreach ($queue as $url) {
            if (
                isset(
                    $processed[$url]
                )
            ) {
                continue;
            }

            $processed[$url] = true;

            $batch[] = $url;

            if (
                count($batch) >=
                MAX_CONCURRENT
            ) {
                break;
            }
        }

        if (!$batch) {
            break;
        }

        $responses =
            fetch_batch($batch);

        foreach ($responses as $response) {
            $url = $response['url'];

            if (
                !$response['success']
            ) {
                $results[$url] = [
                    'url' => $url,
                    'status' =>
                        $response['status'],
                    'categories' => [
                        'seo' => 0,
                        'structure' => 0,
                        'mobile' => 0,
                        'content' => 0,
                        'performance' => 0,
                        'social' => 0,
                    ],
                    'issues' => [
                        [
                            'key' =>
                                'http_error',
                            'category' =>
                                'structure',
                            'message' =>
                                'Cette page ne répond pas correctement.'
                        ]
                    ],
                    'links' => [],
                    'meta' => [
                        'isSpa' => false,
                        'title' => '',
                        'description' => '',
                        'canonical' => '',
                        'lang' => '',
                        'robots' => '',
                        'wordCount' => 0,
                        'h1' => 0,
                        'h1Texts' => [],
                        'h2' => 0,
                        'h3' => 0,
                        'images' => 0,
                        'imagesWithoutAlt' => 0,
                        'ogTitle' => false,
                        'ogDescription' => false,
                        'ogImage' => false,
                        'ogUrl' => false,
                        'twitterCard' => false,
                    ],
                    'technical' => [
                        'responseTime' =>
                            $response['responseTime'],
                        'htmlBytes' =>
                            $response['bytes'],
                    ]
                ];

                continue;
            }

            if (
                $response['status'] < 200 ||
                $response['status'] >= 300
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
                    $url,
                    $response['body'],
                    $response['responseTime'],
                    $response['status'],
                    $response['bytes']
                );

            $results[$url] =
                $analysis;

            /*
             * Découverte de nouveaux liens.
             */

            foreach (
                $analysis['links'] as $link
            ) {
                $addToQueue($link);
            }

            if (
                count($results) >= MAX_PAGES
            ) {
                break;
            }
        }
    }

    /*
     * AGRÉGATION
     */

    $pageCount =
        count($results);

    if ($pageCount === 0) {
        respond([
            'success' => false,
            'message' =>
                'Aucune page exploitable n’a pu être analysée.'
        ], 500);
    }

    $categoryTotals = [
        'seo' => 0,
        'structure' => 0,
        'mobile' => 0,
        'content' => 0,
        'performance' => 0,
        'social' => 0,
    ];

    foreach ($results as $result) {
        foreach ($categoryTotals as $key => $value) {
            $categoryTotals[$key] +=
                (int) (
                    $result['categories'][$key]
                    ?? 0
                );
        }
    }

    $categories = [];

    $labels = [
        'seo' => 'SEO',
        'structure' => 'Structure',
        'mobile' => 'Mobile',
        'content' => 'Contenu',
        'performance' => 'Performance technique',
        'social' => 'Partage social',
    ];

    foreach ($categoryTotals as $key => $total) {
        $categories[$key] = [
            'score' => (int) round(
                $total / $pageCount
            ),
            'label' =>
                $labels[$key] ?? $key,
        ];
    }

    /*
     * SCORE GLOBAL
     */

    $overallScore =
        calculate_overall_score(
            $categories
        );

    /*
     * PROBLÈMES
     */

    $issueCounts =
        aggregate_issues(
            $results
        );

    $recommendations =
        build_recommendations(
            $issueCounts
        );

    /*
     * FORCES
     */

    $strengths =
        build_strengths(
            $categories,
            $results
        );

    /*
     * STATISTIQUES
     */

    $spaPages = 0;
    $errorPages = 0;

    $responseTimes = [];
    $htmlBytes = [];

    $titles = [];
    $descriptions = [];
    $h1s = [];

    $internalLinks = 0;

    foreach ($results as $result) {
        if (
            !empty(
                $result['meta']['isSpa']
            )
        ) {
            $spaPages++;
        }

        if (
            ($result['status'] ?? 200) >= 400
        ) {
            $errorPages++;
        }

        $responseTimes[] =
            (float) (
                $result['technical']['responseTime']
                ?? 0
            );

        $htmlBytes[] =
            (int) (
                $result['technical']['htmlBytes']
                ?? 0
            );

        $title =
            trim(
                $result['meta']['title']
                ?? ''
            );

        if ($title !== '') {
            $titles[] =
                mb_strtolower($title);
        }

        $description =
            trim(
                $result['meta']['description']
                ?? ''
            );

        if ($description !== '') {
            $descriptions[] =
                mb_strtolower($description);
        }

        foreach (
            $result['meta']['h1Texts']
            ?? [] as $h1
        ) {
            $h1s[] =
                mb_strtolower(
                    trim($h1)
                );
        }

        $internalLinks +=
            count(
                $result['links'] ?? []
            );
    }

    /*
     * DUPLICATIONS
     */

    $duplicateTitles = 0;

    foreach (
        array_count_values($titles)
        as $count
    ) {
        if ($count > 1) {
            $duplicateTitles +=
                $count - 1;
        }
    }

    $duplicateDescriptions = 0;

    foreach (
        array_count_values($descriptions)
        as $count
    ) {
        if ($count > 1) {
            $duplicateDescriptions +=
                $count - 1;
        }
    }

    $duplicateH1 = 0;

    foreach (
        array_count_values($h1s)
        as $count
    ) {
        if ($count > 1) {
            $duplicateH1 +=
                $count - 1;
        }
    }

    /*
     * TEMPS
     */

    $elapsed =
        round(
            microtime(true) -
            $started,
            1
        );

    $averageResponse =
        $responseTimes
            ? round(
                array_sum(
                    $responseTimes
                ) / count(
                    $responseTimes
                ),
                3
            )
            : 0;

    $averageHtmlBytes =
        $htmlBytes
            ? (int) round(
                array_sum(
                    $htmlBytes
                ) / count(
                    $htmlBytes
                )
            )
            : 0;

    /*
     * PAGES À PRIORITÉ
     */

    $priorityPages = [];

    foreach ($results as $result) {
        $score =
            calculate_overall_score(
                $result['categories']
            );

        $issueCount =
            count(
                $result['issues'] ?? []
            );

        $priorityPages[] = [
            'url' =>
                $result['url'],
            'score' =>
                $score,
            'issues' =>
                $issueCount,
            'status' =>
                $result['status'],
        ];
    }

    usort(
        $priorityPages,
        function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $b['issues'] <=> $a['issues'];
            }

            return $a['score'] <=> $b['score'];
        }
    );

    /*
     * RÉSULTAT
     */

    return [
        'success' => true,

        'score' =>
            $overallScore,

        'pagesAnalyzed' =>
            $pageCount,

        'pagesDiscovered' =>
            count(
                array_unique(
                    array_merge(
                        array_keys($queued),
                        $queue
                    )
                )
            ),

        'categories' =>
            $categories,

        /*
         * IMPORTANT :
         * Audit.tsx attend des strings.
         */

        'recommendations' =>
            $recommendations,

        'strengths' =>
            $strengths,

        'responseTime' =>
            $elapsed,

        'technical' => [
            'version' =>
                '4.0',

            'isSpa' =>
                $spaPages > 0,

            'spaPages' =>
                $spaPages,

            'pages' =>
                $pageCount,

            'errorPages' =>
                $errorPages,

            'averageResponseTime' =>
                $averageResponse,

            'averageHtmlBytes' =>
                $averageHtmlBytes,

            'duplicateTitles' =>
                $duplicateTitles,

            'duplicateDescriptions' =>
                $duplicateDescriptions,

            'duplicateH1' =>
                $duplicateH1,

            'internalLinks' =>
                $internalLinks,

            'sitemapPages' =>
                count($sitemapUrls),

            /*
             * On ne prétend PAS mesurer les Core Web Vitals.
             */

            'performanceNote' =>
                'La performance mesure ici la réponse serveur et le poids HTML. Les Core Web Vitals nécessitent un navigateur réel.',

            'priorityPages' =>
                array_slice(
                    $priorityPages,
                    0,
                    10
                ),
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| ROUTE AUDIT
|--------------------------------------------------------------------------
*/

if ($action === 'analyze') {
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
            run_full_audit($url);

        respond($result);
    } catch (Throwable $e) {
        /*
         * Ne jamais exposer les détails internes
         * de l'erreur au visiteur.
         */

        respond([
            'success' => false,
            'message' =>
                'Une erreur est survenue pendant l’analyse.'
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| ANCIEN FORMULAIRE DE CONTACT
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
    'Nouvelle demande Vitrine+ — ' .
    $company;

$body =
    "Nouvelle demande depuis vitrineplus.fr\n\n" .
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