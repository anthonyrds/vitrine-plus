<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const MAX_PAGES = 50;
const MAX_BODY_BYTES = 2000000;
const REQUEST_TIMEOUT = 8;
const CONNECT_TIMEOUT = 4;
const MAX_REDIRECTS = 5;

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

function textLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function textSubstr(
    string $value,
    int $start,
    int $length
): string {
    return function_exists('mb_substr')
        ? mb_substr(
            $value,
            $start,
            $length,
            'UTF-8'
        )
        : substr(
            $value,
            $start,
            $length
        );
}

function clean(
    string $value,
    int $max = 1000
): string {
    $value = trim($value);

    $value =
        preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        ) ?? '';

    return textSubstr(
        $value,
        0,
        $max
    );
}

function normalizeUrl(
    string $url
): ?string {
    $url = trim($url);

    if ($url === '') {
        return null;
    }

    if (
        !preg_match(
            '#^https?://#i',
            $url
        )
    ) {
        $url =
            'https://' .
            $url;
    }

    if (
        !filter_var(
            $url,
            FILTER_VALIDATE_URL
        )
    ) {
        return null;
    }

    $parts =
        parse_url($url);

    if (
        !$parts ||
        empty($parts['host'])
    ) {
        return null;
    }

    $scheme =
        strtolower(
            (string) (
                $parts['scheme'] ?? ''
            )
        );

    if (
        $scheme !== 'http' &&
        $scheme !== 'https'
    ) {
        return null;
    }

    $host =
        strtolower(
            rtrim(
                (string) $parts['host'],
                '.'
            )
        );

    $port =
        isset($parts['port'])
            ? ':' .
                (int) $parts['port']
            : '';

    if (
        $port !== '' &&
        !in_array(
            (int) $parts['port'],
            [80, 443],
            true
        )
    ) {
        return null;
    }

    $path =
        (string) (
            $parts['path'] ?? '/'
        );

    if ($path === '') {
        $path = '/';
    }

    $path =
        preg_replace(
            '#/+#',
            '/',
            $path
        ) ?? $path;

    return
        $scheme .
        '://' .
        $host .
        $port .
        $path .
        (
            isset($parts['query'])
                ? '?' .
                    $parts['query']
                : ''
        );
}

function hostIsSafe(
    string $host
): bool {
    $host =
        strtolower(
            rtrim(
                trim($host),
                '.'
            )
        );

    if (
        $host === '' ||
        $host === 'localhost' ||
        $host === 'localhost.localdomain'
    ) {
        return false;
    }

    if (
        filter_var(
            $host,
            FILTER_VALIDATE_IP
        )
    ) {
        return
            filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
            ) !== false;
    }

    $ips =
        @gethostbynamel(
            $host
        ) ?: [];

    if (
        function_exists(
            'dns_get_record'
        )
    ) {
        $dns =
            @dns_get_record(
                $host,
                DNS_AAAA
            );

        foreach (
            $dns ?: []
            as $record
        ) {
            if (
                !empty(
                    $record['ipv6']
                )
            ) {
                $ips[] =
                    $record['ipv6'];
            }
        }
    }

    if (!$ips) {
        return false;
    }

    foreach (
        array_unique($ips)
        as $ip
    ) {
        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
            ) === false
        ) {
            return false;
        }
    }

    return true;
}

function sameHost(
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

    $host =
        strtolower(
            rtrim(
                (string) $parts['host'],
                '.'
            )
        );

    $root =
        strtolower(
            rtrim(
                $rootHost,
                '.'
            )
        );

    if (
        $host === $root
    ) {
        return true;
    }

    return
        preg_replace(
            '/^www\./i',
            '',
            $host
        ) ===
        preg_replace(
            '/^www\./i',
            '',
            $root
        );
}

function canonicalUrl(
    string $url
): string {
    $parts =
        parse_url($url);

    if (!$parts) {
        return $url;
    }

    $scheme =
        strtolower(
            (string) (
                $parts['scheme'] ??
                'https'
            )
        );

    $host =
        strtolower(
            (string) (
                $parts['host'] ??
                ''
            )
        );

    $port =
        isset($parts['port'])
            ? ':' .
                (int) $parts['port']
            : '';

    $path =
        (string) (
            $parts['path'] ??
            '/'
        );

    if ($path === '') {
        $path = '/';
    }

    $path =
        preg_replace(
            '#/+#',
            '/',
            $path
        ) ?? $path;

    if (
        strlen($path) > 1
    ) {
        $path =
            rtrim(
                $path,
                '/'
            );
    }

    return
        $scheme .
        '://' .
        $host .
        $port .
        $path;
}

