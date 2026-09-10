<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const BASE_DIR = __DIR__;

const CONFIG_FILE =
    BASE_DIR . '/vitrine-mail-config.php';

const SOCIAL_DIR =
    BASE_DIR . '/vitrine-data/social';

const SOCIAL_FILE =
    SOCIAL_DIR . '/contents.json';


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
            'WWW-Authenticate: Basic realm="Vitrine+ Social Studio"'
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


function read_contents(): array
{
    if (!is_file(SOCIAL_FILE)) {
        return [];
    }

    $raw = @file_get_contents(
        SOCIAL_FILE
    );

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode(
        $raw,
        true
    );

    return is_array($data)
        ? $data
        : [];
}


function write_contents(
    array $contents
): void {
    if (!is_dir(SOCIAL_DIR)) {
        if (!mkdir(
            SOCIAL_DIR,
            0755,
            true
        )) {
            respond(
                [
                    'success' => false,
                    'message' =>
                        'Impossible de créer le dossier social.',
                ],
                500
            );
        }
    }

    $json = json_encode(
        array_values($contents),
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if (
        $json === false ||
        @file_put_contents(
            SOCIAL_FILE,
            $json,
            LOCK_EX
        ) === false
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Impossible d’enregistrer les contenus.',
            ],
            500
        );
    }
}


function request_json(): array
{
    $raw =
        file_get_contents('php://input');

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $data = json_decode(
        $raw,
        true
    );

    return is_array($data)
        ? $data
        : [];
}


function clean(
    mixed $value
): string {
    return trim(
        (string) $value
    );
}

function publishInstagramPost(string $imageUrl, string $caption): array
{
    $config = load_config();

    $token = $config["instagram_access_token"] ?? "";
    $account = $config["instagram_account_id"] ?? "";

    if ($token === "" || $account === "") {
        respond([
            "success" => false,
            "message" => "Instagram n'est pas configuré."
        ],500);
    }

    /* Création du média */

    $container = "https://graph.instagram.com/v23.0/".$account."/media";

    $postData = http_build_query([
        "image_url"=>$imageUrl,
        "caption"=>$caption,
        "access_token"=>$token
    ]);

    $ch = curl_init($container);

    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$postData,
        CURLOPT_RETURNTRANSFER=>true
    ]);

    $response = curl_exec($ch);

    curl_close($ch);

    $containerResult = json_decode($response,true);

    if(empty($containerResult["id"])){
        respond([
            "success"=>false,
            "message"=>"Impossible de créer le média Instagram.",
            "response"=>$containerResult
        ],500);
    }

    /* Publication */

    $publish = "https://graph.instagram.com/v23.0/".$account."/media_publish";

    $publishData = http_build_query([
        "creation_id"=>$containerResult["id"],
        "access_token"=>$token
    ]);

    $ch = curl_init($publish);

    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$publishData,
        CURLOPT_RETURNTRANSFER=>true
    ]);

    $response = curl_exec($ch);

    curl_close($ch);

    $publishResult = json_decode($response,true);

    return [
        "container"=>$containerResult,
        "publish"=>$publishResult
    ];
}



function generate_content(
    string $type,
    string $topic,
    string $objective
): array {
    $titles = [
        'post' =>
            'Votre présence en ligne mérite mieux.',
        'carousel' =>
            '3 erreurs qui font fuir vos visiteurs',
        'reel' =>
            'Votre site vous fait peut-être perdre des clients.',
        'story' =>
            'Votre site est-il vraiment efficace ?',
    ];

    $title =
        $titles[$type]
        ?? $titles['post'];

    $caption =
        "Votre entreprise mérite une présence en ligne "
        . "à la hauteur de ce que vous proposez.\n\n"
        . "Aujourd’hui, vos clients vous découvrent "
        . "avant même de vous contacter. Votre site, "
        . "votre image et votre expérience en ligne "
        . "doivent donc inspirer confiance immédiatement.\n\n"
        . "Chez Vitrine+, nous analysons votre présence "
        . "en ligne pour identifier ce qui peut être "
        . "amélioré.\n\n"
        . "🔎 Visibilité\n"
        . "🎨 Image\n"
        . "⚡ Performance\n"
        . "📱 Expérience\n\n"
        . "Découvrez votre score avec l’Audit Vitrine+.\n\n"
        . "Votre entreprise. En mieux.\n\n"
        . "#VitrinePlus #Entreprise #Digital "
        . "#MarketingDigital #SiteInternet #Entrepreneur";

    $slides = [];

    if ($type === 'carousel') {
        $slides = [
            'Votre site attire-t-il vraiment vos clients ?',
            '01 — Vous ne dites pas clairement ce que vous faites.',
            '02 — Votre site n’est pas pensé pour le mobile.',
            '03 — Vos visiteurs ne savent pas quoi faire ensuite.',
            'Un bon site ne doit pas seulement être beau.',
            'Il doit transformer les visiteurs en clients.',
            'Analysez votre présence avec l’Audit Vitrine+.',
        ];
    }

    if ($type === 'story') {
        $slides = [
            'VOUS AVEZ UN SITE ?',
            'MAIS EST-IL VRAIMENT EFFICACE ?',
            '🔎 Visibilité',
            '🎨 Image',
            '⚡ Performance',
            '📱 Expérience',
            'Obtenez votre score.',
        ];
    }

    if ($type === 'reel') {
        $slides = [
            'ATTENDEZ.',
            'Votre site vous fait peut-être perdre des clients.',
            'On analyse votre visibilité.',
            'On analyse votre image.',
            'On analyse votre expérience.',
            'Vous obtenez votre score.',
            'Audit Vitrine+',
        ];
    }

    return [
        'id' =>
            'social-' .
            date('YmdHis') .
            '-' .
            bin2hex(
                random_bytes(4)
            ),

        'type' => $type,

        'title' => $title,

        'topic' => $topic,

        'objective' => $objective,

        'caption' => $caption,

        'slides' => $slides,

        'status' => 'draft',

        'scheduled_at' => '',

        'created_at' =>
            date('c'),
    ];
}


