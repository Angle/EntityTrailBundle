<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\DependencyInjection;

use Angle\EntityTrailBundle\Controller\Ajax\EntityTrailLogController as AjaxEntityTrailLogController;
use Angle\EntityTrailBundle\Controller\EntityTrailLogController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class AngleEntityTrailExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Expose the resolved config to the listener and controllers.
        $container->setParameter('angle_entity_trail.config', $config);

        // Allow overriding the user provider with a project service id.
        if (!empty($config['user_provider'])) {
            $container->setAlias('angle_entity_trail.user_provider', $config['user_provider'])
                ->setPublic(true);
        }

        // Drop the admin controllers when the admin UI is disabled.
        if (!$config['enable_admin']) {
            $container->removeDefinition(EntityTrailLogController::class);
            $container->removeDefinition(AjaxEntityTrailLogController::class);
        }
    }

    public function getAlias(): string
    {
        return 'angle_entity_trail';
    }
}
