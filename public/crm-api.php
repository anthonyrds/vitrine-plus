<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

const BASE = __DIR__;

const CONFIG_FILE = BASE . '/vitrine-mail-config.php';

const CRM_FILE = BASE . '/vitrine-data/crm/crm.json';

const AUDIT_FILE = BASE . '/vitrine-data/audit-leads.json';

const CONTACT_FILE = BASE . '/vitrine-data/contact-leads.json';

const GRAND_FILE = BASE . '/vitrine-data/grand-plus/participations.json';

const BOOKINGS_FILE = BASE . '/vitrine-data/bookings.json';


/* ================================================================
   RESPONSE
================================================================ */

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


/* ================================================================
   CONFIG
================================================================ */

function config(): array
{
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    $config = require CONFIG_FILE;

    return is_array($config)
        ? $config
        : [];
}


/* ================================================================
   AUTHENTIFICATION
================================================================ */

function requireAuth(): void
{
    $config = config();

    $username = trim(
        (string) (
            $config['grand_plus_admin_user']
            ?? ''
        )
    );

    $password = (string) (
        $config['grand_plus_admin_password']
        ?? ''
    );

    $authUser = (string) (
        $_SERVER['PHP_AUTH_USER']
        ?? ''
    );

    $authPassword = (string) (
        $_SERVER['PHP_AUTH_PW']
        ?? ''
    );

    if (
        $username === '' ||
        $password === '' ||
        !hash_equals(
            $username,
            $authUser
        ) ||
        !hash_equals(
            $password,
            $authPassword
        )
    ) {
        header(
            'WWW-Authenticate: Basic realm="Vitrine+ CRM"'
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


/* ================================================================
   HELPERS
================================================================ */

function clean(
    mixed $value,
    int $max = 2000
): string {
    $value = trim(
        (string) $value
    );

    $value =
        preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        ) ?? '';

    if (
        function_exists(
            'mb_substr'
        )
    ) {
        return mb_substr(
            $value,
            0,
            $max,
            'UTF-8'
        );
    }

    return substr(
        $value,
        0,
        $max
    );
}


function readJson(
    string $file,
    mixed $fallback = []
): mixed {
    if (!is_file($file)) {
        return $fallback;
    }

    $raw =
        @file_get_contents(
            $file
        );

    if (
        !is_string($raw) ||
        trim($raw) === ''
    ) {
        return $fallback;
    }

    $data =
        json_decode(
            $raw,
            true
        );

    if (
        json_last_error() !==
        JSON_ERROR_NONE
    ) {
        return $fallback;
    }

    return $data;
}


function writeJson(
    string $file,
    mixed $data
): void {
    $directory =
        dirname($file);

    if (
        !is_dir($directory) &&
        !@mkdir(
            $directory,
            0755,
            true
        )
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible de créer le stockage.',
            ],
            500
        );
    }

    $json =
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );

    if (
        !is_string($json) ||
        @file_put_contents(
            $file,
            $json,
            LOCK_EX
        ) === false
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer les données.',
            ],
            500
        );
    }
}


