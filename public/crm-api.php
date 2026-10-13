<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vitrine+ — CRM commercial API
|--------------------------------------------------------------------------
|
| Sources :
| - Grand+
| - Audit
| - Contact
| - Rendez-vous
|
| Stockage :
| /vitrine-data/crm/crm.json
|
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const BASE_DIR = __DIR__;

const CONFIG_FILE =
    BASE_DIR . '/vitrine-mail-config.php';

const CRM_DIR =
    BASE_DIR . '/vitrine-data/crm';

const CRM_FILE =
    CRM_DIR . '/crm.json';

const AUDIT_FILE =
    BASE_DIR . '/vitrine-data/audit-leads.json';

const CONTACT_FILE =
    BASE_DIR . '/vitrine-data/contact-leads.json';

const GRAND_PLUS_FILE =
    BASE_DIR . '/vitrine-data/grand-plus/participations.json';

const BOOKINGS_FILE =
    BASE_DIR . '/vitrine-data/bookings.json';

/*
|--------------------------------------------------------------------------
| Réponse JSON
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
| Configuration
|--------------------------------------------------------------------------
*/

function load_config(): array
{
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    $config = require CONFIG_FILE;

    return is_array($config)
        ? $config
        : [];
}

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/

function require_auth(): void
{
    $config = load_config();

    $expectedUser = trim(
        (string) (
            $config['grand_plus_admin_user']
            ?? ''
        )
    );

    $expectedPassword = (string) (
        $config['grand_plus_admin_password']
        ?? ''
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

require_auth();

/*
|--------------------------------------------------------------------------
| Lecture JSON
|--------------------------------------------------------------------------
*/

function read_json(
    string $file,
    mixed $default = []
): mixed {
    if (!is_file($file)) {
        return $default;
    }

    $contents =
        @file_get_contents($file);

    if ($contents === false) {
        return $default;
    }

    $data =
        json_decode(
            $contents,
            true
        );

    return json_last_error() === JSON_ERROR_NONE
        ? $data
        : $default;
}

/*
|--------------------------------------------------------------------------
| Écriture JSON
|--------------------------------------------------------------------------
*/

function write_json(
    string $file,
    mixed $data
): bool {
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
        return false;
    }

    $json =
        json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($json === false) {
        return false;
    }

    return @file_put_contents(
        $file,
        $json,
        LOCK_EX
    ) !== false;
}

/*
|--------------------------------------------------------------------------
| Nettoyage
|--------------------------------------------------------------------------
*/

function clean(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim(
        preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $value
        ) ?? ''
    );
}

/*
|--------------------------------------------------------------------------
| Sources commerciales
|--------------------------------------------------------------------------
*/

