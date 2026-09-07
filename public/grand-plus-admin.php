<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE GRAND + — ADMINISTRATION
|--------------------------------------------------------------------------
|
| Administration privée des participations.
|
| Actions :
| - consulter les participations
| - désigner un gagnant
| - clôturer un mois
|
|--------------------------------------------------------------------------
*/

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const DATA_DIR = __DIR__ . '/vitrine-data/grand-plus';
const PARTICIPATIONS_FILE = DATA_DIR . '/participations.json';
const WINNER_FILE = __DIR__ . '/grand-plus-winner.json';
const CONFIG_FILE = __DIR__ . '/vitrine-mail-config.php';

const DEFAULT_TO_EMAIL = 'vitrineplus@hotmail.com';

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
|
| IMPORTANT :
| Le mot de passe n'est PAS stocké ici.
| Il sera ajouté dans vitrine-mail-config.php.
|
|--------------------------------------------------------------------------
*/

function load_admin_credentials(): array
{
    if (!file_exists(CONFIG_FILE)) {
        http_response_code(500);
        exit('Configuration Vitrine+ introuvable.');
    }

    $config = require CONFIG_FILE;

    if (!is_array($config)) {
        http_response_code(500);
        exit('Configuration Vitrine+ invalide.');
    }

    $username = trim((string) ($config['grand_plus_admin_user'] ?? ''));
    $password = (string) ($config['grand_plus_admin_password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(500);
        exit(
            'Les identifiants administrateur du Grand + ne sont pas configurés.'
        );
    }

    return [
        'username' => $username,
        'password' => $password,
    ];
}

function require_auth(): void
{
    $credentials = load_admin_credentials();

    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        !isset($_SERVER['PHP_AUTH_PW'])
    ) {
        header('WWW-Authenticate: Basic realm="Le Grand + — Administration"');
        http_response_code(401);
        exit('Authentification requise.');
    }

    $user = (string) $_SERVER['PHP_AUTH_USER'];
    $password = (string) $_SERVER['PHP_AUTH_PW'];

    if (
        !hash_equals($credentials['username'], $user) ||
        !hash_equals($credentials['password'], $password)
    ) {
        header('WWW-Authenticate: Basic realm="Le Grand + — Administration"');
        http_response_code(401);
        exit('Identifiants incorrects.');
    }
}

/*
|--------------------------------------------------------------------------
| UTILITAIRES
|--------------------------------------------------------------------------
*/

function read_json_file(string $file, mixed $default): mixed
{
    if (!file_exists($file)) {
        return $default;
    }

    $content = @file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return $default;
    }

    $decoded = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    return $decoded;
}

function write_json_file(string $file, mixed $data): bool
{
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

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function current_month_key(): string
{
    return date('Y-m');
}

function current_month_label(): string
{
    $months = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    $month = (int) date('n');
    $year = date('Y');

    return $months[$month] . ' ' . $year;
}

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

require_auth();

/*
|--------------------------------------------------------------------------
| LECTURE
|--------------------------------------------------------------------------
*/

$participations = read_json_file(
    PARTICIPATIONS_FILE,
    []
);

if (!is_array($participations)) {
    $participations = [];
}

/*
|--------------------------------------------------------------------------
| STATISTIQUES
|--------------------------------------------------------------------------
*/

$currentMonth = current_month_key();

$currentMonthParticipants = array_values(
    array_filter(
        $participations,
        static function ($item) use ($currentMonth): bool {
            return is_array($item)
                && ($item['month_key'] ?? '') === $currentMonth;
        }
    )
);

$total = count($currentMonthParticipants);

$pending = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? 'pending') === 'pending'
    )
);

$winner = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? '') === 'winner'
    )
);

$notWinner = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            ($item['status'] ?? '') === 'not_winner'
    )
);

