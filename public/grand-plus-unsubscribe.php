<?php

declare(strict_types=1);

header(
    'Content-Type: text/html; charset=utf-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);


const DATA_DIR =
    __DIR__ . '/vitrine-data/grand-plus';

const PARTICIPATIONS_FILE =
    DATA_DIR . '/participations.json';

const UNSUBSCRIBE_FILE =
    DATA_DIR . '/unsubscribed.json';

const CONFIG_FILE =
    __DIR__ . '/vitrine-mail-config.php';

const DEFAULT_SITE_URL =
    'https://vitrineplus.fr';


/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| JSON
|--------------------------------------------------------------------------
*/

function read_json_file(
    string $file,
    mixed $default
): mixed {

    if (!file_exists($file)) {
        return $default;
    }

    $content =
        @file_get_contents($file);

    if (
        $content === false ||
        trim($content) === ''
    ) {
        return $default;
    }

    $decoded =
        json_decode(
            $content,
            true
        );

    if (
        json_last_error() !==
        JSON_ERROR_NONE
    ) {
        return $default;
    }

    return $decoded;
}


function write_json_file(
    string $file,
    mixed $data
): bool {

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
| SECRET
|--------------------------------------------------------------------------
*/

function load_secret(): string
{
    if (!file_exists(CONFIG_FILE)) {
        return '';
    }

    $config =
        require CONFIG_FILE;

    if (!is_array($config)) {
        return '';
    }

    return trim(
        (string) (
            $config[
                'grand_plus_unsubscribe_secret'
            ]
            ?? ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| TOKEN
|--------------------------------------------------------------------------
*/

function token_for_email(
    string $email
): string {

    $secret =
        load_secret();

    if ($secret === '') {
        return '';
    }

    return hash_hmac(
        'sha256',
        strtolower(
            trim($email)
        ),
        $secret
    );
}


/*
|--------------------------------------------------------------------------
| DONNÉES DU LIEN
|--------------------------------------------------------------------------
*/

$email =
    trim(
        (string) (
            $_GET['email']
            ?? ''
        )
    );

$token =
    trim(
        (string) (
            $_GET['token']
            ?? ''
        )
    );


$validEmail =
    filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;


$expectedToken =
    $validEmail
        ? token_for_email($email)
        : '';


$validToken =
    $validEmail &&
    $expectedToken !== '' &&
    $token !== '' &&
    hash_equals(
        $expectedToken,
        $token
    );


/*
|--------------------------------------------------------------------------
| TRAITEMENT
|--------------------------------------------------------------------------
*/

if (!$validToken) {

    http_response_code(400);

    $title =
        'Lien invalide';

    $message =
        'Ce lien de désinscription est invalide ou incomplet.';

} else {

    if (!is_dir(DATA_DIR)) {

        @mkdir(
            DATA_DIR,
            0750,
            true
        );
    }


    /*
     * ENREGISTREMENT DE LA DÉSINSCRIPTION
     */

    $unsubscribed =
        read_json_file(
            UNSUBSCRIBE_FILE,
            []
        );

    if (!is_array($unsubscribed)) {
        $unsubscribed = [];
    }


    $normalizedEmail =
        strtolower($email);


    $unsubscribed[
        $normalizedEmail
    ] = [

        'email' =>
            $normalizedEmail,

        'unsubscribed_at' =>
            date('c'),

    ];


    $saved =
        write_json_file(
            UNSUBSCRIBE_FILE,
            $unsubscribed
        );


    if (!$saved) {

        http_response_code(500);

        $title =
            'Impossible de traiter la demande';

        $message =
            'Une erreur temporaire est survenue. Veuillez réessayer plus tard.';

    } else {


        /*
         * MISE À JOUR DES PARTICIPATIONS EXISTANTES
         */

        $participations =
            read_json_file(
                PARTICIPATIONS_FILE,
                []
            );


        if (is_array($participations)) {

            foreach (
                $participations
                as $index =>
                $participant
            ) {

                if (
                    !is_array(
                        $participant
                    )
                ) {
                    continue;
                }


                $participantEmail =
                    strtolower(
                        trim(
                            (string) (
                                $participant[
                                    'email'
                                ]
                                ?? ''
                            )
                        )
                    );


                if (
                    $participantEmail ===
                    $normalizedEmail
                ) {

                    $participations[
                        $index
                    ][
                        'marketing_consent'
                    ] =
                        false;

                    $participations[
                        $index
                    ][
                        'marketing_unsubscribed_at'
                    ] =
                        date('c');
                }
            }


            write_json_file(
                PARTICIPATIONS_FILE,
                $participations
            );
        }


        $title =
            'Désinscription confirmée';

        $message =
            'Votre demande a bien été enregistrée. Vous ne recevrez plus les offres et actualités commerciales de Vitrine+ à cette adresse.';
    }
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= h($title) ?>
    — Vitrine+
</title>

<style>

body {
    margin: 0;

    min-height:
        100vh;

    display:
        grid;

    place-items:
        center;

    background:
        #080808;

    color:
        #fff;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Helvetica Neue",
        Arial,
        sans-serif;

    padding:
        24px;
}

main {
    width:
        min(
            620px,
            100%
        );

    padding:
        42px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .1
        );

    border-radius:
        28px;

    background:
        #111;
}

.brand {
    color:
        #c8a45d;

    font-size:
        11px;

    font-weight:
        800;

    letter-spacing:
        .2em;

    text-transform:
        uppercase;
}

h1 {
    margin:
        18px 0 0;

    font-size:
        clamp(
            36px,
            7vw,
            60px
        );

    line-height:
        .95;

    letter-spacing:
        -.05em;
}

p {
    color:
        rgba(
            255,
            255,
            255,
            .55
        );

    line-height:
        1.7;
}

a {
    display:
        inline-block;

    margin-top:
        18px;

    color:
        #c8a45d;

    font-weight:
        800;

    text-decoration:
        none;
}

</style>

</head>

<body>

<main>

    <div class="brand">
        Vitrine+
    </div>

    <h1>
        <?= h($title) ?>
    </h1>

    <p>
        <?= h($message) ?>
    </p>

    <a
        href="<?= h(DEFAULT_SITE_URL) ?>"
    >
        Retour sur vitrineplus.fr →
    </a>

</main>

</body>

</html>