function arr(
    string $file
): array {
    $data =
        readJson(
            $file,
            []
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


function idFor(
    string $prefix,
    mixed $raw
): string {
    $raw =
        (string) $raw;

    if ($raw === '') {
        $raw =
            bin2hex(
                random_bytes(8)
            );
    }

    return $prefix . $raw;
}


/* ================================================================
   DEFAULT CRM
================================================================ */

function statusDefaults(
    array $prospect
): array {
    $defaults = [
        'status' => 'new',
        'offer' => '',
        'estimated_value' => 0,
        'recurring_value' => 0,
        'last_contact_at' => '',
        'next_action' => '',
        'next_action_at' => '',
        'notes' => '',
        'interactions' => [],
    ];

    foreach (
        $defaults as $key => $value
    ) {
        if (
            !array_key_exists(
                $key,
                $prospect
            )
        ) {
            $prospect[$key] =
                $value;
        }
    }

    if (
        !is_array(
            $prospect['interactions']
        )
    ) {
        $prospect['interactions'] =
            [];
    }

    return $prospect;
}


/* ================================================================
   SOURCES
================================================================ */

function bookings(): array
{
    return arr(
        BOOKINGS_FILE
    );
}


function crm(): array
{
    $data =
        readJson(
            CRM_FILE,
            []
        );

    return is_array($data)
        ? $data
        : [];
}


/* ================================================================
   CONSTRUCTION DES PROSPECTS
================================================================ */

function buildProspects(): array
{
    $metadata =
        crm();

    $all = [];


    /* AUDIT */

    foreach (
        arr(AUDIT_FILE)
        as $item
    ) {
        $id =
            idFor(
                'audit-',
                $item['id']
                    ?? hash(
                        'sha256',
                        json_encode(
                            $item
                        )
                    )
            );

        $all[$id] =
            statusDefaults(
                [
                    'id' =>
                        $id,

                    'source' =>
                        'audit',

                    'created_at' =>
                        (string) (
                            $item['created_at']
                            ?? ''
                        ),

                    'name' =>
                        (string) (
                            $item['name']
                            ?? ''
                        ),

                    'company' =>
                        (string) (
                            $item['company']
                            ?? ''
                        ),

                    'email' =>
                        (string) (
                            $item['email']
                            ?? ''
                        ),

                    'phone' =>
                        (string) (
                            $item['phone']
                            ?? ''
                        ),

                    'website' =>
                        (string) (
                            $item['website']
                            ?? ''
                        ),

                    'audit_score' =>
                        isset(
                            $item['score']
                        )
                            ? (int) $item['score']
                            : null,

                    'recommendations' =>
                        is_array(
                            $item['recommendations']
                            ?? null
                        )
                            ? $item['recommendations']
                            : [],
                ]
            );
    }


    /* CONTACT */

    foreach (
        arr(CONTACT_FILE)
        as $item
    ) {
        $id =
            idFor(
                'contact-',
                $item['id']
                    ?? hash(
                        'sha256',
                        json_encode(
                            $item
                        )
                    )
            );

        $all[$id] =
            statusDefaults(
                [
                    'id' =>
                        $id,

                    'source' =>
                        'contact',

                    'created_at' =>
                        (string) (
                            $item['created_at']
                            ?? ''
                        ),

                    'name' =>
                        (string) (
                            $item['name']
                            ?? ''
                        ),

                    'company' =>
                        (string) (
                            $item['company']
                            ?? ''
                        ),

                    'email' =>
                        (string) (
                            $item['email']
                            ?? ''
                        ),

                    'phone' =>
                        (string) (
                            $item['phone']
                            ?? ''
                        ),

                    'website' => '',

                    'budget' =>
                        (string) (
                            $item['budget']
                            ?? ''
                        ),

                    'message' =>
                        (string) (
                            $item['message']
                            ?? ''
                        ),
                ]
            );
    }


    /* GRAND+ */

    foreach (
        arr(GRAND_FILE)
        as $item
    ) {
        $id =
            idFor(
                'grand-plus-',
                $item['id']
                    ?? hash(
                        'sha256',
                        json_encode(
                            $item
                        )
                    )
            );

        $all[$id] =
            statusDefaults(
                [
                    'id' =>
                        $id,

                    'source' =>
                        'grand-plus',

                    'created_at' =>
                        (string) (
                            $item['created_at']
                            ?? ''
                        ),

                    'name' =>
                        (string) (
                            $item['name']
                            ?? ''
                        ),

                    'company' =>
                        (string) (
                            $item['company']
                            ?? ''
                        ),

                    'email' =>
                        (string) (
                            $item['email']
                            ?? ''
                        ),

                    'phone' =>
                        (string) (
                            $item['phone']
                            ?? ''
                        ),

                    'website' =>
                        (string) (
                            $item['website']
                            ?? ''
                        ),

                    'sector' =>
                        (string) (
                            $item['sector']
                            ?? ''
                        ),

                    'problem' =>
                        (string) (
                            $item['problem']
                            ?? ''
                        ),

                    'marketing_consent' =>
                        !empty(
                            $item[
                                'marketing_consent'
                            ]
                        ),

                    'grand_plus_status' =>
                        (string) (
                            $item['status']
                            ?? 'pending'
                        ),

                    'month_key' =>
                        (string) (
                            $item['month_key']
                            ?? ''
                        ),
                ]
            );
    }


    /* RENDEZ-VOUS */

    foreach (
        bookings()
        as $item
    ) {
        $reference =
            (string) (
                $item['reference']
                ?? ''
            );

        $id =
            idFor(
                'booking-',
                $reference !== ''
                    ? $reference
                    : hash(
                        'sha256',
                        json_encode(
                            $item
                        )
                    )
            );

        $all[$id] =
            statusDefaults(
                [
                    'id' =>
                        $id,

                    'source' =>
                        'booking',

                    'created_at' =>
                        (string) (
                            $item['created_at']
                            ?? ''
                        ),

                    'name' =>
                        (string) (
                            $item['name']
                            ?? ''
                        ),

                    'company' =>
                        (string) (
                            $item['company']
                            ?? ''
                        ),

                    'email' =>
                        (string) (
                            $item['email']
                            ?? ''
                        ),

                    'phone' =>
                        (string) (
                            $item['phone']
                            ?? ''
                        ),

                    'website' => '',

                    'booking_reference' =>
                        $reference,

                    'booking_date' =>
                        (string) (
                            $item['date']
                            ?? ''
                        ),

                    'booking_time' =>
                        (string) (
                            $item['time']
                            ?? ''
                        ),

                    'booking_status' =>
                        (string) (
                            $item['status']
                            ?? 'confirmed'
                        ),

                    'reason' =>
                        (string) (
                            $item['reason']
                            ?? ''
                        ),
                ]
            );
    }


    /* PROSPECTS MANUELS */

    foreach (
        $metadata as $id => $item
    ) {
        if (
            !isset($all[$id]) &&
            is_array($item)
        ) {
            $all[$id] =
                statusDefaults(
                    array_merge(
                        [
                            'id' =>
                                (string) $id,

                            'source' =>
                                'manual',

                            'created_at' =>
                                (string) (
                                    $item[
                                        'created_at'
                                    ]
                                    ?? date(
                                        DATE_ATOM
                                    )
                                ),
                        ],
                        $item
                    )
                );
        }
    }


    /* METADATA CRM */

    foreach (
        $all as $id => $prospect
    ) {
        if (
            isset(
                $metadata[$id]
            ) &&
            is_array(
                $metadata[$id]
            )
        ) {
            $all[$id] =
                statusDefaults(
                    array_merge(
                        $prospect,
                        $metadata[$id]
                    )
                );
        }
    }


    $result =
        array_values($all);

    usort(
        $result,
        function (
            array $a,
            array $b
        ) {
            return strcmp(
                (string) (
                    $b['created_at']
                    ?? ''
                ),
                (string) (
                    $a['created_at']
                    ?? ''
                )
            );
        }
    );

    return $result;
}


/* ================================================================
   AUTH
================================================================ */

requireAuth();


/* ================================================================
   GET
================================================================ */

$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';

if ($method === 'GET') {
    $prospects =
        buildProspects();

    $bookingList =
        bookings();

    $grand =
        arr(GRAND_FILE);

    $today =
        (
            new DateTimeImmutable(
                'today',
                new DateTimeZone(
                    'Europe/Paris'
                )
            )
        )->format(
            'Y-m-d'
        );

    $activeStatuses = [
        'new',
        'contacted',
        'qualified',
        'meeting',
        'proposal',
        'negotiation',
    ];

    $stats = [
        'prospects' =>
            count($prospects),

        'new' => 0,

        'qualified' => 0,

        'meetings' => 0,

        'won' => 0,

        'lost' => 0,

        'signed_revenue' => 0,

        'potential_revenue' => 0,

        'today_actions' => 0,

        'overdue_actions' => 0,

        'upcoming_bookings' => 0,

        'confirmed_bookings' => 0,
    ];


    foreach (
        $prospects as $prospect
    ) {
        $status =
            (string) (
                $prospect['status']
                ?? 'new'
            );

        if (
            isset(
                $stats[$status]
            )
        ) {
            $stats[$status]++;
        }

        if (
            $status === 'won'
        ) {
            $stats[
                'signed_revenue'
            ] +=
                (float) (
                    $prospect[
                        'estimated_value'
                    ]
                    ?? 0
                );
        }

        if (
            !in_array(
                $status,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            $stats[
                'potential_revenue'
            ] +=
                (float) (
                    $prospect[
                        'estimated_value'
                    ]
                    ?? 0
                );
        }

        $nextAction =
            (string) (
                $prospect[
                    'next_action_at'
                ]
                ?? ''
            );

        if (
            $nextAction === $today &&
            in_array(
                $status,
                $activeStatuses,
                true
            )
        ) {
            $stats[
                'today_actions'
            ]++;
        }

        if (
            $nextAction !== '' &&
            $nextAction < $today &&
            in_array(
                $status,
                $activeStatuses,
                true
            )
        ) {
            $stats[
                'overdue_actions'
            ]++;
        }
    }


    $now =
        new DateTimeImmutable(
            'now',
            new DateTimeZone(
                'Europe/Paris'
            )
        );


    foreach (
        $bookingList as $booking
    ) {
        $bookingStatus =
            (string) (
                $booking['status']
                ?? 'confirmed'
            );

        if (
            $bookingStatus ===
            'confirmed'
        ) {
            $stats[
                'confirmed_bookings'
            ]++;
        }

        $date =
            (string) (
                $booking['date']
                ?? ''
            );

        $time =
            (string) (
                $booking['time']
                ?? ''
            );

        if (
            $date === '' ||
            $time === ''
        ) {
            continue;
        }

        try {
            $dateTime =
                new DateTimeImmutable(
                    $date .
                    ' ' .
                    $time .
                    ':00',
                    new DateTimeZone(
                        'Europe/Paris'
                    )
                );

            if (
                $dateTime >= $now &&
                $bookingStatus ===
                    'confirmed'
            ) {
                $stats[
                    'upcoming_bookings'
                ]++;
            }
        } catch (
            Throwable
        ) {
            // Rien
        }
    }


    $sources = [];

    foreach (
        $prospects as $prospect
    ) {
        $source =
            $prospect['source']
            ?? 'manual';

        $sources[$source] =
            ($sources[$source] ?? 0) +
            1;
    }


    $pipeline = [];

    foreach (
        [
            'new',
            'contacted',
            'qualified',
            'meeting',
            'proposal',
            'negotiation',
            'won',
            'lost',
        ] as $pipelineStatus
    ) {
        $pipeline[
            $pipelineStatus
        ] =
            count(
                array_filter(
                    $prospects,
                    function (
                        $prospect
                    ) use (
                        $pipelineStatus
                    ) {
                        return (
                            $prospect[
                                'status'
                            ]
                            ?? ''
                        ) ===
                            $pipelineStatus;
                    }
                )
            );
    }


    respond(
        [
            'success' =>
                true,

            'generated_at' =>
                date(DATE_ATOM),

            'stats' =>
                $stats,

            'sources' =>
                $sources,

            'pipeline' =>
                $pipeline,

            'prospects' =>
                $prospects,

            'bookings' =>
                $bookingList,

            'grand_plus' =>
                $grand,
        ]
    );
}


/* ================================================================
   POST
================================================================ */

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


$rawInput =
    file_get_contents(
        'php://input'
    );

$payload =
    json_decode(
        $rawInput ?: '{}',
        true
    );

if (!is_array($payload)) {
    $payload = [];
}

$action =
    clean(
        $payload['action']
        ?? ''
    );

$metadata =
    crm();


/* ================================================================
   CRÉER UN PROSPECT
================================================================ */

if (
    $action ===
    'create_prospect'
) {
    $id =
        'manual-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(4)
        );

    $metadata[$id] =
        statusDefaults(
            [
                'id' =>
                    $id,

                'source' =>
                    'manual',

                'created_at' =>
                    date(DATE_ATOM),

                'name' =>
                    clean(
                        $payload[
                            'name'
                        ] ?? '',
                        120
                    ),

                'company' =>
                    clean(
                        $payload[
                            'company'
                        ] ?? '',
                        160
                    ),

                'email' =>
                    clean(
                        $payload[
                            'email'
                        ] ?? '',
                        180
                    ),

                'phone' =>
                    clean(
                        $payload[
                            'phone'
                        ] ?? '',
                        80
                    ),

                'website' =>
                    clean(
                        $payload[
                            'website'
                        ] ?? '',
                        300
                    ),

                'notes' =>
                    clean(
                        $payload[
                            'notes'
                        ] ?? '',
                        5000
                    ),
            ]
        );

    writeJson(
        CRM_FILE,
        $metadata
    );

    respond(
        [
            'success' =>
                true,

            'id' =>
                $id,
        ]
    );
}


/* ================================================================
   SUPPRIMER UN PROSPECT
================================================================ */

if (
    $action ===
    'delete_prospect'
) {
    $id =
        clean(
            $payload['id']
            ?? '',
            300
        );

    if ($id === '') {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable.',
            ],
            422
        );
    }


    $prospects =
        buildProspects();

    $prospect =
        null;

    foreach (
        $prospects as $item
    ) {
        if (
            (
                $item['id']
                ?? ''
            ) === $id
        ) {
            $prospect =
                $item;

            break;
        }
    }


    if (
        !is_array($prospect)
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable.',
            ],
            404
        );
    }


    $source =
        (string) (
            $prospect['source']
            ?? 'manual'
        );


    /*
     * PROSPECT MANUEL
     */

    if (
        $source ===
        'manual'
    ) {
        unset(
            $metadata[$id]
        );

        writeJson(
            CRM_FILE,
            $metadata
        );

        respond(
            [
                'success' =>
                    true,
            ]
        );
    }


    /*
     * PROSPECT ISSU D'UNE SOURCE
     */

    $sourceFiles = [
        'audit' =>
            AUDIT_FILE,

        'contact' =>
            CONTACT_FILE,

        'grand-plus' =>
            GRAND_FILE,

        'booking' =>
            BOOKINGS_FILE,
    ];


    if (
        !isset(
            $sourceFiles[$source]
        )
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Cette source ne peut pas être supprimée.',
            ],
            400
        );
    }


    $file =
        $sourceFiles[$source];

    $items =
        arr($file);

    $before =
        count($items);


    /*
     * RENDEZ-VOUS
     */

    if (
        $source ===
        'booking'
    ) {
        $reference =
            (string) (
                $prospect[
                    'booking_reference'
                ]
                ?? ''
            );

        $items =
            array_values(
                array_filter(
                    $items,
                    function (
                        $item
                    ) use (
                        $reference
                    ) {
                        return (
                            (string) (
                                $item[
                                    'reference'
                                ]
                                ?? ''
                            )
                        ) !==
                            $reference;
                    }
                )
            );
    } else {
        /*
         * AUDIT / CONTACT / GRAND+
         */

        $prefix =
            match ($source) {
                'audit' =>
                    'audit-',

                'contact' =>
                    'contact-',

                'grand-plus' =>
                    'grand-plus-',

                default =>
                    '',
            };

        $rawId =
            str_starts_with(
                $id,
                $prefix
            )
                ? substr(
                    $id,
                    strlen($prefix)
                )
                : $id;

        $items =
            array_values(
                array_filter(
                    $items,
                    function (
                        $item
                    ) use (
                        $rawId
                    ) {
                        return (
                            (string) (
                                $item['id']
                                ?? ''
                            )
                        ) !==
                            $rawId;
                    }
                )
            );
    }


    if (
        count($items) ===
        $before
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable dans sa source.',
            ],
            404
        );
    }


    writeJson(
        $file,
        $items
    );


    /*
     * SUPPRESSION DES MÉTADONNÉES CRM
     */

    unset(
        $metadata[$id]
    );

    writeJson(
        CRM_FILE,
        $metadata
    );


    respond(
        [
            'success' =>
                true,
        ]
    );
}


