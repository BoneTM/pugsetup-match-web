<?php
require_once './vendor/autoload.php';

include_once './config.php';

\Carbon\Carbon::setLocale('zh');

$loader = new \Twig\Loader\FilesystemLoader('./templates');
$twig = new \Twig\Environment($loader);

global $db;

# connect to database
$pdo = new PDO("mysql:host=${db['host']};dbname=${db['dbname']}", $db['username'], $db['password']);

function render($template, $data)
{
    global $twig, $title, $copyright;
    echo $twig->render($template, array_merge($data, ['title' => $title, 'copyright' => $copyright]));
}

if (isset($_GET['id'])) {
    $p = $pdo->prepare('select * from stats_matches where match_id = :id');
    $p->execute(['id' => $_GET['id']]);
    $match = $p->fetch();

    $start = \Carbon\Carbon::parse($match['start_time']);
    $match['time'] = $start->diffForHumans();
    if ($match['end_time']) {
        $match['duration'] = \Carbon\Carbon::parse($match['end_time'])->longAbsoluteDiffForHumans($start);
    } else {
        $match['duration'] = '进行中';
    }

    $rows = $pdo->query('select * from stats_players where match_id = ' . $match['match_id'] . ' order by kills desc')->fetchAll();

    $players = [[], []];
    foreach ($rows as $row) {
        $row['kad'] = $row['kills'] . '/' . $row['assists'] . '/' . $row['deaths'];
        $row['h'] = round($row['headshot_kills'] / ($row['kills'] ?: 1), 2);
        $row['k'] = $row['k2'] + $row['k3'] + $row['k4'] + $row['k5'];
        $row['v'] = $row['v2'] + $row['v3'] + $row['v4'] + $row['v5'] + $row['v1'];
        $row['f'] = $row['firstkill_t'] + $row['firstkill_ct'];
        if ($row['team'] == 'team1') {
            $players[0][] = $row;
        } else {
            $players[1][] = $row;
        }
    }

    render('detail.twig', ['match' => $match, 'players' => $players]);
} else {
    $rows = $pdo->query('select * from stats_matches order by match_id desc limit 100')->fetchAll();

    $rows = array_map(function ($row) {
        $start = \Carbon\Carbon::parse($row['start_time']);
        $row['time'] = $start->diffForHumans();
        if ($row['end_time']) {
            $row['duration'] = \Carbon\Carbon::parse($row['end_time'])->longAbsoluteDiffForHumans($start);
        } else {
            $row['duration'] = '进行中';
        }
        return $row;
    }, $rows);

    render('index.twig', ['items' => $rows]);
}