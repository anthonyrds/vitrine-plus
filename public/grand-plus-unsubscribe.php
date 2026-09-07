<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';
const PARTICIPATIONS_FILE = DATA_DIR . '/participations.json';
const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function read_json_file(
    string $file,
    mixed $default
): mixed {
    if (!file_exists($file)) {
        return $default;
    }

    $content = @file_get_contents($file);

    if (
        $content === false ||
        trim($content) === ''
    ) {
        return $default;
    }

    $decoded = json_decode(
        $content,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE
    ) {
        return $default;
    }

    return $decoded;
}

function write_json_file(
    string $file,
    mixed $data
): bool {
    $json = json_encode(
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

function load_secret(): string
{
    if (!file_exists(CONFIG_FILE)) {
        return '';
    }

    $config = require CONFIG_FILE;

    if (!is_array($config)) {
        return '';
    }

    return (string) (
        $config['grand_plus_unsubscribe_secret']
        ?? ''
    );
}

$email = trim(
    (string) (
        $_GET['email']
        ?? ''
    )
);

$token = trim(
    (string) (
        $_GET['token']
        ?? ''
    )
);

$secret = load_secret();

$valid = false;

if (
    $email !== '' &&
    filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) &&
    $token !== '' &&
    $secret !== ''
) {
    $expected = hash_hmac(
        'sha256',
        strtolower($email),
        $secret
    );

    $valid = hash_equals(
        $expected,
        $token
    );
}

$message = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $valid
) {
    $participations = read_json_file(
        PARTICIPATIONS_FILE,
        []
    );

    if (!is_array($participations)) {
        $participations = [];
    }

    $changed = false;

    foreach (
        $participations as $index => $participant
    ) {
        if (!is_array($participant)) {
            continue;
        }

        $participantEmail =
            strtolower(
                trim(
                    (string) (
                        $participant['email']
                        ?? ''
                    )
                )
            );

        if (
            $participantEmail ===
            strtolower($email)
        ) {
            if (
                !empty(
                    $participant['marketing_consent']
                )
            ) {
                $participations[$index][
                    'marketing_consent'
                ] = false;

                $participations[$index][
                    'marketing_unsubscribed_at'
                ] = date('c');

                $changed = true;
            }
        }
    }

    if (
        $changed &&
        write_json_file(
            PARTICIPATIONS_FILE,
            $participations
        )
    ) {
        $message =
            'Votre désinscription a bien été enregistrée.';
    } else {
        $message =
            'Votre demande a été prise en compte.';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Désinscription — Vitrine+</title>

<style>

body {
    margin: 0;
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background: #080808;
    color: white;
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Helvetica Neue",
        Arial,
        sans-serif;
}

.card {
    width: min(560px, 100%);
    padding: 40px;
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 28px;
    background: #111;
    text-align: center;
}

.logo {
    font-weight: 900;
    font-size: 22px;
}

.logo span {
    color: #c8a45d;
}

h1 {
    margin: 30px 0 12px;
    font-size: 38px;
    letter-spacing: -.05em;
}

p {
    color: rgba(255,255,255,.55);
    line-height: 1.7;
}

.button {
    display: inline-flex;
    margin-top: 18px;
    padding: 13px 20px;
    border-radius: 999px;
    background: white;
    color: #080808;
    text-decoration: none;
    font-weight: 800;
}

.error {
    color: #ef8f8f;
}

</style>

</head>

<body>

<div class="card">

    <div class="logo">
        Vitrine<span>+</span>
    </div>

    <?php if (!$valid): ?>

        <h1>
            Lien invalide
        </h1>

        <p class="error">
            Ce lien de désinscription est invalide ou a expiré.
        </p>

    <?php elseif ($message !== ''): ?>

        <h1>
            C’est fait.
        </h1>

        <p>
            <?= h($message) ?>
        </p>

        <a
            href="https://vitrineplus.fr/"
            class="button"
        >
            Retour sur Vitrine+
        </a>

    <?php else: ?>

        <h1>
            Se désinscrire
        </h1>

        <p>
            Vous êtes sur le point de ne plus recevoir
            les communications commerciales de Vitrine+.
        </p>

        <form method="post">

            <button
                type="submit"
                class="button"
            >
                Confirmer ma désinscription
            </button>

        </form>

    <?php endif; ?>

</div>

</body>

</html>