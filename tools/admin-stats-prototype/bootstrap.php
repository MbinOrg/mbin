<?php

declare(strict_types=1);

\define('PROTOTYPE_PROJECT', \dirname(__DIR__, 2));
\define('PROTOTYPE_OUTPUT', PROTOTYPE_PROJECT.'/var/admin-stats-prototype');
if (!is_dir(PROTOTYPE_OUTPUT)) {
    mkdir(PROTOTYPE_OUTPUT, 0770, true);
}

/** Refuse remote hosts and databases outside the isolated experiment. */
function prototypeDatabase(string $mode = 'prototype'): array
{
    $key = 'baseline' === $mode ? 'PROTOTYPE_BASELINE_DATABASE_URL' : 'PROTOTYPE_DATABASE_URL';
    $url = getenv($key);
    $parts = $url ? parse_url($url) : false;
    $expected = 'baseline' === $mode ? ['mbin_admin_perf'] : ['mbin_admin_rollup_test', 'mbin_admin_rollup_branch_test'];
    if (!$parts || !\in_array($parts['scheme'] ?? '', ['postgresql', 'postgres'], true)
        || !\in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || !\in_array(ltrim($parts['path'] ?? '', '/'), $expected, true) || ($parts['port'] ?? null) !== 5433) {
        throw new RuntimeException("$key must target an isolated admin experiment database on localhost:5433.");
    }

    return $parts;
}

function prototypeConnect(string $mode = 'prototype'): PDO
{
    $parts = prototypeDatabase($mode);

    return new PDO('pgsql:host='.$parts['host'].';port='.$parts['port'].';dbname='.substr($parts['path'], 1),
        rawurldecode($parts['user'] ?? ''), rawurldecode($parts['pass'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function prototypeEnvironment(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

function prototypeConfigureEnvironment(bool $test = false): void
{
    prototypeDatabase();
    $url = getenv('PROTOTYPE_DATABASE_URL');
    // Doctrine appends _test in the test environment.
    if ($test) {
        $parts = prototypeDatabase();
        $url = str_replace($parts['path'], substr($parts['path'], 0, -5), $url);
    }
    foreach ([
        'DATABASE_URL' => $url,
        'APP_ENV' => $test ? 'test' : 'dev',
        'APP_DEBUG' => '1',
        'REDIS_DNS' => 'redis://127.0.0.1:6380',
        'KBIN_DOMAIN' => $test ? 'https://kbin.test' : 'http://127.0.0.1:8002',
        'KBIN_STORAGE_URL' => $test ? 'https://kbin.test/media' : 'http://127.0.0.1:8002/media',
        'MESSENGER_TRANSPORT_DSN' => 'in-memory://',
        'MAILER_DSN' => 'null://default',
        'ELASTICSEARCH_ENABLED' => 'false',
        'KBIN_FEDERATION_ENABLED' => 'false',
        'BOOTSTRAP_DB' => '',
    ] as $key => $value) {
        prototypeEnvironment($key, $value);
    }
    chdir(PROTOTYPE_PROJECT);
}

function prototypeConfigureTestEnvironment(): void
{
    prototypeConfigureEnvironment(true);
}
