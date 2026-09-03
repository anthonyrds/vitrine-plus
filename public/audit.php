<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

/*
|--------------------------------------------------------------------------
| OUTILS
|--------------------------------------------------------------------------
*/

function respond(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

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

    $normalized = $scheme . '://' . $host . $path;

    return rtrim($normalized, '/') ?: $scheme . '://' . $host;
}

function normalize_host(string $host): string
{
    $host = strtolower($host);

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return $host;
}

function is_private_or_reserved_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }

    $flags =
        FILTER_FLAG_NO_PRIV_RANGE |
        FILTER_FLAG_NO_RES_RANGE;

    return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
}

function is_safe_host(string $host): bool
{
    $host = strtolower(trim($host));

    if ($host === '') {
        return false;
    }

    if ($host === 'localhost') {
        return false;
    }

    if (
        $host === '127.0.0.1' ||
        $host === '0.0.0.0' ||
        $host === '::1'
    ) {
        return false;
    }

    $ips = [];

    if (function_exists('dns_get_record')) {
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
    }

    if (!$ips) {
        $resolved = @gethostbynamel($host);

        if (is_array($resolved)) {
            $ips = array_merge($ips, $resolved);
        }
    }

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

function same_site(string $url, string $rootHost): bool
{
    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return false;
    }

    $host = normalize_host($parts['host']);

    return $host === normalize_host($rootHost);
}

function absolute_url(string $href, string $baseUrl): ?string
{
    $href = trim($href);

    if ($href === '') {
        return null;
    }

    if (
        str_starts_with($href, '#') ||
        preg_match('#^(mailto:|tel:|javascript:|data:)#i', $href)
    ) {
        return null;
    }

    $href = preg_replace('/#.*$/', '', $href);

    if ($href === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $href)) {
        return normalize_url($href);
    }

    $base = parse_url($baseUrl);

    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }

    $scheme = $base['scheme'];
    $host = $base['host'];

    if (str_starts_with($href, '//')) {
        return normalize_url($scheme . ':' . $href);
    }

    if (str_starts_with($href, '/')) {
        return normalize_url($scheme . '://' . $host . $href);
    }

    $basePath = $base['path'] ?? '/';

    if (!str_ends_with($basePath, '/')) {
        $basePath = dirname($basePath) . '/';
    }

    $combined = $basePath . $href;

    $segments = [];

    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return normalize_url(
        $scheme . '://' . $host . '/' . implode('/', $segments)
    );
}