$marketing = count(
    array_filter(
        $currentMonthParticipants,
        static fn ($item): bool =>
            !empty($item['marketing_consent'])
    )
);

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Le Grand + — Administration</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #080808;
            color: #f5f5f2;
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Helvetica Neue",
                Helvetica,
                Arial,
                sans-serif;
        }

        .container {
            width: min(1400px, calc(100% - 40px));
            margin: 0 auto;
            padding: 60px 0;
        }

        .eyebrow {
            color: #c8a45d;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .24em;
            text-transform: uppercase;
        }

        h1 {
            margin: 14px 0 0;
            font-size: clamp(42px, 7vw, 88px);
            line-height: .9;
            letter-spacing: -.06em;
        }

        .month {
            margin-top: 18px;
            color: rgba(255,255,255,.45);
            font-size: 16px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 50px;
        }

        .stat {
            padding: 24px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 22px;
            background: #111;
        }

        .stat-label {
            color: rgba(255,255,255,.35);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .stat-value {
            margin-top: 12px;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .table-wrap {
            margin-top: 50px;
            overflow-x: auto;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 24px;
            background: #111;
        }

        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            text-align: left;
            vertical-align: top;
        }

        th {
            color: rgba(255,255,255,.35);
            font-size: 10px;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .company {
            font-weight: 800;
        }

        .muted {
            margin-top: 4px;
            color: rgba(255,255,255,.4);
            font-size: 12px;
        }

        .problem {
            max-width: 280px;
            color: rgba(255,255,255,.55);
            line-height: 1.5;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .pending {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.6);
        }

        .winner {
            background: rgba(200,164,93,.16);
            color: #c8a45d;
        }

        .not-winner {
            background: rgba(255,255,255,.05);
            color: rgba(255,255,255,.35);
        }

        .yes {
            color: #c8a45d;
            font-weight: 800;
        }

        .no {
            color: rgba(255,255,255,.3);
        }

        .empty {
            padding: 60px;
            text-align: center;
            color: rgba(255,255,255,.4);
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 520px) {
            .container {
                width: min(100% - 24px, 1400px);
                padding: 35px 0;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
            }

            .stat {
                padding: 18px;
            }

            .stat-value {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="eyebrow">
        Vitrine+ — Administration
    </div>

    <h1>
        Le Grand +
    </h1>

    <div class="month">
        <?= h(current_month_label()) ?>
    </div>

    <div class="stats">

        <div class="stat">
            <div class="stat-label">Participants</div>
            <div class="stat-value">
                <?= $total ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-label">En attente</div>
            <div class="stat-value">
                <?= $pending ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-label">Gagnant</div>
            <div class="stat-value">
                <?= $winner ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-label">Non gagnants</div>
            <div class="stat-value">
                <?= $notWinner ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-label">Marketing</div>
            <div class="stat-value">
                <?= $marketing ?>
            </div>
        </div>

    </div>

    <div class="table-wrap">

        <?php if ($total === 0): ?>

            <div class="empty">
                Aucun participant pour <?= h(current_month_label()) ?>.
            </div>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Contact</th>
                        <th>Activité</th>
                        <th>Problématique</th>
                        <th>Marketing</th>
                        <th>Statut</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($currentMonthParticipants as $participant): ?>

                    <?php
                    $status = (string) (
                        $participant['status']
                        ?? 'pending'
                    );
                    ?>

                    <tr>

                        <td>
                            <div class="company">
                                <?= h((string) ($participant['company'] ?? '')) ?>
                            </div>

                            <div class="muted">
                                <?= h((string) ($participant['sector'] ?? '')) ?>
                            </div>
                        </td>

                        <td>
                            <div>
                                <?= h((string) ($participant['name'] ?? '')) ?>
                            </div>

                            <div class="muted">
                                <?= h((string) ($participant['email'] ?? '')) ?>
                            </div>

                            <div class="muted">
                                <?= h((string) ($participant['phone'] ?? '')) ?>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($participant['website'])): ?>

                                <a
                                    href="<?= h((string) $participant['website']) ?>"
                                    target="_blank"
                                    rel="noreferrer"
                                    style="color:#c8a45d;"
                                >
                                    Voir le site
                                </a>

                            <?php else: ?>

                                <span class="no">
                                    Aucun site
                                </span>

                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="problem">
                                <?= h((string) ($participant['problem'] ?? '')) ?>
                            </div>
                        </td>

                        <td>
                            <?php if (!empty($participant['marketing_consent'])): ?>

                                <span class="yes">
                                    Oui
                                </span>

                            <?php else: ?>

                                <span class="no">
                                    Non
                                </span>

                            <?php endif; ?>
                        </td>

                        <td>

                            <?php if ($status === 'winner'): ?>

                                <span class="badge winner">
                                    Gagnant
                                </span>

                            <?php elseif ($status === 'not_winner'): ?>

                                <span class="badge not-winner">
                                    Non gagnant
                                </span>

                            <?php else: ?>

                                <span class="badge pending">
                                    En attente
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>