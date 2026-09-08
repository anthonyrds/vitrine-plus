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

const BOOKINGS_FILE =
    BASE_DIR . '/vitrine-data/bookings.json';

const AVAILABILITY_FILE =
    BASE_DIR . '/vitrine-data/availability.json';

const SLOT_MINUTES = 30;

const OPEN_HOUR = 9;

const CLOSE_HOUR = 18;

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

function read_json(
    string $file,
    mixed $default = []
): mixed {
    if (!is_file($file)) {
        return $default;
    }

    $content =
        @file_get_contents($file);

    if ($content === false) {
        return $default;
    }

    $data =
        json_decode(
            $content,
            true
        );

    return json_last_error() === JSON_ERROR_NONE
        ? $data
        : $default;
}

function write_json(
    string $file,
    mixed $data
): bool {
    $directory =
        dirname($file);

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
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($json === false) {
        return false;
    }

    return
        file_put_contents(
            $file,
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

function slot_is_blocked(
    string $date,
    string $time,
    array $blocks
): bool {
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (
            ($block['date'] ?? '') !==
            $date
        ) {
            continue;
        }

        if (
            !empty(
                $block['all_day']
            )
        ) {
            return true;
        }

        $start =
            (string) (
                $block['start_time']
                ?? ''
            );

        $end =
            (string) (
                $block['end_time']
                ?? ''
            );

        if (
            !valid_time($start) ||
            !valid_time($end)
        ) {
            continue;
        }

        if (
            $time >= $start &&
            $time < $end
        ) {
            return true;
        }
    }

    return false;
}

function generate_slots(): array {
    $slots = [];

    $start =
        OPEN_HOUR * 60;

    $end =
        CLOSE_HOUR * 60;

    for (
        $minutes = $start;
        $minutes < $end;
        $minutes += SLOT_MINUTES
    ) {
        $hours =
            intdiv(
                $minutes,
                60
            );

        $minute =
            $minutes % 60;

        $slots[] =
            sprintf(
                '%02d:%02d',
                $hours,
                $minute
            );
    }

    return $slots;
}

function booking_exists(
    array $bookings,
    string $date,
    string $time
): bool {
    foreach ($bookings as $booking) {
        if (!is_array($booking)) {
            continue;
        }

        if (
            ($booking['date'] ?? '') ===
                $date &&
            ($booking['time'] ?? '') ===
                $time &&
            ($booking['status'] ?? 'confirmed') !==
                'cancelled'
        ) {
            return true;
        }
    }

    return false;
}

function get_available_slots(
    string $date,
    array $bookings,
    array $blocks
): array {
    $result = [];

    foreach (
        generate_slots()
        as $time
    ) {
        $available =
            !booking_exists(
                $bookings,
                $date,
                $time
            ) &&
            !slot_is_blocked(
                $date,
                $time,
                $blocks
            );

        $result[] = [
            'time' =>
                $time,
            'available' =>
                $available,
        ];
    }

    return $result;
}

function load_config(): array {
    if (!is_file(CONFIG_FILE)) {
        return [];
    }

    $config =
        require CONFIG_FILE;

    return is_array($config)
        ? $config
        : [];
}

function smtp_send_mail(
    array $config,
    string $to,
    string $subject,
    string $body,
    ?string $replyTo = null
): bool {
    $host =
        (string) (
            $config['smtp_host']
            ?? ''
        );

    $port =
        (int) (
            $config['smtp_port']
            ?? 465
        );

    $username =
        (string) (
            $config['smtp_username']
            ?? ''
        );

    $password =
        (string) (
            $config['smtp_password']
            ?? ''
        );

    $fromEmail =
        (string) (
            $config['from_email']
            ?? $username
        );

    $fromName =
        (string) (
            $config['from_name']
            ?? 'Vitrine+'
        );

    if (
        $host === '' ||
        $username === '' ||
        $password === '' ||
        $fromEmail === ''
    ) {
        return false;
    }

    $remote =
        $port === 465
            ? 'ssl://' . $host
            : $host;

    $socket =
        @fsockopen(
            $remote,
            $port,
            $errno,
            $errstr,
            15
        );

    if (!$socket) {
        return false;
    }

    stream_set_timeout(
        $socket,
        15
    );

    try {
        $read = function (
            array $expected
        ) use ($socket): void {
            $response =
                '';

            while (
                !feof($socket)
            ) {
                $line =
                    fgets(
                        $socket,
                        4096
                    );

                if ($line === false) {
                    break;
                }

                $response .=
                    $line;

                if (
                    strlen(
                        $line
                    ) >= 4 &&
                    $line[3] === ' '
                ) {
                    break;
                }
            }

            $code =
                (int) substr(
                    trim($response),
                    0,
                    3
                );

            if (
                !in_array(
                    $code,
                    $expected,
                    true
                )
            ) {
                throw new RuntimeException(
                    'SMTP'
                );
            }
        };

        $command =
            function (
                string $value,
                array $expected
            ) use (
                $socket,
                $read
            ): void {
                fwrite(
                    $socket,
                    $value . "\r\n"
                );

                $read(
                    $expected
                );
            };

        $read([220]);

        $hostname =
            $_SERVER[
                'SERVER_NAME'
            ] ?? 'vitrineplus.fr';

        $command(
            'EHLO ' . $hostname,
            [250]
        );

        if ($port !== 465) {
            $command(
                'STARTTLS',
                [220]
            );

            if (
                !stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )
            ) {
                throw new RuntimeException(
                    'TLS'
                );
            }

            $command(
                'EHLO ' . $hostname,
                [250]
            );
        }

        $command(
            'AUTH LOGIN',
            [334]
        );

        $command(
            base64_encode(
                $username
            ),
            [334]
        );

        $command(
            base64_encode(
                $password
            ),
            [235]
        );

        $command(
            'MAIL FROM:<' .
                $fromEmail .
                '>',
            [250]
        );

        $command(
            'RCPT TO:<' .
                $to .
                '>',
            [250, 251]
        );

        $command(
            'DATA',
            [354]
        );

        $safeSubject =
            preg_replace(
                "/[\r\n]+/",
                ' ',
                $subject
            );

        $safeFromName =
            preg_replace(
                "/[\r\n]+/",
                ' ',
                $fromName
            );

        $headers =
            'From: ' .
            $safeFromName .
            ' <' .
            $fromEmail .
            ">\r\n" .
            (
                $replyTo
                    ? 'Reply-To: ' .
                        $replyTo .
                        "\r\n"
                    : ''
            ) .
            'To: <' .
            $to .
            ">\r\n" .
            'Subject: =?UTF-8?B?' .
            base64_encode(
                (string)
                    $safeSubject
            ) .
            "?=\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n";

        $body =
            preg_replace(
                '/^\./m',
                '..',
                $body
            ) ?? $body;

        fwrite(
            $socket,
            $headers .
            $body .
            "\r\n.\r\n"
        );

        $read([250]);

        fwrite(
            $socket,
            "QUIT\r\n"
        );

        fclose($socket);

        return true;
    } catch (
        Throwable $error
    ) {
        fclose($socket);

        return false;
    }
}

