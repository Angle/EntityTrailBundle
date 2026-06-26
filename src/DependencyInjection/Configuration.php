<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('angle_entity_trail');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('track_creates')
                    ->info('Log postPersist (new records).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('track_updates')
                    ->info('Log preUpdate/postUpdate.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('track_deletes')
                    ->info('Log preRemove (before delete).')
                    ->defaultTrue()
                ->end()
                ->arrayNode('exclude_entities')
                    ->info('FQCN list — skip these entities entirely.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('exclude_fields')
                    ->info('Field names excluded from ALL entities.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['updatedAt', 'createdAt', 'deletedAt'])
                ->end()
                ->booleanNode('enable_admin')
                    ->info('Register admin list/view/data controllers.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('admin_route_prefix')
                    ->info('Prefix used when projects import the bundle routes.')
                    ->defaultValue('/admin/trail-log')
                ->end()
                ->scalarNode('user_provider')
                    ->info('Service id overriding the default SecurityEntityTrailUserProvider. null = use default.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
