<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vitrine+ — Protection de /admin
|--------------------------------------------------------------------------
|
| Cette page sert de porte d'entrée au cockpit commercial.
| L'authentification est effectuée AVANT de servir l'application React.
|
|--------------------------------------------------------------------------
*/

const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

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

/*
|--------------------------------------------------------------------------
| Configuration absente
|--------------------------------------------------------------------------
*/

if (
    $expectedUser === '' ||
    $expectedPassword === ''
) {
    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=utf-8'
    );

    echo 'Configuration administrateur absente.';

    exit;
}

/*
|--------------------------------------------------------------------------
| Récupération Basic Auth
|--------------------------------------------------------------------------
*/

$user =
    $_SERVER['PHP_AUTH_USER']
    ?? '';

$password =
    $_SERVER['PHP_AUTH_PW']
    ?? '';

$authenticated =
    hash_equals(
        $expectedUser,
        (string) $user
    ) &&
    hash_equals(
        $expectedPassword,
        (string) $password
    );

/*
|--------------------------------------------------------------------------
| Refus
|--------------------------------------------------------------------------
*/

if (!$authenticated) {
    header(
        'WWW-Authenticate: Basic realm="Vitrine+ Administration"'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    http_response_code(401);

    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <title>Administration — Vitrine+</title>

        <style>
            * {
                box-sizing: border-box;
            }

            html,
            body {
                margin: 0;
                min-height: 100%;
            }

            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;

                background: #080808;
                color: #ffffff;

                font-family:
                    Inter,
                    -apple-system,
                    BlinkMacSystemFont,
                    "Segoe UI",
                    sans-serif;
            }

            .card {
                width: 100%;
                max-width: 420px;

                padding: 40px;

                border:
                    1px solid
                    rgba(255, 255, 255, 0.1);

                border-radius: 28px;

                background: #111111;

                text-align: center;

                box-shadow:
                    0 30px 100px
                    rgba(0, 0, 0, 0.45);
            }

            .logo {
                font-size: 28px;
                font-weight: 900;
                letter-spacing: -0.06em;
            }

            .logo span {
                color: #c8a45d;
            }

            .label {
                margin-top: 28px;

                color: #c8a45d;

                font-size: 10px;
                font-weight: 800;

                letter-spacing: 0.24em;
                text-transform: uppercase;
            }

            h1 {
                margin: 10px 0 0;

                font-size: 28px;
                line-height: 1;

                letter-spacing: -0.05em;
            }

            p {
                margin: 16px 0 0;

                color: rgba(255, 255, 255, 0.45);

                font-size: 14px;
                line-height: 1.7;
            }
        </style>
    </head>

    <body>
        <div class="card">
            <div class="logo">
                Vitrine<span>+</span>
            </div>

            <div class="label">
                Administration
            </div>

            <h1>
                Accès protégé
            </h1>

            <p>
                Identifiant ou mot de passe incorrect.
            </p>
        </div>
    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| Authentification réussie
|--------------------------------------------------------------------------
|
| On sert directement l'index React.
| L'URL reste /admin.
|
|--------------------------------------------------------------------------
*/

$indexFile = __DIR__ . '/index.html';

if (!is_file($indexFile)) {
    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=utf-8'
    );

    echo 'Application Vitrine+ introuvable.';

    exit;
}

header(
    'Content-Type: text/html; charset=utf-8'
);

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'
);

readfile($indexFile);

exit;