/* ================================================================
   MODIFIER UN PROSPECT
================================================================ */

if (
    $action ===
    'update_prospect'
) {
    $id =
        clean(
            $payload['id']
            ?? '',
            300
        );

    if ($id === '') {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable.',
            ],
            422
        );
    }


    $prospects =
        buildProspects();

    $existing =
        null;

    foreach (
        $prospects as $item
    ) {
        if (
            (
                $item['id']
                ?? ''
            ) === $id
        ) {
            $existing =
                $item;

            break;
        }
    }


    if (
        !is_array($existing)
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable.',
            ],
            404
        );
    }


    if (
        !isset(
            $metadata[$id]
        ) ||
        !is_array(
            $metadata[$id]
        )
    ) {
        $metadata[$id] =
            [
                'id' =>
                    $id,

                'source' =>
                    $existing[
                        'source'
                    ] ?? 'manual',

                'created_at' =>
                    $existing[
                        'created_at'
                    ] ?? date(
                        DATE_ATOM
                    ),

                'interactions' =>
                    $existing[
                        'interactions'
                    ] ?? [],
            ];
    }


    $fields = [
        'name',
        'company',
        'email',
        'phone',
        'website',
        'status',
        'offer',
        'estimated_value',
        'recurring_value',
        'last_contact_at',
        'next_action',
        'next_action_at',
        'notes',
    ];


    foreach (
        $fields as $field
    ) {
        if (
            !array_key_exists(
                $field,
                $payload
            )
        ) {
            continue;
        }


        $value =
            $payload[$field];


        if (
            in_array(
                $field,
                [
                    'estimated_value',
                    'recurring_value',
                ],
                true
            )
        ) {
            $value =
                (float) $value;
        } else {
            $value =
                clean(
                    $value,
                    5000
                );
        }


        $metadata[$id][$field] =
            $value;
    }


    writeJson(
        CRM_FILE,
        $metadata
    );


    respond(
        [
            'success' =>
                true,

            'prospect' =>
                statusDefaults(
                    array_merge(
                        $existing,
                        $metadata[$id]
                    )
                ),
        ]
    );
}


