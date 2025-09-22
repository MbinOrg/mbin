<?php
declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

function bootstrapDatabase(): void
{
    $kernel = new Kernel('test', true);
    $kernel->boot();

    $application = new Application($kernel);
    $application->setAutoExit(false);

    $application->run(new ArrayInput([
        'command' => 'cache:pool:clear',
        '--all' => '1',
        '--no-interaction' => true,
    ]));

    $application->run(new ArrayInput([
        'command' => 'doctrine:database:drop',
        '--if-exists' => '1',
        '--force' => '1',
    ]));

    $application->run(new ArrayInput([
        'command' => 'doctrine:database:create',
    ]));

    $application->run(new ArrayInput([
        'command' => 'doctrine:migrations:migrate',
        '--no-interaction' => true,
    ]));

    $application->run(new ArrayInput([
        'command' => 'mbin:ap:keys:update',
        '--no-interaction' => true,
    ]));

    $application->run(new ArrayInput([
        'command' => 'mbin:push:keys:update',
        '--no-interaction' => true,
    ]));

    $conn = $kernel->getContainer()->get('doctrine.orm.entity_manager')->getConnection();
    if ($conn->isTransactionActive()) {
        $conn->commit();
    }
    if ($conn->isConnected()) {
        $conn->close();
    }

    $kernel->shutdown();
}

if (!empty($_SERVER['BOOTSTRAP_DB'])) {
    bootstrapDatabase();
}