function source_leads(): array
{
    $all = [];

    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    $audits =
        read_json(
            AUDIT_FILE,
            []
        );

    if (is_array($audits)) {
        foreach ($audits as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawId =
                (string) (
                    $item['id']
                    ?? hash(
                        'sha256',
                        json_encode($item)
                    )
                );

            $id =
                'audit-' . $rawId;

            $all[$id] = [
                'id' => $id,
                'source' => 'audit',

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
                    isset($item['score'])
                        ? (int) $item['score']
                        : null,

                'recommendations' =>
                    is_array(
                        $item['recommendations']
                        ?? null
                    )
                        ? $item['recommendations']
                        : [],
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONTACT
    |--------------------------------------------------------------------------
    */

    $contacts =
        read_json(
            CONTACT_FILE,
            []
        );

    if (is_array($contacts)) {
        foreach ($contacts as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawId =
                (string) (
                    $item['id']
                    ?? hash(
                        'sha256',
                        json_encode($item)
                    )
                );

            $id =
                'contact-' . $rawId;

            $all[$id] = [
                'id' => $id,
                'source' => 'contact',

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
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GRAND+
    |--------------------------------------------------------------------------
    */

    $grand =
        read_json(
            GRAND_PLUS_FILE,
            []
        );

    if (is_array($grand)) {
        foreach ($grand as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawId =
                (string) (
                    $item['id']
                    ?? hash(
                        'sha256',
                        json_encode($item)
                    )
                );

            $id =
                'grand-plus-' . $rawId;

            $all[$id] = [
                'id' => $id,
                'source' => 'grand-plus',

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
                        $item['marketing_consent']
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
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RENDEZ-VOUS
    |--------------------------------------------------------------------------
    */

    $bookings =
        read_json(
            BOOKINGS_FILE,
            []
        );

    if (is_array($bookings)) {
        foreach ($bookings as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawId =
                (string) (
                    $item['reference']
                    ?? hash(
                        'sha256',
                        json_encode($item)
                    )
                );

            $id =
                'booking-' . $rawId;

            $all[$id] = [
                'id' => $id,
                'source' => 'booking',

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

                'email' => '',

                'phone' =>
                    (string) (
                        $item['phone']
                        ?? ''
                    ),

                'website' => '',

                'booking_reference' =>
                    (string) (
                        $item['reference']
                        ?? ''
                    ),

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
            ];
        }
    }

    return array_values($all);
}

/*
|--------------------------------------------------------------------------
| Enrichissement CRM
|--------------------------------------------------------------------------
*/

function enrich(
    array $base,
    array $crm
): array {
    $meta =
        $crm[$base['id']]
        ?? [];

    if (is_array($meta)) {
        $base =
            array_merge(
                $base,
                $meta
            );
    }

    if (
        !isset($base['status']) ||
        !is_string($base['status']) ||
        $base['status'] === ''
    ) {
        $base['status'] =
            'new';
    }

    if (!isset($base['offer'])) {
        $base['offer'] = '';
    }

    if (!isset($base['estimated_value'])) {
        $base['estimated_value'] = 0;
    }

    if (!isset($base['recurring_value'])) {
        $base['recurring_value'] = 0;
    }

    if (!isset($base['last_contact_at'])) {
        $base['last_contact_at'] = '';
    }

    if (!isset($base['next_action'])) {
        $base['next_action'] = '';
    }

    if (!isset($base['next_action_at'])) {
        $base['next_action_at'] = '';
    }

    if (!isset($base['notes'])) {
        $base['notes'] = '';
    }

    if (
        !isset($base['interactions']) ||
        !is_array($base['interactions'])
    ) {
        $base['interactions'] = [];
    }

    return $base;
}

/*
|--------------------------------------------------------------------------
| GET — Tableau de bord
|--------------------------------------------------------------------------
*/

$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';

if ($method === 'GET') {
    $crm =
        read_json(
            CRM_FILE,
            []
        );

    if (!is_array($crm)) {
        $crm = [];
    }

    $prospects =
        array_map(
            fn(array $prospect) =>
                enrich(
                    $prospect,
                    $crm
                ),
            source_leads()
        );

    usort(
        $prospects,
        fn($a, $b) =>
            strcmp(
                (string) (
                    $b['created_at']
                    ?? ''
                ),
                (string) (
                    $a['created_at']
                    ?? ''
                )
            )
    );

    $bookings =
        read_json(
            BOOKINGS_FILE,
            []
        );

    $grand =
        read_json(
            GRAND_PLUS_FILE,
            []
        );

    $today =
        date('Y-m-d');

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

        'new' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? 'new')
                        === 'new'
                )
            ),

        'qualified' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? '')
                        === 'qualified'
                )
            ),

        'meetings' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? '')
                        === 'meeting'
                )
            ),

        'won' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? '')
                        === 'won'
                )
            ),

        'lost' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? '')
                        === 'lost'
                )
            ),

        'signed_revenue' =>
            array_sum(
                array_map(
                    fn($p) =>
                        ($p['status'] ?? '') === 'won'
                            ? (float) (
                                $p['estimated_value']
                                ?? 0
                            )
                            : 0,
                    $prospects
                )
            ),

        'potential_revenue' =>
            array_sum(
                array_map(
                    fn($p) =>
                        in_array(
                            ($p['status'] ?? ''),
                            [
                                'won',
                                'lost',
                            ],
                            true
                        )
                            ? 0
                            : (float) (
                                $p['estimated_value']
                                ?? 0
                            ),
                    $prospects
                )
            ),

        'today_actions' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['next_action_at'] ?? '')
                        === $today &&
                        in_array(
                            ($p['status'] ?? ''),
                            $activeStatuses,
                            true
                        )
                )
            ),

        'overdue_actions' =>
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['next_action_at'] ?? '') !== '' &&
                        ($p['next_action_at'] ?? '') < $today &&
                        in_array(
                            ($p['status'] ?? ''),
                            $activeStatuses,
                            true
                        )
                )
            ),
    ];

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */

    $sources = [];

    foreach ($prospects as $prospect) {
        $source =
            $prospect['source']
            ?? 'manual';

        $sources[$source] =
            ($sources[$source] ?? 0) + 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Pipeline
    |--------------------------------------------------------------------------
    */

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
        ] as $status
    ) {
        $pipeline[$status] =
            count(
                array_filter(
                    $prospects,
                    fn($p) =>
                        ($p['status'] ?? '')
                        === $status
                )
            );
    }

    respond([
        'success' => true,

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

        'grand_plus' =>
            is_array($grand)
                ? $grand
                : [],

        'bookings' =>
            is_array($bookings)
                ? $bookings
                : [],
    ]);
}

