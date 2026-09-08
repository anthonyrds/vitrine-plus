<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

const TIMEZONE = 'Europe/Paris';
const MAX_DAYS_AHEAD = 365;
const STORAGE_DIR = __DIR__ . '/vitrine-data';
const AVAILABILITY_FILE = STORAGE_DIR . '/availability.json';
const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function loadConfig(): array
{
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    $config = require CONFIG_FILE;

    return is_array($config) ? $config : [];
}

function requireAuth(): void
{
    $config = loadConfig();

    $expectedUser = trim(
        (string)($config['grand_plus_admin_user'] ?? '')
    );

    $expectedPassword = (string)(
        $config['grand_plus_admin_password'] ?? ''
    );

    $user = (string)(
        $_SERVER['PHP_AUTH_USER'] ?? ''
    );

    $password = (string)(
        $_SERVER['PHP_AUTH_PW'] ?? ''
    );

    if (
        $expectedUser === '' ||
        $expectedPassword === '' ||
        !hash_equals($expectedUser, $user) ||
        !hash_equals($expectedPassword, $password)
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ Administration"'
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

function clean(
    mixed $value,
    int $max = 500
): string {
    if (!is_string($value)) {
        return '';
    }

    $value = trim(
        preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        ) ?? ''
    );

    return function_exists('mb_substr')
        ? mb_substr(
            $value,
            0,
            $max,
            'UTF-8'
        )
        : substr(
            $value,
            0,
            $max
        );
}

function validDate(string $date): bool
{
    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        return false;
    }

    $tz = new DateTimeZone(TIMEZONE);

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date,
        $tz
    );

    if (
        !$parsed ||
        $parsed->format('Y-m-d') !== $date
    ) {
        return false;
    }

    $today = new DateTimeImmutable(
        'today',
        $tz
    );

    $max = $today->modify(
        '+' . MAX_DAYS_AHEAD . ' days'
    );

    return (
        $parsed >= $today &&
        $parsed <= $max
    );
}

function validTime(string $time): bool
{
    return (bool)preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $time
    );
}

function minutes(string $time): int
{
    [
        $hours,
        $mins
    ] = array_map(
        'intval',
        explode(':', $time)
    );

    return ($hours * 60) + $mins;
}

function ensureStorage(): void
{
    if (
        !is_dir(STORAGE_DIR) &&
        !@mkdir(
            STORAGE_DIR,
            0755,
            true
        )
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Le stockage des disponibilités est indisponible.',
            ],
            500
        );
    }

    if (!file_exists(AVAILABILITY_FILE)) {
        if (
            @file_put_contents(
                AVAILABILITY_FILE,
                '[]',
                LOCK_EX
            ) === false
        ) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Le stockage des disponibilités est indisponible.',
                ],
                500
            );
        }
    }
}

function readBlocks(): array
{
    ensureStorage();

    $contents = @file_get_contents(
        AVAILABILITY_FILE
    );

    if (
        !is_string($contents) ||
        trim($contents) === ''
    ) {
        return [];
    }

    $data = json_decode(
        $contents,
        true
    );

    if (!is_array($data)) {
        return [];
    }

    return array_values(
        array_filter(
            $data,
            'is_array'
        )
    );
}

function writeBlocks(
    array $blocks
): void {
    $json = json_encode(
        array_values($blocks),
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if (
        !is_string($json) ||
        @file_put_contents(
            AVAILABILITY_FILE,
            $json,
            LOCK_EX
        ) === false
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer les disponibilités.',
            ],
            500
        );
    }
}

function blockOverlapsSlot(
    array $block,
    string $date,
    string $time
): bool {
    if (
        ($block['date'] ?? '') !== $date
    ) {
        return false;
    }

    if (!empty($block['all_day'])) {
        return true;
    }

    if (
        !validTime(
            (string)($block['start_time'] ?? '')
        ) ||
        !validTime(
            (string)($block['end_time'] ?? '')
        )
    ) {
        return false;
    }

    $start = minutes($time);
    $end = $start + 30;

    $blockStart = minutes(
        (string)$block['start_time']
    );

    $blockEnd = minutes(
        (string)$block['end_time']
    );

    return (
        $start < $blockEnd &&
        $blockStart < $end
    );
}

requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = clean(
        $_GET['action'] ?? ''
    );

    if ($action !== 'list') {
        respond(
            [
                'success' => false,
                'message' => 'Action inconnue.',
            ],
            400
        );
    }

    respond(
        [
            'success' => true,
            'blocks' => readBlocks(),
        ]
    );
}

