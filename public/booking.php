<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

const ADMIN_EMAIL = 'vitrineplus@hotmail.com';
const FROM_EMAIL = 'contact@vitrineplus.fr';
const TIMEZONE = 'Europe/Paris';
const OPEN_MINUTE = 9 * 60;
const CLOSE_MINUTE = 18 * 60;
const SLOT_STEP = 30;
const MAX_DAYS_AHEAD = 60;

function respond(array $data, int $status = 200): void {
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function dataDirectory(): string {
    return dirname(__DIR__) . '/vitrine-data';
}

function bookingsFile(): string {
    return dataDirectory() . '/bookings.json';
}

function clean(string $value, int $max = 1000): string {
    $value = trim(
        preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        ) ?? ''
    );

    if (function_exists('mb_substr')) {
        return mb_substr(
            $value,
            0,
            $max,
            'UTF-8'
        );
    }

    return substr($value, 0, $max);
}

function ensureStorage(): void {
    if (
        !is_dir(dataDirectory()) &&
        !@mkdir(dataDirectory(), 0755, true)
    ) {
        respond([
            'success' => false,
            'message' =>
                'Le stockage des rendez-vous est indisponible.'
        ], 500);
    }

    if (
        !file_exists(bookingsFile()) &&
        @file_put_contents(
            bookingsFile(),
            '[]',
            LOCK_EX
        ) === false
    ) {
        respond([
            'success' => false,
            'message' =>
                'Le stockage des rendez-vous est indisponible.'
        ], 500);
    }
}

function readBookings($handle): array {
    rewind($handle);

    $contents = stream_get_contents($handle);

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

    return is_array($data)
        ? $data
        : [];
}

function validDate(string $date): bool {
    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        return false;
    }

    $timezone =
        new DateTimeZone(TIMEZONE);

    $dateObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            $timezone
        );

    if (!$dateObject) {
        return false;
    }

    $today =
        new DateTimeImmutable(
            'today',
            $timezone
        );

    $maximum =
        $today->modify(
            '+' . MAX_DAYS_AHEAD . ' days'
        );

    return (
        $dateObject >= $today &&
        $dateObject <= $maximum
    );
}

function validTime(string $time): bool {
    return (bool) preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $time
    );
}

function minutes(string $time): int {
    [
        $hours,
        $minutes
    ] = array_map(
        'intval',
        explode(':', $time)
    );

    return (
        $hours * 60
    ) + $minutes;
}

function overlaps(
    string $time,
    array $booking
): bool {
    if (
        ($booking['status'] ?? 'confirmed')
        !== 'confirmed'
    ) {
        return false;
    }

    $start =
        minutes($time);

    $end =
        $start + 30;

    $otherStart =
        minutes(
            (string) (
                $booking['time'] ?? '00:00'
            )
        );

    $otherEnd =
        $otherStart +
        (int) (
            $booking['duration'] ?? 30
        );

    return (
        $start < $otherEnd &&
        $otherStart < $end
    );
}

function getSlots(
    string $date,
    array $bookings
): array {
    $timezone =
        new DateTimeZone(TIMEZONE);

    $selected =
        new DateTimeImmutable(
            $date . ' 12:00:00',
            $timezone
        );

    if (
        (int) $selected->format('N') >= 6
    ) {
        return [];
    }

    $today =
        new DateTimeImmutable(
            'today',
            $timezone
        );

    $now =
        new DateTimeImmutable(
            'now',
            $timezone
        );

    $isToday =
        $date ===
        $today->format('Y-m-d');

    $slots = [];

    for (
        $minute = OPEN_MINUTE;
        $minute < CLOSE_MINUTE;
        $minute += SLOT_STEP
    ) {
        $time =
            sprintf(
                '%02d:%02d',
                intdiv($minute, 60),
                $minute % 60
            );

        $available = true;

        if ($isToday) {
            $slot =
                new DateTimeImmutable(
                    $date .
                    ' ' .
                    $time .
                    ':00',
                    $timezone
                );

            if (
                $slot <=
                $now->modify('+30 minutes')
            ) {
                $available = false;
            }
        }

        foreach (
            $bookings as $booking
        ) {
            if (
                ($booking['date'] ?? '')
                === $date &&
                overlaps(
                    $time,
                    $booking
                )
            ) {
                $available = false;
                break;
            }
        }

        $slots[] = [
            'time' => $time,
            'available' => $available
        ];
    }

    return $slots;
}

