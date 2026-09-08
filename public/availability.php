<?php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

const BASE_DIR = __DIR__;

const CONFIG_FILE =
    BASE_DIR . '/vitrine-mail-config.php';

const AVAILABILITY_FILE =
    BASE_DIR . '/vitrine-data/availability.json';

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

function config(): array {
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    $config =
        require CONFIG_FILE;

    return is_array($config)
        ? $config
        : [];
}

function require_auth(): void {
    $config =
        config();

    $user =
        trim(
            (string) (
                $config[
                    'grand_plus_admin_user'
                ] ?? ''
            )
        );

    $password =
        (string) (
            $config[
                'grand_plus_admin_password'
            ] ?? ''
        );

    $providedUser =
        $_SERVER[
            'PHP_AUTH_USER'
        ] ?? '';

    $providedPassword =
        $_SERVER[
            'PHP_AUTH_PW'
        ] ?? '';

    if (
        $user === '' ||
        $password === ''
    ) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Configuration administrateur absente.',
            ],
            500
        );
    }

    if (
        !hash_equals(
            $user,
            (string)
                $providedUser
        ) ||
        !hash_equals(
            $password,
            (string)
                $providedPassword
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ Disponibilités"'
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

function read_blocks(): array {
    if (
        !is_file(
            AVAILABILITY_FILE
        )
    ) {
        return [];
    }

    $content =
        file_get_contents(
            AVAILABILITY_FILE
        );

    if ($content === false) {
        return [];
    }

    $data =
        json_decode(
            $content,
            true
        );

    return is_array($data)
        ? $data
        : [];
}

function write_blocks(
    array $blocks
): bool {
    $directory =
        dirname(
            AVAILABILITY_FILE
        );

    if (
        !is_dir($directory) &&
        !mkdir(
            $directory,
            0755,
            true
        )
    ) {
        return false;
    }

    $json =
        json_encode(
            $blocks,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($json === false) {
        return false;
    }

    return
        file_put_contents(
            AVAILABILITY_FILE,
            $json . PHP_EOL,
            LOCK_EX
        ) !== false;
}

function clean(
    mixed $value
): string {
    return trim(
        strip_tags(
            (string) $value
        )
    );
}

function valid_date(
    string $date
): bool {
    $object =
        DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

    return
        $object !== false &&
        $object->format('Y-m-d') ===
            $date;
}

function valid_time(
    string $time
): bool {
    return
        preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $time
        ) === 1;
}

require_auth();

$blocks =
    read_blocks();

$method =
    $_SERVER[
        'REQUEST_METHOD'
    ] ?? 'GET';

if ($method === 'GET') {
    respond([
        'success' =>
            true,
        'blocks' =>
            $blocks,
    ]);
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

$raw =
    file_get_contents(
        'php://input'
    );

$data =
    json_decode(
        $raw ?: '{}',
        true
    );

if (!is_array($data)) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Données invalides.',
        ],
        400
    );
}

$action =
    clean(
        $data['action']
            ?? ''
    );

if (
    $action ===
    'add_block'
) {
    $date =
        clean(
            $data['date']
                ?? ''
        );

    $allDay =
        !empty(
            $data['all_day']
        );

    $start =
        isset(
            $data[
                'start_time'
            ]
        )
            ? clean(
                $data[
                    'start_time'
                ]
            )
            : '';

    $end =
        isset(
            $data[
                'end_time'
            ]
        )
            ? clean(
                $data[
                    'end_time'
                ]
            )
            : '';

    $reason =
        clean(
            $data['reason']
                ?? 'Indisponible'
        );

    if (!valid_date($date)) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Date invalide.',
            ],
            400
        );
    }

    if (!$allDay) {
        if (
            !valid_time(
                $start
            ) ||
            !valid_time(
                $end
            )
        ) {
            respond(
                [
                    'success' =>
                        false,
                    'message' =>
                        'Horaires invalides.',
                ],
                400
            );
        }

        if ($start >= $end) {
            respond(
                [
                    'success' =>
                        false,
                    'message' =>
                        'L’heure de début doit être avant l’heure de fin.',
                ],
                400
            );
        }
    }

    $block = [
        'id' =>
            'block-' .
            date('YmdHis') .
            '-' .
            bin2hex(
                random_bytes(3)
            ),

        'date' =>
            $date,

        'all_day' =>
            $allDay,

        'start_time' =>
            $allDay
                ? null
                : $start,

        'end_time' =>
            $allDay
                ? null
                : $end,

        'reason' =>
            $reason !== ''
                ? $reason
                : 'Indisponible',

        'created_at' =>
            date(DATE_ATOM),
    ];

    $blocks[] =
        $block;

    if (
        !write_blocks(
            $blocks
        )
    ) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Impossible d’enregistrer l’indisponibilité.',
            ],
            500
        );
    }

    respond([
        'success' =>
            true,
        'block' =>
            $block,
        'blocks' =>
            $blocks,
    ]);
}

if (
    $action ===
    'delete_block'
) {
    $id =
        clean(
            $data['id']
                ?? ''
        );

    if ($id === '') {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Identifiant invalide.',
            ],
            400
        );
    }

    $found = false;

    $blocks =
        array_values(
            array_filter(
                $blocks,
                function (
                    $block
                ) use (
                    $id,
                    &$found
                ) {
                    if (
                        is_array(
                            $block
                        ) &&
                        (
                            string
                        ) (
                            $block['id']
                            ?? ''
                        ) === $id
                    ) {
                        $found =
                            true;

                        return false;
                    }

                    return true;
                }
            )
        );

    if (!$found) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Indisponibilité introuvable.',
            ],
            404
        );
    }

    if (
        !write_blocks(
            $blocks
        )
    ) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Impossible de supprimer l’indisponibilité.',
            ],
            500
        );
    }

    respond([
        'success' =>
            true,
        'blocks' =>
            $blocks,
    ]);
}

respond(
    [
        'success' =>
            false,
        'message' =>
            'Action inconnue.',
    ],
    400
);