$method =
    $_SERVER[
        'REQUEST_METHOD'
    ] ?? 'GET';

$bookings =
    read_json(
        BOOKINGS_FILE,
        []
    );

if (!is_array($bookings)) {
    $bookings = [];
}

$blocks =
    read_json(
        AVAILABILITY_FILE,
        []
    );

if (!is_array($blocks)) {
    $blocks = [];
}

if ($method === 'GET') {
    $action =
        clean(
            $_GET['action']
                ?? ''
        );

    if (
        $action !==
        'slots'
    ) {
        respond(
            [
                'success' =>
                    false,
                'message' =>
                    'Action inconnue.',
            ],
            400
        );
    }

    $date =
        clean(
            $_GET['date']
                ?? ''
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

    $slots =
        get_available_slots(
            $date,
            $bookings,
            $blocks
        );

    respond([
        'success' =>
            true,
        'date' =>
            $date,
        'slots' =>
            $slots,
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

if (
    !is_array($data)
) {
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
    $action !==
    'book'
) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Action inconnue.',
        ],
        400
    );
}

$date =
    clean(
        $data['date']
            ?? ''
    );

$time =
    clean(
        $data['time']
            ?? ''
    );

$name =
    clean(
        $data['name']
            ?? ''
    );

$company =
    clean(
        $data['company']
            ?? ''
    );

$phone =
    clean(
        $data['phone']
            ?? ''
    );

$email =
    clean(
        $data['email']
            ?? ''
    );

$reason =
    clean(
        $data['reason']
            ?? ''
    );

if (!valid_date($date)) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'La date sélectionnée est invalide.',
        ],
        400
    );
}

