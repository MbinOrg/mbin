<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$db = prototypeConnect();
$db->beginTransaction();
try {
    $db->exec("INSERT INTO admin_perf.all_time(shard,kind,is_local,cnt) SELECT s,k,l,195000000 FROM generate_series(0,63) s CROSS JOIN unnest(ARRAY['user','magazine','entry','entry_comment','post','post_comment','entry_vote','entry_comment_vote','post_vote','post_comment_vote','favourite']) k CROSS JOIN unnest(ARRAY[true,false]) l ON CONFLICT(shard,kind,is_local) DO UPDATE SET cnt=EXCLUDED.cnt");
    $rows = (int) $db->query('SELECT COUNT(*) FROM admin_perf.all_time')->fetchColumn();
    $times = [];
    for ($i = 0; $i < 10; ++$i) {
        $t = hrtime(true);
        $db->query('SELECT kind,SUM(cnt)::bigint FROM admin_perf.all_time GROUP BY kind')->fetchAll();
        $times[] = round((hrtime(true) - $t) / 1e6, 3);
    }
    $plan = json_decode($db->query('EXPLAIN(ANALYZE,BUFFERS,TIMING OFF,FORMAT JSON) SELECT kind,SUM(cnt)::bigint FROM admin_perf.all_time GROUP BY kind')->fetchColumn(), true)[0];
    file_put_contents(PROTOTYPE_OUTPUT.'/bounded-read.json', json_encode(['synthetic' => true, 'description' => 'Maximum 11 kinds x 2 locality flags x 64 shards; synthetic totals, not production data', 'rows' => $rows, 'ms' => $times, 'explain' => $plan], JSON_PRETTY_PRINT));
    echo json_encode(['rows' => $rows, 'ms' => $times]),"\n";
} finally {
    $db->rollBack();
}
