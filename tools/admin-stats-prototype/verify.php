<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
prototypeConfigureTestEnvironment();
require PROTOTYPE_PROJECT.'/tests/bootstrap.php';
require __DIR__.'/PrototypeStatsManager.php';
$kernel = new App\Kernel('test', false);
$kernel->boot();
$c = $kernel->getContainer()->get('test.service_container');
$db = $c->get(Doctrine\ORM\EntityManagerInterface::class)->getConnection();
foreach (['adminProbe', 'voterProbe', 'scale1', 'scale2', 'scale3'] as $username) {
    if (!$db->fetchOne('SELECT id FROM public."user" WHERE username=?', [$username])) {
        throw new RuntimeException('Missing local experiment fixture: '.$username);
    }
}
$original = new App\Service\InstanceStatsManager($c->get(App\Repository\UserRepository::class), $c->get(App\Repository\MagazineRepository::class), $c->get(App\Repository\StatsContentRepository::class), $c->get(App\Repository\VoteRepository::class), $c->get('cache.app'));
$prototype = new PrototypeStatsManager($db);
$report = ['cases' => [], 'timings' => []];
$verify = function (string $case) use ($original, $prototype, &$report): void {
    foreach ([null, '-7 days', '-30 days', '-365 days', '2026-09-30 12:34:56'] as $period) {
        foreach ([false, true] as $federated) {
            $expected = $original->count($period, $federated);
            $actual = $prototype->count($period, $federated);
            if ($actual !== $expected) {
                throw new RuntimeException(json_encode(compact('case', 'period', 'federated', 'expected', 'actual')));
            }
        }
    }
    $report['cases'][] = $case;
    echo "PASS $case (10 period/federation combinations)\n";
};
$verify('backfill');
foreach ([false, true] as $fed) {
    foreach ([null, '-7 days'] as $period) {
        foreach (['original' => $original, 'prototype' => $prototype] as $name => $manager) {
            $times = [];
            for ($i = 0; $i < 5; ++$i) {
                $start = hrtime(true);
                $manager->count($period, $fed);
                $times[] = round((hrtime(true) - $start) / 1e6, 3);
            }
            $report['timings'][] = ['implementation' => $name, 'period' => $period, 'federated' => $fed, 'ms' => $times];
        }
    }
}
$db->beginTransaction();
try {
    $actor = (int) $db->fetchOne("SELECT id FROM public.\"user\" WHERE username='scale2'");
    $subject = $db->fetchAssociative('SELECT id,magazine_id FROM entry e WHERE NOT EXISTS(SELECT 1 FROM favourite f WHERE f.entry_id=e.id AND f.user_id=?) ORDER BY id LIMIT 1', [$actor]);
    $id = (int) $db->fetchOne("INSERT INTO favourite(id,magazine_id,user_id,created_at,favourite_type,entry_id) VALUES(nextval('favourite_id_seq'),?,?,'2026-09-30 12:34:56+00','entry',?) RETURNING id", [$subject['magazine_id'], $actor, $subject['id']]);
    $verify('favourite inserted exactly on strict cutoff');
    $db->executeStatement("UPDATE favourite SET created_at='2026-09-30 12:34:57+00' WHERE id=?", [$id]);
    $verify('favourite moved across strict cutoff');
    $local = (int) $db->fetchOne("SELECT id FROM public.\"user\" WHERE username='adminProbe'");
    $db->executeStatement('UPDATE favourite SET user_id=? WHERE id=?', [$local, $id]);
    $verify('favourite moved to local voter');
    $db->executeStatement('DELETE FROM favourite WHERE id=?', [$id]);
    $verify('favourite removed');
    $db->executeStatement('DELETE FROM entry_comment_vote WHERE id=(SELECT MIN(id) FROM entry_comment_vote)');
    $verify('vote removed');
    $db->executeStatement("UPDATE public.\"user\" SET is_deleted=true WHERE username IN ('scale1','voterProbe')");
    $verify('soft-deleted local and remote voters/authors');
    $db->executeStatement("UPDATE public.\"user\" SET is_deleted=false WHERE username IN ('scale1','voterProbe')");
    $verify('users restored');
    $db->executeStatement("UPDATE public.\"user\" SET ap_id='voterProbe@remote.test' WHERE username='voterProbe'");
    $verify('local voter becomes federated');
    $db->executeStatement("UPDATE entry SET ap_id=NULL,created_at='2026-09-30 12:34:57+00' WHERE id=(SELECT MAX(id) FROM entry)");
    $verify('thread locality and date changed');
    $db->executeStatement("DELETE FROM public.\"user\" WHERE username='scale3'");
    $verify('hard-deleted user with cascading favourites');
} finally {
    $db->rollBack();
}
$verify('transaction rollback');
$report['source_rows'] = (int) $db->fetchOne('SELECT COUNT(*) FROM favourite');
$report['summary_rows'] = (int) $db->fetchOne('SELECT COUNT(*) FROM admin_perf.totals');
file_put_contents(PROTOTYPE_OUTPUT.'/verification.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT),"\n";
$kernel->shutdown();