if (!valid_time($time)) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'L’heure sélectionnée est invalide.',
        ],
        400
    );
}

if ($name === '') {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Votre nom est obligatoire.',
        ],
        400
    );
}

if ($phone === '') {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Votre numéro de téléphone est obligatoire.',
        ],
        400
    );
}

if (
    $email !== '' &&
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'L’adresse e-mail est invalide.',
        ],
        400
    );
}

$today =
    date('Y-m-d');

if ($date < $today) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Cette date est déjà passée.',
        ],
        409
    );
}

if (
    slot_is_blocked(
        $date,
        $time,
        $blocks
    )
) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Ce créneau n’est plus disponible.',
        ],
        409
    );
}

if (
    booking_exists(
        $bookings,
        $date,
        $time
    )
) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Ce créneau vient d’être réservé. Choisissez-en un autre.',
        ],
        409
    );
}

$reference =
    'VP-' .
    date('YmdHis') .
    '-' .
    strtoupper(
        bin2hex(
            random_bytes(3)
        )
    );

$booking = [
    'reference' =>
        $reference,
    'created_at' =>
        date(DATE_ATOM),
    'date' =>
        $date,
    'time' =>
        $time,
    'duration' =>
        SLOT_MINUTES,
    'name' =>
        $name,
    'company' =>
        $company,
    'phone' =>
        $phone,
    'email' =>
        $email,
    'reason' =>
        $reason,
    'status' =>
        'confirmed',
];

$bookings[] =
    $booking;

if (
    !write_json(
        BOOKINGS_FILE,
        $bookings
    )
) {
    respond(
        [
            'success' =>
                false,
            'message' =>
                'Impossible d’enregistrer le rendez-vous.',
        ],
        500
    );
}

$config =
    load_config();

$adminEmail =
    (string) (
        $config[
            'admin_email'
        ]
        ??
        $config[
            'to_email'
        ]
        ??
        $config[
            'from_email'
        ]
        ??
        $config[
            'smtp_username'
        ]
        ??
        ''
    );

$adminBody =
    "NOUVEAU RENDEZ-VOUS VITRINE+\n\n" .
    "Nom : " .
    $name .
    "\n" .
    "Entreprise : " .
    (
        $company !== ''
            ? $company
            : 'Non renseignée'
    ) .
    "\n" .
    "Téléphone : " .
    $phone .
    "\n" .
    "E-mail : " .
    (
        $email !== ''
            ? $email
            : 'Non renseigné'
    ) .
    "\n" .
    "Date : " .
    $date .
    "\n" .
    "Heure : " .
    $time .
    "\n" .
    "Motif : " .
    (
        $reason !== ''
            ? $reason
            : 'Non renseigné'
    ) .
    "\n" .
    "Référence : " .
    $reference .
    "\n";

if (
    $adminEmail !== ''
) {
    smtp_send_mail(
        $config,
        $adminEmail,
        'Nouveau rendez-vous — Vitrine+',
        $adminBody,
        $email !== ''
            ? $email
            : null
    );
}

if (
    $email !== ''
) {
    $clientBody =
        "Bonjour " .
        $name .
        ",\n\n" .
        "Votre rendez-vous avec Vitrine+ est bien confirmé.\n\n" .
        "Date : " .
        $date .
        "\n" .
        "Heure : " .
        $time .
        "\n" .
        "Référence : " .
        $reference .
        "\n\n" .
        "Nous vous appellerons au numéro indiqué lors de votre réservation.\n\n" .
        "À bientôt,\n\n" .
        "Vitrine+\n" .
        "Votre entreprise. En mieux.\n\n" .
        "https://vitrineplus.fr/rendez-vous\n";

    smtp_send_mail(
        $config,
        $email,
        'Votre rendez-vous avec Vitrine+ est confirmé',
        $clientBody
    );
}

respond([
    'success' =>
        true,
    'reference' =>
        $reference,
    'booking' =>
        $booking,
]);