<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
prototypeConfigureTestEnvironment();
require PROTOTYPE_PROJECT.'/tests/bootstrap.php';
require __DIR__.'/PrototypeStatsManager.php';
$report = [];
foreach (['original', 'prototype'] as $implementation) {
    $kernel = new App\Kernel('test', true);
    $kernel->boot();
    $client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
    $client->disableReboot();
    $c = $client->getContainer();
    if ('prototype' === $implementation) {
        $db = $c->get(Doctrine\ORM\EntityManagerInterface::class)->getConnection();
        $c->set(App\Service\InstanceStatsManager::class, new PrototypeStatsManager($db));
    }
    $admin = $c->get(App\Repository\UserRepository::class)->findOneBy(['username' => 'adminProbe']);
    $client->loginUser($admin);
    $client->setServerParameter('HTTP_HOST', 'kbin.test');
    foreach (['/admin', '/admin/-1/1', '/admin/-1/1', '/admin/7/1'] as $path) {
        $client->enableProfiler();
        $start = hrtime(true);
        $crawler = $client->request('GET', $path);
        $ms = (hrtime(true) - $start) / 1e6;
        if (200 !== $client->getResponse()->getStatusCode()) {
            throw new RuntimeException($client->getResponse()->getContent());
        }
        $values = $crawler->filter('.stats-count p')->each(fn ($n) => (int) $n->text());
        if (6 !== \count($values)) {
            throw new RuntimeException('Dashboard not rendered');
        }
        $profile = $client->getProfile();
        $collector = $profile->getCollector('db');
        $queries = [];
        foreach ($collector->getQueries() as $group) {
            foreach ($group as $q) {
                $queries[] = $q['sql'];
            }
        }
        $row = ['implementation' => $implementation, 'path' => $path, 'status' => 200, 'elapsed_ms' => round($ms, 3), 'sql_ms' => round($collector->getTime() * 1000, 3), 'query_count' => $collector->getQueryCount(), 'values' => $values, 'statistics_queries' => array_values(array_filter($queries, fn ($q) => str_contains($q, 'COUNT(e.id)') || str_contains($q, 'admin_perf.')))];
        $report[] = $row;
        echo json_encode($row),"\n";
    }
    $kernel->shutdown();
    unset($client,$kernel,$c);
}
for ($i = 0; $i < 4; ++$i) {
    if ($report[$i]['values'] !== $report[$i + 4]['values']) {
        throw new RuntimeException('Rendered dashboard counts differ');
    }
}
file_put_contents(PROTOTYPE_OUTPUT.'/page-results.json', json_encode($report, JSON_PRETTY_PRINT));