require_auth();

$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';

if ($method === 'GET') {
    $contents = read_contents();

    usort(
        $contents,
        static function (
            array $a,
            array $b
        ): int {
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

    respond(
        [
            'success' => true,
            'contents' => $contents,
        ]
    );
}


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


$input = request_json();

$action =
    clean(
        $input['action']
        ?? ''
    );


if ($action === 'list') {
    $contents = read_contents();

    respond(
        [
            'success' => true,
            'contents' => $contents,
        ]
    );
}


if ($action === 'generate') {
    $type =
        clean(
            $input['type']
            ?? 'post'
        );

    $topic =
        clean(
            $input['topic']
            ?? ''
        );

    $objective =
        clean(
            $input['objective']
            ?? ''
        );

    if ($topic === '') {
        respond(
            [
                'success' => false,
                'message' =>
                    'Le sujet est obligatoire.',
            ],
            422
        );
    }

    $allowedTypes = [
        'post',
        'carousel',
        'reel',
        'story',
    ];

    if (
        !in_array(
            $type,
            $allowedTypes,
            true
        )
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Format de contenu invalide.',
            ],
            422
        );
    }

    $content =
        generate_content(
            $type,
            $topic,
            $objective
        );

    respond(
        [
            'success' => true,
            'content' => $content,
        ]
    );
}


if ($action === 'save') {
    $content =
        $input['content']
        ?? null;

    if (
        !is_array($content)
    ) {
        respond(
            [
                'success' => false,
                'message' =>
                    'Contenu invalide.',
            ],
            422
        );
    }

    $contents =
        read_contents();

    $contentId =
        clean(
            $content['id']
            ?? ''
        );

    if ($contentId === '') {
        $contentId =
            'social-' .
            date('YmdHis') .
            '-' .
            bin2hex(
                random_bytes(4)
            );

        $content['id'] =
            $contentId;
    }

    $content['status'] =
        clean(
            $input['status']
            ?? 'draft'
        );

    $content['scheduled_at'] =
        clean(
            $input['scheduled_at']
            ?? ''
        );

    $content['updated_at'] =
        date('c');

    $found = false;

    foreach (
        $contents
        as $index => $existing
    ) {
        if (
            ($existing['id'] ?? '')
            === $contentId
        ) {
            $contents[$index] =
                $content;

            $found = true;

            break;
        }
    }

    if (!$found) {
        $contents[] =
            $content;
    }

    write_contents(
        $contents
    );

    respond(
        [
            'success' => true,
            'content' => $content,
        ]
    );
}


if ($action === 'delete') {
    $id =
        clean(
            $input['id']
            ?? ''
        );

    if ($id === '') {
        respond(
            [
                'success' => false,
                'message' =>
                    'Identifiant manquant.',
            ],
            422
        );
    }

    $contents =
        read_contents();

    $contents = array_values(
        array_filter(
            $contents,
            static function (
                array $content
            ) use ($id): bool {
                return (
                    ($content['id'] ?? '')
                    !== $id
                );
            }
        )
    );

    write_contents(
        $contents
    );

    respond(
        [
            'success' => true,
        ]
    );
}

if($action==="publish"){

    $caption = clean($input["caption"] ?? "");

    $image = clean($input["image"] ?? "");

    if($caption==="" || $image===""){
        respond([
            "success"=>false,
            "message"=>"Image ou légende manquante."
        ],422);
    }

    $result = publishInstagramPost($image,$caption);

    respond([
        "success"=>true,
        "message"=>"Publication envoyée sur Instagram.",
        "result"=>$result
    ]);

}


respond(
    [
        'success' => false,
        'message' =>
            'Action inconnue.',
    ],
    400
);