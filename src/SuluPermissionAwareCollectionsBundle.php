<?php

declare(strict_types=1);

use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SuluPermissionAwareCollectionsBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasParameter('sulu.context') !== true) {
            return;
        }
        if($builder->getParameter('sulu.context') !== SuluKernel::CONTEXT_ADMIN) {
            return;
        }
        $container->import(__DIR__ . '/../config/services_admin.yaml');
    }
}