function resolveUrl(
    string $base,
    string $relative
): ?string {
    $relative =
        trim(
            html_entity_decode(
                $relative,
                ENT_QUOTES |
                ENT_HTML5,
                'UTF-8'
            )
        );

    if (
        $relative === '' ||
        preg_match(
            '#^(mailto:|tel:|javascript:|data:)#i',
            $relative
        )
    ) {
        return null;
    }

    if (
        preg_match(
            '#^https?://#i',
            $relative
        )
    ) {
        return normalizeUrl(
            $relative
        );
    }

    $baseParts =
        parse_url($base);

    if (
        !$baseParts ||
        empty($baseParts['scheme']) ||
        empty($baseParts['host'])
    ) {
        return null;
    }

    $origin =
        $baseParts['scheme'] .
        '://' .
        $baseParts['host'] .
        (
            isset($baseParts['port'])
                ? ':' .
                    $baseParts['port']
                : ''
        );

    if (
        str_starts_with(
            $relative,
            '//'
        )
    ) {
        return normalizeUrl(
            $baseParts['scheme'] .
            ':' .
            $relative
        );
    }

    if (
        str_starts_with(
            $relative,
            '/'
        )
    ) {
        return normalizeUrl(
            $origin .
            $relative
        );
    }

    $basePath =
        (string) (
            $baseParts['path'] ??
            '/'
        );

    $dir =
        rtrim(
            str_replace(
                '\\',
                '/',
                dirname($basePath)
            ),
            '/'
        );

    $path =
        (
            $dir === ''
                ? ''
                : $dir
        ) .
        '/' .
        $relative;

    $segments = [];

    foreach (
        explode(
            '/',
            $path
        ) as $segment
    ) {
        if (
            $segment === '' ||
            $segment === '.'
        ) {
            continue;
        }

        if (
            $segment === '..'
        ) {
            array_pop(
                $segments
            );

            continue;
        }

        $segments[] =
            $segment;
    }

    return normalizeUrl(
        $origin .
        '/' .
        implode(
            '/',
            $segments
        )
    );
}

function fetchUrl(
    string $url,
    int $redirects = 0
): array {
    $started =
        microtime(true);

    $ch =
        curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                false,

            CURLOPT_CONNECTTIMEOUT =>
                CONNECT_TIMEOUT,

            CURLOPT_TIMEOUT =>
                REQUEST_TIMEOUT,

            CURLOPT_USERAGENT =>
                'VitrinePlusAudit/2.0 (+https://vitrineplus.fr/audit)',

            CURLOPT_ENCODING =>
                '',

            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            ],

            CURLOPT_MAXFILESIZE =>
                MAX_BODY_BYTES,

            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_SSL_VERIFYHOST =>
                2,

            CURLOPT_HEADER =>
                true,
        ]
    );

    $raw =
        curl_exec($ch);

    $error =
        curl_error($ch);

    $status =
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

    $headerSize =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HEADER_SIZE
        );

    curl_close($ch);

    $elapsed =
        $totalTime > 0
            ? $totalTime
            : microtime(true) -
                $started;

    if (!is_string($raw)) {
        $raw = '';
    }

    $headers =
        $headerSize > 0
            ? substr(
                $raw,
                0,
                $headerSize
            )
            : '';

    $body =
        $headerSize > 0
            ? substr(
                $raw,
                $headerSize
            )
            : $raw;

    if ($error !== '') {
        return [
            'success' =>
                false,

            'status' =>
                $status,

            'contentType' =>
                $contentType,

            'body' =>
                '',

            'bytes' =>
                0,

            'time' =>
                $elapsed,

            'error' =>
                $error,

            'finalUrl' =>
                $url
        ];
    }

    if (
        $status >= 300 &&
        $status < 400
    ) {
        if (
            $redirects >=
            MAX_REDIRECTS
        ) {
            return [
                'success' => false,
                'status' => $status,
                'contentType' => $contentType,
                'body' => '',
                'bytes' => 0,
                'time' => $elapsed,
                'error' =>
                    'Trop de redirections.',
                'finalUrl' => $url
            ];
        }

        if (
            !preg_match(
                '/^Location:\s*(.+)$/im',
                $headers,
                $match
            )
        ) {
            return [
                'success' => false,
                'status' => $status,
                'contentType' => $contentType,
                'body' => '',
                'bytes' => 0,
                'time' => $elapsed,
                'error' =>
                    'Redirection sans destination.',
                'finalUrl' => $url
            ];
        }

        $nextUrl =
            resolveUrl(
                $url,
                trim($match[1])
            );

        if (!$nextUrl) {
            return [
                'success' => false,
                'status' => $status,
                'contentType' => $contentType,
                'body' => '',
                'bytes' => 0,
                'time' => $elapsed,
                'error' =>
                    'Redirection invalide.',
                'finalUrl' => $url
            ];
        }

        $nextParts =
            parse_url($nextUrl);

        $nextHost =
            is_array($nextParts)
                ? (string) (
                    $nextParts['host'] ??
                    ''
                )
                : '';

        if (
            $nextHost === '' ||
            !hostIsSafe($nextHost)
        ) {
            return [
                'success' => false,
                'status' => $status,
                'contentType' => $contentType,
                'body' => '',
                'bytes' => 0,
                'time' => $elapsed,
                'error' =>
                    'Redirection vers un domaine non autorisé.',
                'finalUrl' => $url
            ];
        }

        return fetchUrl(
            $nextUrl,
            $redirects + 1
        );
    }

    if (
        strlen($body) >
        MAX_BODY_BYTES
    ) {
        $body =
            substr(
                $body,
                0,
                MAX_BODY_BYTES
            );
    }

    return [
        'success' =>
            $status >= 200 &&
            $status < 300,

        'status' =>
            $status,

        'contentType' =>
            $contentType,

        'body' =>
            $body,

        'bytes' =>
            strlen($body),

        'time' =>
            $elapsed,

        'error' =>
            $status >= 400
                ? 'HTTP ' .
                    $status
                : '',

        'finalUrl' =>
            $url
    ];
}