/*
|--------------------------------------------------------------------------
| Seules les requêtes POST peuvent modifier le CRM
|--------------------------------------------------------------------------
*/

if ($method !== 'POST') {
    respond(
        [
            'success' => false,
            'message' =>
                'Méthode non autorisée.',
        ],
        405
    );
}

/*
|--------------------------------------------------------------------------
| Lecture du body
|--------------------------------------------------------------------------
*/

$raw =
    file_get_contents(
        'php://input'
    );

$data =
    json_decode(
        $raw ?: '',
        true
    );

if (!is_array($data)) {
    respond(
        [
            'success' => false,
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

$crm =
    read_json(
        CRM_FILE,
        []
    );

if (!is_array($crm)) {
    $crm = [];
}

/*
|--------------------------------------------------------------------------
| UPDATE PROSPECT
|--------------------------------------------------------------------------
*/

if ($action === 'update_prospect') {
    $id =
        clean(
            $data['id']
            ?? ''
        );

    if ($id === '') {
        respond(
            [
                'success' => false,
                'message' =>
                    'Prospect introuvable.',
            ],
            422
        );
    }

    if (
        !isset($crm[$id]) ||
        !is_array($crm[$id])
    ) {
        $crm[$id] = [];
    }

    $allowed = [
        'status',
        'offer',
        'estimated_value',
        'recurring_value',
        'last_contact_at',
        'next_action',
        'next_action_at',
        'notes',
    ];

    foreach ($allowed as $field) {
        if (!array_key_exists(
            $field,
            $data
        )) {
            continue;
        }

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
            $crm[$id][$field] =
                max(
                    0,
                    (float) $data[$field]
                );
        } else {
            $crm[$id][$field] =
                clean(
                    $data[$field]
                );
        }
    }

    if (!write_json(
        CRM_FILE,
        $crm
    )) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer le prospect.',
            ],
            500
        );
    }

    respond([
        'success' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| ADD INTERACTION
|--------------------------------------------------------------------------
*/

if ($action === 'add_interaction') {
    $id =
        clean(
            $data['id']
            ?? ''
        );

    $type =
        clean(
            $data['type']
            ?? 'Note'
        );

    $text =
        clean(
            $data['text']
            ?? ''
        );

    if (
        $id === '' ||
        $text === ''
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Interaction incomplète.',
            ],
            422
        );
    }

    if (
        !isset($crm[$id]) ||
        !is_array($crm[$id])
    ) {
        $crm[$id] = [];
    }

    if (
        !isset(
            $crm[$id]['interactions']
        ) ||
        !is_array(
            $crm[$id]['interactions']
        )
    ) {
        $crm[$id]['interactions'] = [];
    }

    array_unshift(
        $crm[$id]['interactions'],
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
        ]
    );

    $crm[$id]['last_contact_at'] =
        date('Y-m-d');

    if (!write_json(
        CRM_FILE,
        $crm
    )) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer l’interaction.',
            ],
            500
        );
    }

    respond([
        'success' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| CREATE PROSPECT MANUEL
|--------------------------------------------------------------------------
*/

if ($action === 'create_prospect') {
    $id =
        'manual-' .
        date('YmdHis') .
        '-' .
        bin2hex(
            random_bytes(3)
        );

    $crm[$id] = [
        'id' =>
            $id,

        'source' =>
            'manual',

        'created_at' =>
            date(DATE_ATOM),

        'name' =>
            clean(
                $data['name']
                ?? ''
            ),

        'company' =>
            clean(
                $data['company']
                ?? ''
            ),

        'email' =>
            clean(
                $data['email']
                ?? ''
            ),

        'phone' =>
            clean(
                $data['phone']
                ?? ''
            ),

        'website' =>
            clean(
                $data['website']
                ?? ''
            ),

        'status' =>
            'new',

        'offer' =>
            '',

        'estimated_value' =>
            0,

        'recurring_value' =>
            0,

        'last_contact_at' =>
            '',

        'next_action' =>
            '',

        'next_action_at' =>
            '',

        'notes' =>
            clean(
                $data['notes']
                ?? ''
            ),

        'interactions' =>
            [],
    ];

    if (!write_json(
        CRM_FILE,
        $crm
    )) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible de créer le prospect.',
            ],
            500
        );
    }

    respond([
        'success' => true,
        'id' => $id,
    ]);
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