/* ================================================================
   AJOUTER UNE INTERACTION
================================================================ */

if (
    $action ===
    'add_interaction'
) {
    $id =
        clean(
            $payload['id']
            ?? '',
            300
        );

    $text =
        clean(
            $payload['text']
            ?? '',
            5000
        );

    $type =
        clean(
            $payload['type']
            ?? 'note',
            50
        );


    if (
        $id === '' ||
        $text === ''
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Interaction incomplète.',
            ],
            422
        );
    }


    $prospects =
        buildProspects();

    $existing =
        null;

    foreach (
        $prospects as $item
    ) {
        if (
            (
                $item['id']
                ?? ''
            ) === $id
        ) {
            $existing =
                $item;

            break;
        }
    }


    if (
        !is_array($existing)
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Prospect introuvable.',
            ],
            404
        );
    }


    if (
        !isset(
            $metadata[$id]
        ) ||
        !is_array(
            $metadata[$id]
        )
    ) {
        $metadata[$id] =
            [
                'id' =>
                    $id,

                'source' =>
                    $existing[
                        'source'
                    ] ?? 'manual',

                'created_at' =>
                    $existing[
                        'created_at'
                    ] ?? date(
                        DATE_ATOM
                    ),
            ];
    }


    if (
        !isset(
            $metadata[$id][
                'interactions'
            ]
        ) ||
        !is_array(
            $metadata[$id][
                'interactions'
            ]
        )
    ) {
        $metadata[$id][
            'interactions'
        ] =
            $existing[
                'interactions'
            ] ?? [];
    }


    $metadata[$id][
        'interactions'
    ][] =
        [
            'id' =>
                bin2hex(
                    random_bytes(8)
                ),

            'created_at' =>
                date(DATE_ATOM),

            'type' =>
                $type,

            'text' =>
                $text,
        ];


    $metadata[$id][
        'last_contact_at'
    ] =
        date('Y-m-d');


    writeJson(
        CRM_FILE,
        $metadata
    );


    respond(
        [
            'success' =>
                true,
        ]
    );
}