function firstMatch(
    string $html,
    string $pattern
): string {
    if (
        preg_match(
            $pattern,
            $html,
            $match
        )
    ) {
        return clean(
            html_entity_decode(
                (string) (
                    $match[1] ?? ''
                ),
                ENT_QUOTES |
                ENT_HTML5,
                'UTF-8'
            ),
            2000
        );
    }

    return '';
}

function countTag(
    string $html,
    string $tag
): int {
    return
        preg_match_all(
            '/<' .
            preg_quote(
                $tag,
                '/'
            ) .
            '\b[^>]*>/i',
            $html,
            $matches
        ) ?: 0;
}

function metaContent(
    string $html,
    string $attribute,
    string $value
): string {
    $pattern =
        '/<meta\b' .
        '(?=[^>]*\b' .
        preg_quote(
            $attribute,
            '/'
        ) .
        '\s*=\s*["\']' .
        preg_quote(
            $value,
            '/'
        ) .
        '["\'])' .
        '(?=[^>]*\bcontent\s*=\s*["\']([^"\']*)["\'])' .
        '[^>]*>/i';

    return firstMatch(
        $html,
        $pattern
    );
}

function hasMeta(
    string $html,
    string $attribute,
    string $value
): bool {
    return preg_match(
        '/<meta\b[^>]*\b' .
        preg_quote(
            $attribute,
            '/'
        ) .
        '\s*=\s*["\']' .
        preg_quote(
            $value,
            '/'
        ) .
        '["\'][^>]*>/i',
        $html
    ) === 1;
}

