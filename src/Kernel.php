<?php

declare(strict_types=1);

namespace App;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    // Kernel can be empty according to: https://github.com/symfony/recipes/pull/1006
    // But this will break your routing, so we keep configureRoutes()
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $projectDir = $this->getProjectDir();
        $routes->import($projectDir.'/config/{routes}/'.$this->environment.'/*.yaml');
        $routes->import($projectDir.'/config/{mbin_routes}/*.yaml');
        $routes->import($projectDir.'/config/{routes}/*.yaml');

        if (is_file($projectDir.'/config/routes.yaml')) {
            $routes->import($projectDir.'/config/routes.yaml');
        } else {
            $routes->import($projectDir.'/config/{routes}.php');
        }
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('doctrine.orm.default_configuration')
                    ->addMethodCall(
                        'setIdentityGenerationPreferences',
                        [
                            [
                                PostgreSQLPlatform::class => ClassMetadata::GENERATOR_TYPE_SEQUENCE,
                            ],
                        ]
                    );
            }
        });
    }
}