/* ================================================================
   MODIFIER UN RENDEZ-VOUS
================================================================ */

if (
    $action ===
    'update_booking'
) {
    $reference =
        clean(
            $payload[
                'reference'
            ] ?? '',
            100
        );


    if (
        $reference === ''
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Référence manquante.',
            ],
            422
        );
    }


    $items =
        bookings();

    $found =
        false;


    foreach (
        $items as &$booking
    ) {
        if (
            (
                $booking[
                    'reference'
                ] ?? ''
            ) ===
            $reference
        ) {
            $found =
                true;


            if (
                isset(
                    $payload[
                        'status'
                    ]
                )
            ) {
                $booking[
                    'status'
                ] =
                    clean(
                        $payload[
                            'status'
                        ],
                        50
                    );
            }


            if (
                isset(
                    $payload[
                        'reason'
                    ]
                )
            ) {
                $booking[
                    'reason'
                ] =
                    clean(
                        $payload[
                            'reason'
                        ],
                        2500
                    );
            }


            break;
        }
    }

    unset($booking);


    if (!$found) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Rendez-vous introuvable.',
            ],
            404
        );
    }


    writeJson(
        BOOKINGS_FILE,
        $items
    );


    respond(
        [
            'success' =>
                true,
        ]
    );
}


/* ================================================================
   SUPPRIMER UN RENDEZ-VOUS
================================================================ */