if ($method !== 'POST') {
    respond(
        [
            'success' => false,
            'message' => 'Méthode non autorisée.',
        ],
        405
    );
}

$payload = json_decode(
    (string)file_get_contents(
        'php://input'
    ),
    true
);

if (!is_array($payload)) {
    respond(
        [
            'success' => false,
            'message' => 'Données invalides.',
        ],
        400
    );
}

$action = clean(
    $payload['action'] ?? ''
);

if ($action === 'delete_block') {
    $id = clean(
        $payload['id'] ?? ''
    );

    if ($id === '') {
        respond(
            [
                'success' => false,
                'message' => 'Identifiant manquant.',
            ],
            422
        );
    }

    $blocks = readBlocks();

    $found = false;

    $blocks = array_values(
        array_filter(
            $blocks,
            function (
                array $block
            ) use (
                $id,
                &$found
            ): bool {
                if (
                    (string)(
                        $block['id'] ?? ''
                    ) === $id
                ) {
                    $found = true;

                    return false;
                }

                return true;
            }
        )
    );

    if (!$found) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Indisponibilité introuvable.',
            ],
            404
        );
    }

    writeBlocks($blocks);

    respond(
        [
            'success' => true,
            'message' =>
                'Indisponibilité supprimée.',
            'blocks' => $blocks,
        ]
    );
}

if ($action !== 'add_block') {
    respond(
        [
            'success' => false,
            'message' => 'Action inconnue.',
        ],
        400
    );
}

$date = clean(
    $payload['date'] ?? '',
    10
);

$allDay = (bool)(
    $payload['all_day'] ?? false
);

$startTime = clean(
    $payload['start_time'] ?? '',
    5
);

$endTime = clean(
    $payload['end_time'] ?? '',
    5
);

$reason = clean(
    $payload['reason'] ?? 'Indisponible',
    160
);

if (!validDate($date)) {
    respond(
        [
            'success' => false,
            'message' =>
                'La date sélectionnée est invalide.',
        ],
        422
    );
}

if (!$allDay) {
    if (
        !validTime($startTime) ||
        !validTime($endTime)
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Les horaires sélectionnés sont invalides.',
            ],
            422
        );
    }

    if (
        minutes($startTime) >=
        minutes($endTime)
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'L’heure de début doit être avant l’heure de fin.',
            ],
            422
        );
    }
} else {
    $startTime = null;
    $endTime = null;
}

if ($reason === '') {
    $reason = 'Indisponible';
}

$blocks = readBlocks();

foreach ($blocks as $existing) {
    if (
        ($existing['date'] ?? '') !== $date
    ) {
        continue;
    }

    if (
        $allDay ||
        !empty($existing['all_day'])
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Une indisponibilité existe déjà sur cette date.',
            ],
            409
        );
    }

    if (
        !validTime(
            (string)($existing['start_time'] ?? '')
        ) ||
        !validTime(
            (string)($existing['end_time'] ?? '')
        )
    ) {
        continue;
    }

    $newStart = minutes($startTime);
    $newEnd = minutes($endTime);

    $existingStart = minutes(
        (string)$existing['start_time']
    );

    $existingEnd = minutes(
        (string)$existing['end_time']
    );

    if (
        $newStart < $existingEnd &&
        $existingStart < $newEnd
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Cette plage horaire chevauche une indisponibilité existante.',
            ],
            409
        );
    }
}

$tz = new DateTimeZone(TIMEZONE);

$newBlock = [
    'id' =>
        'AV-' .
        str_replace(
            '-',
            '',
            $date
        ) .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(4)
            )
        ),

    'date' => $date,

    'all_day' => $allDay,

    'start_time' => $startTime,

    'end_time' => $endTime,

    'reason' => $reason,

    'created_at' =>
        (new DateTimeImmutable(
            'now',
            $tz
        ))->format(
            DateTimeInterface::ATOM
        ),
];

$blocks[] = $newBlock;

usort(
    $blocks,
    static function (
        array $a,
        array $b
    ): int {
        $aKey =
            (string)($a['date'] ?? '') .
            ' ' .
            (
                !empty($a['all_day'])
                    ? '00:00'
                    : (string)($a['start_time'] ?? '00:00')
            );

        $bKey =
            (string)($b['date'] ?? '') .
            ' ' .
            (
                !empty($b['all_day'])
                    ? '00:00'
                    : (string)($b['start_time'] ?? '00:00')
            );

        return strcmp(
            $aKey,
            $bKey
        );
    }
);

writeBlocks($blocks);

respond(
    [
        'success' => true,
        'message' =>
            'Indisponibilité ajoutée.',
        'block' => $newBlock,
        'blocks' => $blocks,
    ]
);