<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
prototypeConfigureEnvironment();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ('/' !== $path && is_file(PROTOTYPE_PROJECT.'/public'.$path)) {
    return false;
}
require PROTOTYPE_PROJECT.'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(PROTOTYPE_PROJECT.'/.env');
require __DIR__.'/PrototypeStatsManager.php';

final class AdminPrototypeKernel extends App\Kernel
{
    public function getProjectDir(): string
    {
        return PROTOTYPE_PROJECT;
    }

    public function getCacheDir(): string
    {
        return PROTOTYPE_OUTPUT.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return PROTOTYPE_OUTPUT.'/logs';
    }

    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                $container->getDefinition(App\Service\InstanceStatsManager::class)
                    ->setClass(PrototypeStatsManager::class)
                    ->setArguments([new Symfony\Component\DependencyInjection\Reference('doctrine.dbal.default_connection')]);
            }
        });
    }
}

$kernel = new AdminPrototypeKernel('dev', true);
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