if (
    $action ===
    'delete_booking'
) {
    $reference =
        clean(
            $payload[
                'reference'
            ] ?? '',
            100
        );


    if (
        $reference === ''
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Référence du rendez-vous manquante.',
            ],
            422
        );
    }


    $items =
        bookings();


    $filtered =
        array_values(
            array_filter(
                $items,
                function (
                    $booking
                ) use (
                    $reference
                ) {
                    return (
                        (string) (
                            $booking[
                                'reference'
                            ] ?? ''
                        )
                    ) !==
                        $reference;
                }
            )
        );


    if (
        count($filtered) ===
        count($items)
    ) {
        respond(
            [
                'success' =>
                    false,

                'message' =>
                    'Rendez-vous introuvable.',
            ],
            404
        );
    }


    writeJson(
        BOOKINGS_FILE,
        $filtered
    );


    /*
     * On supprime également
     * les métadonnées CRM
     * associées au rendez-vous.
     */

    $crmId =
        'booking-' .
        $reference;

    unset(
        $metadata[$crmId]
    );

    writeJson(
        CRM_FILE,
        $metadata
    );


    respond(
        [
            'success' =>
                true,
        ]
    );
}


/* ================================================================
   ACTION INCONNUE
================================================================ */

respond(
    [
        'success' =>
            false,

        'message' =>
            'Action inconnue.',
    ],
    400
);