function is_html_candidate(string $url): bool
{
    $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

    $extensions = [
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
        '.csv',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.ppt',
        '.pptx'
    ];

    foreach ($extensions as $extension) {
        if (str_ends_with($path, $extension)) {
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

function fetch_url(string $url, int $timeout = 8): array
{
    $start = microtime(true);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_MAXFILESIZE => 2500000,
        CURLOPT_USERAGENT => 'VitrinePlus-Audit/1.0',
        CURLOPT_ENCODING => '',
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);

    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    $duration = round(microtime(true) - $start, 3);

    if ($response === false) {
        return [
            'success' => false,
            'status' => $status,
            'body' => '',
            'contentType' => $contentType,
            'effectiveUrl' => $effectiveUrl,
            'time' => $duration,
            'error' => $error,
        ];
    }

    $body = substr($response, $headerSize);

    return [
        'success' => $status >= 200 && $status < 400,
        'status' => $status,
        'body' => $body,
        'contentType' => $contentType,
        'effectiveUrl' => $effectiveUrl,
        'time' => $duration,
        'error' => '',
    ];
}

/*
|--------------------------------------------------------------------------
| HTML ANALYSIS
|--------------------------------------------------------------------------
*/

function analyze_html(string $url, string $html, float $responseTime): array
{
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    @$dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_NOWARNING | LIBXML_NOERROR
    );

    $xpath = new DOMXPath($dom);

    $title = '';
    $description = '';
    $canonical = '';
    $lang = '';

    $titleNode = $xpath->query('//title')->item(0);

    if ($titleNode) {
        $title = trim($titleNode->textContent);
    }

    $descriptionNode = $xpath->query(
        '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content'
    )->item(0);

    if ($descriptionNode) {
        $description = trim($descriptionNode->nodeValue);
    }

    $canonicalNode = $xpath->query(
        '//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]/@href'
    )->item(0);

    if ($canonicalNode) {
        $canonical = trim($canonicalNode->nodeValue);
    }

    $htmlNode = $xpath->query('//html')->item(0);

    if ($htmlNode && $htmlNode->hasAttribute('lang')) {
        $lang = trim($htmlNode->getAttribute('lang'));
    }

    $h1Count = $xpath->query('//h1')->length;
    $h2Count = $xpath->query('//h2')->length;

    $viewportCount = $xpath->query(
        '//meta[contains(translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"viewport")]'
    )->length;

    $images = $xpath->query('//img');

    $imagesTotal = $images->length;
    $imagesWithoutAlt = 0;

    foreach ($images as $image) {
        $alt = trim($image->getAttribute('alt'));

        if ($alt === '') {
            $imagesWithoutAlt++;
        }
    }

    $ogTitle = $xpath->query(
        '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:title"]'
    )->length > 0;

    $ogDescription = $xpath->query(
        '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:description"]'
    )->length > 0;

    $ogImage = $xpath->query(
        '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image"]'
    )->length > 0;

    $links = [];

    foreach ($xpath->query('//a[@href]') as $anchor) {
        $href = trim($anchor->getAttribute('href'));

        if ($href !== '') {
            $links[] = $href;
        }
    }

    $seo = 0;
    $structure = 0;
    $mobile = 0;
    $content = 0;
    $performance = 0;
    $social = 0;

    if ($title !== '') {
        $seo += 25;
    }

    if (mb_strlen($title) >= 30 && mb_strlen($title) <= 65) {
        $seo += 15;
    }

    if ($description !== '') {
        $seo += 20;
    }

    if (mb_strlen($description) >= 80 && mb_strlen($description) <= 170) {
        $seo += 10;
    }

    if ($canonical !== '') {
        $seo += 10;
    }

    if ($lang !== '') {
        $seo += 10;
    }

    if ($h1Count === 1) {
        $structure += 40;
    } elseif ($h1Count > 1) {
        $structure += 20;
    }

    if ($h2Count > 0) {
        $structure += 25;
    }

    if ($links) {
        $structure += 20;
    }

    if ($viewportCount > 0) {
        $mobile += 60;
    }

    if ($imagesTotal === 0) {
        $mobile += 20;
    } elseif ($imagesWithoutAlt / max(1, $imagesTotal) < 0.25) {
        $mobile += 20;
    }

    if ($title !== '') {
        $content += 25;
    }

    if ($description !== '') {
        $content += 20;
    }

    if ($h1Count > 0) {
        $content += 25;
    }

    if ($h2Count >= 2) {
        $content += 15;
    }

    if ($imagesTotal > 0) {
        $content += 15;
    }

    if ($responseTime <= 0.5) {
        $performance += 100;
    } elseif ($responseTime <= 1) {
        $performance += 85;
    } elseif ($responseTime <= 1.5) {
        $performance += 70;
    } elseif ($responseTime <= 2.5) {
        $performance += 55;
    } elseif ($responseTime <= 4) {
        $performance += 35;
    } else {
        $performance += 15;
    }

    if ($ogTitle) {
        $social += 30;
    }

    if ($ogDescription) {
        $social += 30;
    }

    if ($ogImage) {
        $social += 40;
    }

    $categories = [
        'seo' => min(100, $seo),
        'structure' => min(100, $structure),
        'mobile' => min(100, $mobile),
        'content' => min(100, $content),
        'performance' => min(100, $performance),
        'social' => min(100, $social),
    ];

    $issues = [];

    if ($title === '') {
        $issues[] = 'Ajouter une balise title claire et optimisée sur cette page.';
    }

    if ($description === '') {
        $issues[] = 'Ajouter une meta description pertinente.';
    }

    if ($h1Count === 0) {
        $issues[] = 'Ajouter un titre H1 clairement identifiable.';
    }

    if ($h1Count > 1) {
        $issues[] = 'Réduire la page à un seul H1 principal.';
    }

    if ($viewportCount === 0) {
        $issues[] = 'Ajouter la configuration viewport pour une expérience mobile correcte.';
    }

    if ($imagesWithoutAlt > 0) {
        $issues[] = 'Renseigner les textes alternatifs des images.';
    }

    if ($canonical === '') {
        $issues[] = 'Ajouter une URL canonique.';
    }

    if (!$ogTitle || !$ogDescription || !$ogImage) {
        $issues[] = 'Compléter les balises Open Graph pour améliorer les partages sur les réseaux sociaux.';
    }

    if ($responseTime > 2) {
        $issues[] = 'Réduire le temps de réponse du serveur et optimiser le chargement.';
    }

    return [
        'url' => $url,
        'categories' => $categories,
        'issues' => $issues,
        'links' => $links,
    ];
}

/*
|--------------------------------------------------------------------------
| SITEMAP
|--------------------------------------------------------------------------
*/

function get_sitemap_urls(string $origin, string $rootHost): array
{
    $sitemapUrl = rtrim($origin, '/') . '/sitemap.xml';

    $result = fetch_url($sitemapUrl, 5);

    if (
        !$result['success'] ||
        stripos($result['contentType'], 'xml') === false
    ) {
        return [];
    }

    preg_match_all(
        '#<loc>\s*(.*?)\s*</loc>#is',
        $result['body'],
        $matches
    );

    $urls = [];

    foreach ($matches[1] ?? [] as $url) {
        $url = normalize_url(html_entity_decode(trim($url)));

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

    return array_values(array_unique($urls));
}

/*
|--------------------------------------------------------------------------
| ANALYSE COMPLETE
|--------------------------------------------------------------------------
*/

function run_full_audit(string $inputUrl): array
{
    $normalized = normalize_url($inputUrl);

    if (!$normalized) {
        respond([
            'success' => false,
            'message' => 'L’adresse du site est invalide.'
        ], 422);
    }

    $parts = parse_url($normalized);

    if (!$parts || empty($parts['host'])) {
        respond([
            'success' => false,
            'message' => 'Impossible de déterminer le domaine.'
        ], 422);
    }

    $rootHost = normalize_host($parts['host']);

    if (!is_safe_host($rootHost)) {
        respond([
            'success' => false,
            'message' => 'Ce domaine ne peut pas être analysé.'
        ], 422);
    }

    $origin =
        ($parts['scheme'] ?? 'https') .
        '://' .
        $parts['host'];

    $start = microtime(true);

    /*
    |--------------------------------------------------------------------------
    | PAGE DE DÉPART
    |--------------------------------------------------------------------------
    */

    $homepage = fetch_url($normalized, 8);

    if (!$homepage['success']) {
        respond([
            'success' => false,
            'message' => 'Le site n’a pas pu être chargé. Vérifiez l’adresse et réessayez.'
        ], 502);
    }

    $contentType = strtolower($homepage['contentType']);

    if (
        $contentType !== '' &&
        stripos($contentType, 'text/html') === false
    ) {
        respond([
            'success' => false,
            'message' => 'Cette adresse ne semble pas pointer vers une page HTML.'
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | FILE D'URLS
    |--------------------------------------------------------------------------
    */

    $queue = [];
    $visited = [];

    $homepageUrl = normalize_url($normalized);

    if ($homepageUrl) {
        $queue[] = $homepageUrl;
    }

    /*
    |--------------------------------------------------------------------------
    | SITEMAP
    |--------------------------------------------------------------------------
    */

    $sitemapUrls = get_sitemap_urls($origin, $rootHost);

    foreach ($sitemapUrls as $url) {
        if (!isset($visited[$url])) {
            $queue[] = $url;
        }
    }

    $queue = array_values(array_unique($queue));

    /*
    |--------------------------------------------------------------------------
    | ANALYSE DE LA HOME
    |--------------------------------------------------------------------------
    */

    $results = [];

    $homepageAnalysis = analyze_html(
        $homepageUrl,
        $homepage['body'],
        $homepage['time']
    );

    $results[] = $homepageAnalysis;
    $visited[$homepageUrl] = true;

    /*
    |--------------------------------------------------------------------------
    | DÉCOUVERTE DES LIENS INTERNES
    |--------------------------------------------------------------------------
    */

    foreach ($homepageAnalysis['links'] as $href) {
        $absolute = absolute_url($href, $homepageUrl);

        if (!$absolute) {
            continue;
        }

        if (!same_site($absolute, $rootHost)) {
            continue;
        }

        if (!is_html_candidate($absolute)) {
            continue;
        }

        if (!isset($visited[$absolute])) {
            $queue[] = $absolute;
        }
    }

    $queue = array_values(array_unique($queue));

    /*
    |--------------------------------------------------------------------------
    | LIMITATION RAISONNABLE
    |--------------------------------------------------------------------------
    |
    | On vise l'intégralité du site, mais on protège le serveur contre
    | les sites gigantesques ou générant des milliers d'URLs.
    |
    */

    $maxPages = 50;

    /*
    |--------------------------------------------------------------------------
    | CRAWL PAR LOTS CONCURRENTS
    |--------------------------------------------------------------------------
    */

    while (
        count($results) < $maxPages &&
        !empty($queue)
    ) {
        $batch = [];

        while (
            count($batch) < 8 &&
            !empty($queue) &&
            count($results) + count($batch) < $maxPages
        ) {
            $candidate = array_shift($queue);

            if (!$candidate) {
                continue;
            }

            if (isset($visited[$candidate])) {
                continue;
            }

            if (!same_site($candidate, $rootHost)) {
                continue;
            }

            if (!is_html_candidate($candidate)) {
                continue;
            }

            $visited[$candidate] = true;
            $batch[] = $candidate;
        }

        if (!$batch) {
            break;
        }

        $multi = curl_multi_init();
        $handles = [];

        foreach ($batch as $url) {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_MAXFILESIZE => 2500000,
                CURLOPT_USERAGENT => 'VitrinePlus-Audit/1.0',
                CURLOPT_ENCODING => '',
                CURLOPT_HEADER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            curl_multi_add_handle($multi, $ch);

            $handles[(int) $ch] = [
                'handle' => $ch,
                'url' => $url,
                'start' => microtime(true),
            ];
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running) {
                curl_multi_select($multi, 0.5);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $data) {
            $ch = $data['handle'];
            $url = $data['url'];

            $raw = curl_multi_getcontent($ch);

            $statusCode = (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            $headerSize = (int) curl_getinfo(
                $ch,
                CURLINFO_HEADER_SIZE
            );

            $contentType = (string) curl_getinfo(
                $ch,
                CURLINFO_CONTENT_TYPE
            );

            $duration = round(
                microtime(true) - $data['start'],
                3
            );

            if (
                $raw !== false &&
                $statusCode >= 200 &&
                $statusCode < 300 &&
                (
                    $contentType === '' ||
                    stripos($contentType, 'text/html') !== false
                )
            ) {
                $body = substr($raw, $headerSize);

                $analysis = analyze_html(
                    $url,
                    $body,
                    $duration
                );

                $results[] = $analysis;

                foreach ($analysis['links'] as $href) {
                    $absolute = absolute_url(
                        $href,
                        $url
                    );

                    if (!$absolute) {
                        continue;
                    }

                    if (!same_site($absolute, $rootHost)) {
                        continue;
                    }

                    if (!is_html_candidate($absolute)) {
                        continue;
                    }

                    if (!isset($visited[$absolute])) {
                        $queue[] = $absolute;
                    }
                }
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        $queue = array_values(array_unique($queue));

        /*
        |--------------------------------------------------------------------------
        | ÉVITER UN CRAWL TROP LONG
        |--------------------------------------------------------------------------
        */

        if (
            microtime(true) - $start > 22
        ) {
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AGRÉGATION
    |--------------------------------------------------------------------------
    */

    $totals = [
        'seo' => 0,
        'structure' => 0,
        'mobile' => 0,
        'content' => 0,
        'performance' => 0,
        'social' => 0,
    ];

    $issueCounts = [];
    $strengthCounts = [];

    foreach ($results as $result) {
        foreach ($result['categories'] as $key => $score) {
            $totals[$key] += $score;
        }

        foreach ($result['issues'] as $issue) {
            if (!isset($issueCounts[$issue])) {
                $issueCounts[$issue] = 0;
            }

            $issueCounts[$issue]++;
        }

        foreach ($result['categories'] as $key => $score) {
            if ($score >= 80) {
                $strength = match ($key) {
                    'seo' => 'Les fondamentaux SEO sont correctement présents sur une grande partie du site.',
                    'structure' => 'La structure HTML et les titres sont globalement bien organisés.',
                    'mobile' => 'Le site présente de bons signaux de compatibilité mobile.',
                    'content' => 'Les principales pages disposent de signaux de contenu correctement structurés.',
                    'performance' => 'Les temps de réponse observés sont globalement satisfaisants.',
                    'social' => 'Les métadonnées sociales sont correctement configurées.',
                    default => null,
                };

                if ($strength) {
                    $strengthCounts[$strength] =
                        ($strengthCounts[$strength] ?? 0) + 1;
                }
            }
        }
    }

    $pageCount = max(1, count($results));

    $categories = [];

    foreach ($totals as $key => $total) {
        $categories[$key] = [
            'score' => (int) round($total / $pageCount),
            'label' => $key,
        ];
    }

    $overallScore = (int) round(
        array_sum(
            array_column($categories, 'score')
        ) / count($categories)
    );

    arsort($issueCounts);
    arsort($strengthCounts);

    $recommendations = array_slice(
        array_keys($issueCounts),
        0,
        3
    );

    if (!$recommendations) {
        $recommendations = [
            'Continuer à optimiser les pages les plus importantes du site.',
            'Maintenir une structure SEO cohérente sur les nouvelles pages.',
            'Mesurer régulièrement les performances et la conversion.'
        ];
    }

    $strengths = array_slice(
        array_keys($strengthCounts),
        0,
        5
    );

    if (!$strengths) {
        $strengths = [
            'Le domaine est accessible et a pu être analysé.',
            'Plusieurs pages internes ont été découvertes.',
            'Les principaux signaux techniques ont pu être contrôlés.'
        ];
    }

    $elapsed = round(
        microtime(true) - $start,
        1
    );

    return [
        'success' => true,
        'score' => max(0, min(100, $overallScore)),
        'pagesAnalyzed' => count($results),
        'pagesDiscovered' => max(
            count($results),
            count(array_unique(array_merge(
                array_keys($visited),
                $queue
            )))
        ),
        'categories' => $categories,
        'strengths' => $strengths,
        'recommendations' => $recommendations,
        'responseTime' => $elapsed,
    ];
}

/*
|--------------------------------------------------------------------------
| ROUTE
|--------------------------------------------------------------------------
*/

if ($action === 'analyze') {
    $url = trim($_POST['url'] ?? '');

    if ($url === '') {
        respond([
            'success' => false,
            'message' => 'Veuillez renseigner l’adresse de votre site.'
        ], 422);
    }

    try {
        $result = run_full_audit($url);
        respond($result);
    } catch (Throwable $e) {
        respond([
            'success' => false,
            'message' => 'Une erreur est survenue pendant l’analyse.'
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| ANCIEN FORMULAIRE — COMPATIBILITÉ
|--------------------------------------------------------------------------
*/

$to = 'vitrineplus@hotmail.com';

$name = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$website = trim($_POST['website'] ?? '');
$goal = trim($_POST['goal'] ?? '');
$budget = trim($_POST['budget'] ?? '');
$message = trim($_POST['message'] ?? '');

if (
    $name === '' ||
    $company === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $message === ''
) {
    respond([
        'success' => false,
        'message' => 'Informations obligatoires manquantes.'
    ], 422);
}

$subject = 'Nouvel audit digital Vitrine+ — ' . $company;

$body =
    "Nouvelle demande d'audit depuis vitrineplus.fr\n\n" .
    "Nom : $name\n" .
    "Entreprise : $company\n" .
    "Email : $email\n" .
    "Téléphone : $phone\n" .
    "Site : $website\n" .
    "Objectif : $goal\n" .
    "Budget : $budget\n\n" .
    "Projet :\n$message\n";

$headers =
    "From: Vitrine+ <no-reply@vitrineplus.fr>\r\n" .
    "Reply-To: " . $email . "\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail(
    $to,
    $subject,
    $body,
    $headers
);

if (!$sent) {
    respond([
        'success' => false,
        'message' => 'Envoi impossible.'
    ], 500);
}

respond([
    'success' => true
]);