function generateReference(
    DateTimeImmutable $now
): string {
    return (
        'VP-' .
        $now->format('Ymd') .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(3)
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| GET — DISPONIBILITÉS
|--------------------------------------------------------------------------
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'GET'
) {
    if (
        clean(
            (string) (
                $_GET['action'] ?? ''
            )
        ) !== 'slots'
    ) {
        respond([
            'success' => false,
            'message' => 'Action inconnue.'
        ], 400);
    }

    $date =
        clean(
            (string) (
                $_GET['date'] ?? ''
            )
        );

    if (!validDate($date)) {
        respond([
            'success' => false,
            'message' => 'Date invalide.'
        ], 422);
    }

    ensureStorage();

    $handle =
        @fopen(
            bookingsFile(),
            'r'
        );

    if (!$handle) {
        respond([
            'success' => false,
            'message' =>
                'Impossible de lire les disponibilités.'
        ], 500);
    }

    $bookings =
        readBookings($handle);

    fclose($handle);

    respond([
        'success' => true,
        'date' => $date,
        'slots' =>
            getSlots(
                $date,
                $bookings
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| POST — RÉSERVATION
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
    ) !== 'book'
) {
    respond([
        'success' => false,
        'message' => 'Action inconnue.'
    ], 400);
}

/*
|--------------------------------------------------------------------------
| HONEYPOT
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $_POST['website'] ?? ''
    )
) {
    respond([
        'success' => true,
        'reference' => 'VP-SPAM'
    ]);
}

/*
|--------------------------------------------------------------------------
| DONNÉES
|--------------------------------------------------------------------------
*/

$name =
    clean(
        (string) (
            $_POST['name'] ?? ''
        ),
        120
    );

$phone =
    clean(
        (string) (
            $_POST['phone'] ?? ''
        ),
        60
    );

$company =
    clean(
        (string) (
            $_POST['company'] ?? ''
        ),
        160
    );

$reason =
    clean(
        (string) (
            $_POST['reason'] ?? ''
        ),
        2500
    );

$date =
    clean(
        (string) (
            $_POST['date'] ?? ''
        )
    );

$time =
    clean(
        (string) (
            $_POST['time'] ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $phone === '' ||
    $reason === '' ||
    !isset($_POST['consent'])
) {
    respond([
        'success' => false,
        'message' =>
            'Merci de compléter les informations obligatoires.'
    ], 422);
}

$digits =
    preg_replace(
        '/\D+/',
        '',
        $phone
    );

if (
    !is_string($digits) ||
    strlen($digits) < 8
) {
    respond([
        'success' => false,
        'message' =>
            'Veuillez indiquer un numéro de téléphone valide.'
    ], 422);
}

if (
    !validDate($date) ||
    !validTime($time)
) {
    respond([
        'success' => false,
        'message' =>
            'Le créneau sélectionné n’est pas valide.'
    ], 422);
}

$slotMinutes =
    minutes($time);

if (
    $slotMinutes % SLOT_STEP !== 0 ||
    $slotMinutes < OPEN_MINUTE ||
    $slotMinutes + 30 > CLOSE_MINUTE
) {
    respond([
        'success' => false,
        'message' =>
            'Ce créneau n’est pas disponible.'
    ], 422);
}

/*
|--------------------------------------------------------------------------
| TEMPS RÉEL
|--------------------------------------------------------------------------
*/

$timezone =
    new DateTimeZone(TIMEZONE);

$slotDateTime =
    new DateTimeImmutable(
        $date .
        ' ' .
        $time .
        ':00',
        $timezone
    );

$now =
    new DateTimeImmutable(
        'now',
        $timezone
    );

if (
    $slotDateTime <=
    $now->modify('+30 minutes')
) {
    respond([
        'success' => false,
        'message' =>
            'Ce créneau n’est plus disponible.'
    ], 409);
}

/*
|--------------------------------------------------------------------------
| VERROUILLAGE
|--------------------------------------------------------------------------
*/

ensureStorage();

$handle =
    @fopen(
        bookingsFile(),
        'c+'
    );

if (
    !$handle ||
    !flock(
        $handle,
        LOCK_EX
    )
) {
    respond([
        'success' => false,
        'message' =>
            'Impossible de sécuriser la réservation.'
    ], 500);
}

$bookings =
    readBookings($handle);

/*
|--------------------------------------------------------------------------
| DOUBLE RÉSERVATION
|--------------------------------------------------------------------------
*/

foreach (
    $bookings as $booking
) {
    if (
        ($booking['date'] ?? '')
        === $date &&
        overlaps(
            $time,
            $booking
        )
    ) {
        flock(
            $handle,
            LOCK_UN
        );

        fclose($handle);

        respond([
            'success' => false,
            'message' =>
                'Ce créneau vient d’être réservé. Choisissez-en un autre.'
        ], 409);
    }
}

/*
|--------------------------------------------------------------------------
| CRÉATION
|--------------------------------------------------------------------------
*/

$reference =
    generateReference($now);

$booking = [
    'reference' =>
        $reference,

    'created_at' =>
        $now->format(
            DateTimeInterface::ATOM
        ),

    'date' =>
        $date,

    'time' =>
        $time,

    'duration' =>
        30,

    'name' =>
        $name,

    'phone' =>
        $phone,

    'company' =>
        $company,

    'reason' =>
        $reason,

    'status' =>
        'confirmed'
];

$bookings[] =
    $booking;

/*
|--------------------------------------------------------------------------
| SAUVEGARDE
|--------------------------------------------------------------------------
*/

rewind($handle);

if (
    !ftruncate(
        $handle,
        0
    )
) {
    flock(
        $handle,
        LOCK_UN
    );

    fclose($handle);

    respond([
        'success' => false,
        'message' =>
            'Impossible d’enregistrer le rendez-vous.'
    ], 500);
}

$json =
    json_encode(
        $bookings,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

if (
    !is_string($json) ||
    fwrite(
        $handle,
        $json
    ) === false
) {
    flock(
        $handle,
        LOCK_UN
    );

    fclose($handle);

    respond([
        'success' => false,
        'message' =>
            'Impossible d’enregistrer le rendez-vous.'
    ], 500);
}

fflush($handle);

flock(
    $handle,
    LOCK_UN
);

fclose($handle);

/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

$dateLabel =
    $slotDateTime->format(
        'd/m/Y'
    );

$companyLabel =
    $company !== ''
        ? $company
        : '(Non renseignée)';

$subject =
    'Nouveau rendez-vous Vitrine+ — ' .
    $name;

$body =
    "NOUVEAU RENDEZ-VOUS VITRINE+\n" .
    "================================\n\n" .

    "Référence : {$reference}\n\n" .

    "RENDEZ-VOUS\n" .
    "Date : {$dateLabel}\n" .
    "Heure : {$time}\n" .
    "Durée : 30 minutes\n" .
    "Type : Appel téléphonique\n\n" .

    "CONTACT\n" .
    "Nom : {$name}\n" .
    "Téléphone : {$phone}\n" .
    "Entreprise : {$companyLabel}\n\n" .

    "MOTIF\n" .
    $reason .
    "\n";

$headers =
    "From: Vitrine+ <" .
    FROM_EMAIL .
    ">\r\n" .

    "Reply-To: " .
    FROM_EMAIL .
    "\r\n" .

    "MIME-Version: 1.0\r\n" .

    "Content-Type: text/plain; charset=UTF-8\r\n";

$mailSent =
    @mail(
        ADMIN_EMAIL,
        $subject,
        $body,
        $headers
    );

$response = [
    'success' =>
        true,

    'reference' =>
        $reference
];

if (!$mailSent) {
    $response['warning'] =
        'Le rendez-vous est enregistré. La notification email n’a pas pu être envoyée.';
}

respond($response);