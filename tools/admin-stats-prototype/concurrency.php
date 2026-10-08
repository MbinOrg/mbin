<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
\define('ROOT', PROTOTYPE_OUTPUT.'/');
function connect(string $name): PDO
{
    return prototypeConnect($name);
}
$report = [];
foreach (['baseline' => 'baseline', 'prototype' => 'prototype'] as $mode => $name) {
    $db = connect($name);
    $jobs = [];
    for ($i = 0; $i < 4; ++$i) {
        $actor = (int) $db->query("SELECT id FROM public.\"user\" WHERE username='scale".(10 + $i)."'")->fetchColumn();
        $entry = $db->query('SELECT id,magazine_id FROM entry ORDER BY id LIMIT 1 OFFSET '.(100 + $i))->fetch(PDO::FETCH_ASSOC);
        if (!$actor || !$entry) {
            throw new RuntimeException('Missing synthetic concurrency fixtures');
        }
        $jobs[] = [$actor, (int) $entry['id'], (int) $entry['magazine_id']];
    }
    $before = (int) $db->query('SELECT COUNT(*) FROM favourite')->fetchColumn();
    $db = null;
    $start = hrtime(true);
    $children = [];
    foreach ($jobs as $i => [$actor,$entry,$magazine]) {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new RuntimeException('fork failed');
        }
        if (0 === $pid) {
            try {
                $db = connect($name);
                $db->exec("SET statement_timeout='5s'");
                $db->exec("SET lock_timeout='3s'");
                $insert = $db->prepare("INSERT INTO favourite(id,magazine_id,user_id,created_at,favourite_type,entry_id) VALUES(nextval('favourite_id_seq'),?,?,NOW(),'entry',?) RETURNING id");
                $delete = $db->prepare('DELETE FROM favourite WHERE id=?');
                $ms = [];
                $actorBefore = 'prototype' === $mode ? (int) $db->query("SELECT COALESCE(SUM(cnt),0) FROM admin_perf.by_actor WHERE actor_id=$actor AND kind='favourite'")->fetchColumn() : null;
                for ($j = 0; $j < 100; ++$j) {
                    $t = hrtime(true);
                    $db->beginTransaction();
                    $insert->execute([$magazine, $actor, $entry]);
                    $id = $insert->fetchColumn();
                    if ('prototype' === $mode && 0 === $j) {
                        $actorAfter = (int) $db->query("SELECT COALESCE(SUM(cnt),0) FROM admin_perf.by_actor WHERE actor_id=$actor AND kind='favourite'")->fetchColumn();
                        if ($actorAfter !== $actorBefore + 1) {
                            throw new RuntimeException('Insert was not counted');
                        }
                    }
                    $delete->execute([$id]);
                    $db->commit();
                    $ms[] = round((hrtime(true) - $t) / 1e6, 3);
                }
                file_put_contents(ROOT.$mode.'-worker-'.$i.'.json', json_encode(['pairs' => 100, 'ms' => $ms]));
                exit(0);
            } catch (Throwable $e) {
                file_put_contents(ROOT.$mode.'-worker-'.$i.'.json', json_encode(['error' => $e->getMessage()]));
                exit(1);
            }
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        if (0 !== pcntl_wexitstatus($status)) {
            throw new RuntimeException('Worker failed');
        }
    }
    $elapsed = (hrtime(true) - $start) / 1e6;
    $times = [];
    for ($i = 0; $i < 4; ++$i) {
        $times = array_merge($times, json_decode(file_get_contents(ROOT.$mode.'-worker-'.$i.'.json'), true)['ms']);
    }
    sort($times);
    $db = connect($name);
    $after = (int) $db->query('SELECT COUNT(*) FROM favourite')->fetchColumn();
    if ($before !== $after) {
        throw new RuntimeException('Row drift');
    }
    $row = ['mode' => $mode, 'workers' => 4, 'insert_delete_pairs' => 400, 'elapsed_ms' => round($elapsed, 3), 'pairs_per_second' => round(400000 / $elapsed, 1), 'pair_median_ms' => $times[200], 'pair_p95_ms' => $times[380], 'rows_before' => $before, 'rows_after' => $after];
    if ('prototype' === $mode) {
        $expected = (int) $db->query('SELECT COUNT(*) FROM favourite f JOIN public."user" u ON u.id=f.user_id WHERE NOT u.is_deleted')->fetchColumn();
        $actual = (int) $db->query("SELECT SUM(cnt) FROM admin_perf.totals WHERE kind='favourite'")->fetchColumn();
        if ($actual !== $expected) {
            throw new RuntimeException('Counter drift');
        }$row['counter_matches'] = true;
    }
    $report[] = $row;
    $db = null;
    echo json_encode($row),"\n";
}
file_put_contents(ROOT.'concurrency-results.json', json_encode($report, JSON_PRETTY_PRINT));