function extractText(
    string $html
): string {
    $clean =
        preg_replace(
            '/<(script|style|noscript|svg)\b[^>]*>.*?<\/\1>/is',
            ' ',
            $html
        ) ?? $html;

    $clean =
        strip_tags($clean);

    $clean =
        html_entity_decode(
            $clean,
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

    return trim(
        preg_replace(
            '/\s+/u',
            ' ',
            $clean
        ) ?? ''
    );
}

function extractLinks(
    string $html,
    string $currentUrl,
    string $rootHost
): array {
    preg_match_all(
        '/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>/i',
        $html,
        $matches
    );

    $links = [];

    foreach (
        $matches[1] ?? []
        as $href
    ) {
        $absolute =
            resolveUrl(
                $currentUrl,
                $href
            );

        if (
            !$absolute ||
            !sameHost(
                $absolute,
                $rootHost
            )
        ) {
            continue;
        }

        $normalized =
            canonicalUrl(
                $absolute
            );

        $links[$normalized] =
            $normalized;
    }

    return array_values(
        $links
    );
}

function extractSitemapUrls(
    string $rootUrl,
    string $rootHost
): array {
    $parts =
        parse_url($rootUrl);

    if (
        !$parts ||
        empty($parts['scheme']) ||
        empty($parts['host'])
    ) {
        return [];
    }

    $origin =
        $parts['scheme'] .
        '://' .
        $parts['host'];

    $candidates = [
        $origin . '/sitemap.xml',
        $origin . '/sitemap_index.xml',
        $origin . '/wp-sitemap.xml'
    ];

    $urls = [];

    foreach (
        $candidates as $candidate
    ) {
        $fetch =
            fetchUrl($candidate);

        if (
            !$fetch['success'] ||
            (
                stripos(
                    $fetch['contentType'],
                    'xml'
                ) === false &&
                stripos(
                    $fetch['contentType'],
                    'text'
                ) === false
            )
        ) {
            continue;
        }

        preg_match_all(
            '/<loc>\s*([^<]+?)\s*<\/loc>/i',
            $fetch['body'],
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $loc
        ) {
            $url =
                normalizeUrl(
                    trim(
                        html_entity_decode(
                            $loc,
                            ENT_QUOTES |
                            ENT_HTML5,
                            'UTF-8'
                        )
                    )
                );

            if (
                $url &&
                sameHost(
                    $url,
                    $rootHost
                )
            ) {
                $urls[
                    canonicalUrl($url)
                ] = $url;
            }

            if (
                count($urls) >=
                MAX_PAGES
            ) {
                break 2;
            }
        }
    }

    return array_values(
        $urls
    );
}

function analyzePage(
    string $url,
    array $fetch,
    string $rootHost
): array {
    $html = (string) $fetch['body'];

    /*
    |--------------------------------------------------------------------------
    | EXTRACTION DU CONTENU
    |--------------------------------------------------------------------------
    */

    $text = extractText($html);

    $title = firstMatch(
        $html,
        '/<title\b[^>]*>(.*?)<\/title>/is'
    );

    $description = metaContent(
        $html,
        'name',
        'description'
    );

    $lang = firstMatch(
        $html,
        '/<html\b[^>]*\blang\s*=\s*["\']([^"\']+)["\']/i'
    );

    $canonical = firstMatch(
        $html,
        '/<link\b[^>]*\brel\s*=\s*["\']canonical["\'][^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i'
    );

    $h1 = countTag(
        $html,
        'h1'
    );

    $h2 = countTag(
        $html,
        'h2'
    );

    $h3 = countTag(
        $html,
        'h3'
    );

    $viewport = hasMeta(
        $html,
        'name',
        'viewport'
    );

    $ogTitle = metaContent(
        $html,
        'property',
        'og:title'
    );

    $ogDescription = metaContent(
        $html,
        'property',
        'og:description'
    );

    $ogImage = metaContent(
        $html,
        'property',
        'og:image'
    );

    $wordCount = str_word_count(
        $text,
        0,
        'ÀÁÂÃÄÅàáâãäåÆæÇçÈÉÊËèéêëÌÍÎÏìíîïÑñÒÓÔÕÖØòóôõöøÙÚÛÜùúûüÝŸýÿABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
    );

    /*
    |--------------------------------------------------------------------------
    | IMAGES
    |--------------------------------------------------------------------------
    */

    preg_match_all(
        '/<img\b[^>]*>/i',
        $html,
        $imageMatches
    );

    $imageTotal = count(
        $imageMatches[0] ?? []
    );

    $imagesWithAlt = 0;

    foreach (
        $imageMatches[0] ?? []
        as $image
    ) {
        if (
            preg_match(
                '/\balt\s*=\s*["\'][^"\']*["\']/i',
                $image
            )
        ) {
            $imagesWithAlt++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DÉTECTION HTML
    |--------------------------------------------------------------------------
    */

    $hasCta = preg_match(
        '/(contactez|demandez|devis|rendez[- ]vous|réservation|appel|commencer|obtenir|découvrir|parler|audit gratuit|essai gratuit|prendre contact|en savoir plus|voir les services|nous contacter)/iu',
        $html
    ) === 1;

    $hasContact = preg_match(
        '/(contact|mailto:|tel:|\+33|0[1-9](?:[\s.-]?\d{2}){4})/iu',
        $html
    ) === 1;

    $hasSocial = preg_match(
        '/(facebook\.com|instagram\.com|linkedin\.com|x\.com|twitter\.com|youtube\.com|tiktok\.com)/i',
        $html
    ) === 1;

    $forms = countTag(
        $html,
        'form'
    );

    $buttons = countTag(
        $html,
        'button'
    );

    $links = extractLinks(
        $html,
        $url,
        $rootHost
    );

    /*
    |--------------------------------------------------------------------------
    | DÉTECTION SPA
    |--------------------------------------------------------------------------
    */

    $spa =
        (
            $h1 === 0 &&
            $wordCount < 150 &&
            countTag($html, 'script') > 0
        )
        ||
        preg_match(
            '/id=["\'](?:root|app|__next|__nuxt)["\']/i',
            $html
        ) === 1;

    /*
    |--------------------------------------------------------------------------
    | ANALYSE DES FICHIERS JAVASCRIPT POUR LES SPA
    |
    | Un site React/Vite peut contenir ses CTA, formulaires, tarifs et
    | parcours de conversion uniquement dans les bundles JS.
    |--------------------------------------------------------------------------
    */

    $renderedSource = '';

    preg_match_all(
        '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
        $html,
        $scriptMatches
    );

    $scriptSources = $scriptMatches[1] ?? [];

    $scriptCount = countTag(
        $html,
        'script'
    );

    $scriptBytesRead = 0;
    $maxScriptBytes = 1200000;

    foreach (
        $scriptSources as $scriptSource
    ) {
        if (
            $scriptBytesRead >= $maxScriptBytes
        ) {
            break;
        }

        $scriptUrl = resolveUrl(
            $url,
            $scriptSource
        );

        if (
            !$scriptUrl ||
            !sameHost(
                $scriptUrl,
                $rootHost
            )
        ) {
            continue;
        }

        $scriptFetch = fetchUrl(
            $scriptUrl
        );

        if (
            !$scriptFetch['success']
        ) {
            continue;
        }

        $contentType = strtolower(
            (string) (
                $scriptFetch['contentType'] ?? ''
            )
        );

        if (
            $contentType !== '' &&
            strpos(
                $contentType,
                'javascript'
            ) === false &&
            strpos(
                $contentType,
                'text/'
            ) === false
        ) {
            continue;
        }

        $scriptBody = (string) (
            $scriptFetch['body'] ?? ''
        );

        if (
            $scriptBody === ''
        ) {
            continue;
        }

        $remaining =
            $maxScriptBytes -
            $scriptBytesRead;

        if (
            strlen($scriptBody) > $remaining
        ) {
            $scriptBody = substr(
                $scriptBody,
                0,
                $remaining
            );
        }

        $renderedSource .= "\n" . $scriptBody;

        $scriptBytesRead += strlen(
            $scriptBody
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SOURCE ANALYSIS
    |--------------------------------------------------------------------------
    */

    $analysisSource =
        $html .
        "\n" .
        $renderedSource;

    /*
    |--------------------------------------------------------------------------
    | CTA / CONTACT / CONVERSION SIGNALS
    |--------------------------------------------------------------------------
    */

    $hasCta =
        preg_match(
            '/(contactez|demandez|devis|rendez[- ]vous|réservation|appel|commencer|obtenir|découvrir|parler|audit gratuit|essai gratuit|prendre contact|en savoir plus|voir les services|nous contacter|commencer mon|obtenir mon|recevoir mon|prendre rendez[- ]vous)/iu',
            $analysisSource
        ) === 1;

    $hasContact =
        preg_match(
            '/(contact|mailto:|tel:|\+33|0[1-9](?:[\s.-]?\d{2}){4}|\/contact\b|\/rendez[- ]vous\b)/iu',
            $analysisSource
        ) === 1;

    $hasTrust =
        preg_match(
            '/(témoignage|témoignages|avis|client|clients|réalisations|portfolio|référence|références|projet|projets|ils nous font confiance|confiance|satisfaction|garantie|expert|expérience|années)/iu',
            $analysisSource
        ) === 1;

    $hasOffer =
        preg_match(
            '/(prix|tarif|tarifs|à partir de|offre|offres|formule|formules|790|1990|3990|49|149|299|€|euros)/iu',
            $analysisSource
        ) === 1;

    $hasForm =
        $forms > 0 ||
        preg_match(
            '/(type=["\']email["\']|type=["\']tel["\']|type=["\']text["\']|placeholder=|name=["\']email["\']|name=["\']phone["\']|name=["\']telephone["\'])/i',
            $renderedSource
        ) === 1;

    $hasNavigationConversion =
        preg_match(
            '/(\/contact\b|\/rendez[- ]vous\b|\/audit\b|\/devis\b|\/services\b)/i',
            $analysisSource
        ) === 1;

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    */

    $seo = 0;

    if (
        $title !== ''
    ) {
        $seo +=
            textLength($title) >= 20 &&
            textLength($title) <= 65
                ? 25
                : 15;
    }

    if (
        $description !== ''
    ) {
        $seo +=
            textLength($description) >= 70 &&
            textLength($description) <= 170
                ? 25
                : 15;
    }

    if ($h1 === 1) {
        $seo += 20;
    } elseif ($h1 > 0) {
        $seo += 10;
    } elseif ($spa) {
        /*
        | Une SPA peut avoir son H1 après rendu JavaScript.
        */
        $seo += 10;
    }

    if ($canonical !== '') {
        $seo += 10;
    }

    if ($lang !== '') {
        $seo += 10;
    }

    if ($ogTitle !== '') {
        $seo += 5;
    }

    if ($ogDescription !== '') {
        $seo += 5;
    }

    /*
    |--------------------------------------------------------------------------
    | STRUCTURE
    |--------------------------------------------------------------------------
    */

    $structure = 0;

    $structure +=
        $h1 === 1
            ? 35
            : (
                $h1 > 0
                    ? 20
                    : 0
            );

    $structure +=
        $h2 > 0
            ? 25
            : 0;

    $structure +=
        $h3 > 0
            ? 10
            : 0;

    $structure +=
        preg_match(
            '/<nav\b/i',
            $html
        )
            ? 15
            : 0;

    $structure +=
        preg_match(
            '/<main\b/i',
            $html
        )
            ? 15
            : 0;

    if (
        $spa &&
        $structure < 50
    ) {
        /*
        | Minimum prudent pour une SPA dont la structure est rendue
        | côté client et donc invisible dans le HTML initial.
        */
        $structure = 50;
    }

    /*
    |--------------------------------------------------------------------------
    | MOBILE
    |--------------------------------------------------------------------------
    */

    $mobile =
        $viewport
            ? 60
            : 0;

    $mobile +=
        preg_match(
            '/max-width|min-width|@media|responsive|viewport/i',
            $html
        )
            ? 10
            : 0;

    if ($imageTotal === 0) {
        $mobile += 10;
    } elseif (
        $imagesWithAlt === $imageTotal
    ) {
        $mobile += 15;
    } else {
        $mobile += 5;
    }

    $mobile +=
        preg_match(
            '/width\s*=\s*["\']\d{3,}["\']/i',
            $html
        )
            ? 0
            : 15;

    /*
    |--------------------------------------------------------------------------
    | CONTENT
    |--------------------------------------------------------------------------
    */

    $content = 0;

    $content +=
        $wordCount >= 300
            ? 35
            : (
                $wordCount >= 150
                    ? 25
                    : (
                        $wordCount >= 80
                            ? 15
                            : 5
                    )
            );

    $content +=
        $h2 >= 2
            ? 25
            : (
                $h2 === 1
                    ? 15
                    : 0
            );

    $content +=
        $h1 > 0
            ? 15
            : 0;

    $content +=
        $imageTotal > 0
            ? 10
            : 0;

    $content +=
        $hasContact
            ? 15
            : 0;

    if (
        $spa &&
        $content < 50
    ) {
        /*
        | Une SPA ne doit pas être considérée comme vide simplement
        | parce que son contenu est injecté par React.
        */
        $content = 50;
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE
    |--------------------------------------------------------------------------
    */

    $performance = 0;

    $performance +=
        $fetch['time'] <= 1
            ? 45
            : (
                $fetch['time'] <= 2
                    ? 35
                    : (
                        $fetch['time'] <= 4
                            ? 20
                            : 5
                    )
            );

    $performance +=
        $fetch['bytes'] <= 300000
            ? 30
            : (
                $fetch['bytes'] <= 700000
                    ? 20
                    : (
                        $fetch['bytes'] <= 1200000
                            ? 10
                            : 0
                    )
            );

    $performance +=
        $scriptCount <= 8
            ? 15
            : (
                $scriptCount <= 15
                    ? 10
                    : 5
            );

    $performance +=
        preg_match(
            '/<link[^>]+preload/i',
            $html
        )
            ? 10
            : 0;

    /*
    |--------------------------------------------------------------------------
    | SOCIAL
    |--------------------------------------------------------------------------
    */

    $social = 0;

    $social +=
        $ogTitle !== ''
            ? 30
            : 0;

    $social +=
        $ogDescription !== ''
            ? 30
            : 0;

    $social +=
        $ogImage !== ''
            ? 25
            : 0;

    $social +=
        $hasSocial
            ? 15
            : 0;

    /*
    |--------------------------------------------------------------------------
    | CONVERSION
    |--------------------------------------------------------------------------
    |
    | Le moteur regarde désormais :
    | - HTML initial
    | - bundles JavaScript des SPA
    | - CTA
    | - contact
    | - formulaires
    | - navigation vers contact/rendez-vous/audit/devis
    | - preuves sociales
    | - offres et tarifs
    |
    */

    $conversion = 0;

    if (
        $hasCta
    ) {
        $conversion += 30;
    }

    if (
        $hasContact
    ) {
        $conversion += 20;
    }

    if (
        $hasForm
    ) {
        $conversion += 20;
    }

    if (
        $hasTrust
    ) {
        $conversion += 15;
    }

    if (
        $hasOffer
    ) {
        $conversion += 10;
    }

    if (
        $hasNavigationConversion
    ) {
        $conversion += 5;
    }

    /*
    |--------------------------------------------------------------------------
    | GARDE-FOU SPA
    |--------------------------------------------------------------------------
    */

    if (
        $spa &&
        $renderedSource !== ''
    ) {
        /*
        | Si le bundle contient clairement des signaux de conversion,
        | on ne laisse pas le score tomber artificiellement à cause
        | de l'absence de rendu HTML côté serveur.
        */
        if (
            $hasCta ||
            $hasContact ||
            $hasForm ||
            $hasNavigationConversion
        ) {
            $conversion = max(
                $conversion,
                50
            );
        }
    }

    return [
        'url' =>
            $url,

        'title' =>
            $title,

        'description' =>
            $description,

        'h1' =>
            $h1,

        'h2' =>
            $h2,

        'h3' =>
            $h3,

        'spa' =>
            $spa,

        'words' =>
            $wordCount,

        'response' =>
            $fetch['time'],

        'bytes' =>
            $fetch['bytes'],

        'categories' => [
            'seo' =>
                min(
                    100,
                    $seo
                ),

            'structure' =>
                min(
                    100,
                    $structure
                ),

            'mobile' =>
                min(
                    100,
                    $mobile
                ),

            'content' =>
                min(
                    100,
                    $content
                ),

            'performance' =>
                min(
                    100,
                    $performance
                ),

            'social' =>
                min(
                    100,
                    $social
                ),

            'conversion' =>
                min(
                    100,
                    $conversion
                )
        ],

        'links' =>
            $links
    ];
}

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {
    respond([
        'success' => false,
        'message' =>
            'Méthode non autorisée.'
    ], 405);
}

if (
    clean(
        (string) (
            $_POST['action'] ?? ''
        )
    ) !== 'analyze'
) {
    respond([
        'success' => false,
        'message' =>
            'Action inconnue.'
    ], 400);
}

$inputUrl =
    clean(
        (string) (
            $_POST['url'] ?? ''
        ),
        2000
    );

$url =
    normalizeUrl(
        $inputUrl
    );

if (!$url) {
    respond([
        'success' => false,
        'message' =>
            'L’adresse du site n’est pas valide.'
    ], 422);
}

$parts =
    parse_url($url);

$rootHost =
    strtolower(
        rtrim(
            (string) (
                $parts['host'] ?? ''
            ),
            '.'
        )
    );

if (
    $rootHost === '' ||
    !hostIsSafe($rootHost)
) {
    respond([
        'success' => false,
        'message' =>
            'Ce site public ne peut pas être joint depuis notre serveur. Vérifiez l’adresse ou réessayez plus tard.'
    ], 422);
}

$startTime =
    microtime(true);

/*
|--------------------------------------------------------------------------
| PREMIÈRE PAGE
|--------------------------------------------------------------------------
*/

$rootFetch =
    fetchUrl($url);

if (
    !$rootFetch['success']
) {
    $reason =
        $rootFetch['error']
            ?: 'la réponse du serveur est inaccessible';

    respond([
        'success' => false,
        'message' =>
            'Vitrine+ n’a pas pu récupérer ce site : ' .
            $reason .
            '. Certains sites protégés par un pare-feu, une authentification ou un système anti-bot peuvent limiter les audits externes.'
    ], 422);
}

$finalUrl =
    normalizeUrl(
        (string) (
            $rootFetch['finalUrl']
        )
    ) ?: $url;

$finalParts =
    parse_url($finalUrl);

$finalHost =
    strtolower(
        rtrim(
            (string) (
                $finalParts['host'] ??
                $rootHost
            ),
            '.'
        )
    );

if (
    sameHost(
        $finalUrl,
        $rootHost
    )
) {
    $rootHost =
        $finalHost;
}

/*
|--------------------------------------------------------------------------
| FILE D’ATTENTE
|--------------------------------------------------------------------------
*/

$first =
    canonicalUrl(
        $finalUrl
    );

$queue = [
    $first
];

$queued = [
    $first => true
];

$visited = [];
$pages = [];
$discovered = [];

/*
|--------------------------------------------------------------------------
| SITEMAP
|--------------------------------------------------------------------------
*/

foreach (
    extractSitemapUrls(
        $finalUrl,
        $rootHost
    ) as $sitemapUrl
) {
    $normalized =
        canonicalUrl(
            $sitemapUrl
        );

    $discovered[
        $normalized
    ] = true;

    if (
        !isset(
            $queued[$normalized]
        ) &&
        count($queued) <
        MAX_PAGES
    ) {
        $queue[] =
            $normalized;

        $queued[
            $normalized
        ] = true;
    }
}

/*
|--------------------------------------------------------------------------
| CRAWL
|--------------------------------------------------------------------------
*/

while (
    $queue &&
    count($pages) <
    MAX_PAGES
) {
    $current =
        array_shift($queue);

    if (
        isset(
            $visited[$current]
        )
    ) {
        continue;
    }

    $visited[$current] =
        true;

    if (
        !sameHost(
            $current,
            $rootHost
        )
    ) {
        continue;
    }

    if (
        $current ===
        canonicalUrl($finalUrl)
    ) {
        $fetch =
            $rootFetch;
    } else {
        $fetch =
            fetchUrl($current);
    }

    if (
        !$fetch['success'] ||
        stripos(
            (string) (
                $fetch['contentType']
            ),
            'text/html'
        ) === false
    ) {
        continue;
    }

    $page =
        analyzePage(
            $current,
            $fetch,
            $rootHost
        );

    $pages[] =
        $page;

    foreach (
        $page['links'] as $link
    ) {
        $normalized =
            canonicalUrl($link);

        $discovered[
            $normalized
        ] = true;

        if (
            !isset(
                $queued[$normalized]
            ) &&
            count($queued) <
            MAX_PAGES
        ) {
            $queue[] =
                $normalized;

            $queued[
                $normalized
            ] = true;
        }
    }
}

if (!$pages) {
    respond([
        'success' => false,
        'message' =>
            'Le serveur du site n’a renvoyé aucune page HTML exploitable. Le site peut être protégé contre les requêtes automatisées ou dépendre entièrement d’un rendu navigateur.'
    ], 422);
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
    'conversion'
];

$totals =
    array_fill_keys(
        $keys,
        0
    );

foreach (
    $pages as $page
) {
    foreach (
        $keys as $key
    ) {
        $totals[$key] +=
            (int) (
                $page['categories'][$key]
            );
    }
}

$weights = [
    'seo' =>
        0.22,

    'structure' =>
        0.14,

    'mobile' =>
        0.14,

    'content' =>
        0.12,

    'performance' =>
        0.14,

    'social' =>
        0.08,

    'conversion' =>
        0.16
];

$categories = [];
$global = 0;

foreach (
    $keys as $key
) {
    $score =
        (int) round(
            $totals[$key] /
            count($pages)
        );

    $categories[$key] = [
        'score' =>
            $score,

        'label' =>
            ucfirst($key)
    ];

    $global +=
        $score *
        $weights[$key];
}

/*
|--------------------------------------------------------------------------
| RECOMMANDATIONS
|--------------------------------------------------------------------------
*/

$avg =
    fn(string $key): int =>
        (int) round(
            $totals[$key] /
            count($pages)
        );

$recommendations = [];

if (
    $avg('conversion') < 60
) {
    $recommendations[] =
        'Clarifier les appels à l’action, les points de contact et les éléments de réassurance pour transformer davantage de visites en demandes.';
}

if (
    $avg('seo') < 70
) {
    $recommendations[] =
        'Renforcer les fondamentaux SEO : titres, méta-descriptions, balises H1, structure sémantique et données de partage.';
}

if (
    $avg('performance') < 70
) {
    $recommendations[] =
        'Réduire le temps de réponse, le poids HTML et la complexité technique afin d’améliorer la vitesse perçue.';
}

if (
    $avg('mobile') < 70
) {
    $recommendations[] =
        'Vérifier et renforcer l’expérience mobile : viewport, dimensions des médias et adaptation des contenus.';
}

if (
    $avg('content') < 70
) {
    $recommendations[] =
        'Enrichir les pages importantes avec un contenu plus précis, structuré et orienté vers les intentions de recherche et les besoins clients.';
}

if (
    $avg('social') < 70
) {
    $recommendations[] =
        'Compléter les balises Open Graph et les signaux sociaux pour mieux contrôler l’apparence des partages.';
}

if (
    $avg('structure') < 70
) {
    $recommendations[] =
        'Améliorer la hiérarchie des titres et la structure HTML afin de rendre les pages plus lisibles pour les visiteurs et les moteurs.';
}

if (!$recommendations) {
    $recommendations[] =
        'Le socle est solide. Les prochaines optimisations doivent viser les détails de conversion, de contenu et de performance.';
}

$recommendations =
    array_slice(
        $recommendations,
        0,
        3
    );

/*
|--------------------------------------------------------------------------
| POINTS FORTS
|--------------------------------------------------------------------------
*/

$strengths = [];

$strengthMap = [
    'seo' =>
        'Les fondamentaux SEO sont globalement bien structurés.',

    'mobile' =>
        'Les principaux signaux d’adaptation mobile sont présents.',

    'performance' =>
        'Le temps de réponse et le poids HTML sont globalement maîtrisés.',

    'conversion' =>
        'Le site présente plusieurs éléments favorisant la prise de contact.',

    'social' =>
        'Les balises de partage social sont correctement renseignées.',

    'content' =>
        'Le contenu présente une base suffisamment structurée.'
];

foreach (
    $strengthMap as $key => $label
) {
    if (
        $avg($key) >= 75
    ) {
        $strengths[] =
            $label;
    }
}

/*
|--------------------------------------------------------------------------
| RÉPONSE
|--------------------------------------------------------------------------
*/

$responseTime =
    round(
        microtime(true) -
        $startTime,
        1
    );

respond([
    'success' =>
        true,

    'score' =>
        max(
            0,
            min(
                100,
                (int) round($global)
            )
        ),

    'pagesAnalyzed' =>
        count($pages),

    'pagesDiscovered' =>
        max(
            count($discovered),
            count($pages)
        ),

    'categories' =>
        $categories,

    'strengths' =>
        $strengths,

    'recommendations' =>
        $recommendations,

    'responseTime' =>
        $responseTime
]);