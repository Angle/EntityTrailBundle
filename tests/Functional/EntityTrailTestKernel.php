<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Functional;

use Angle\EntityTrailBundle\AngleEntityTrailBundle;
use Angle\EntityTrailBundle\Tests\Functional\Fixtures\StaticEntityTrailUserProvider;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal kernel that boots FrameworkBundle + DoctrineBundle + AngleEntityTrailBundle
 * against an in-memory SQLite database.
 *
 * The admin UI is disabled and the user provider is overridden with a static test
 * implementation, so neither twig-bundle nor security-bundle needs to be registered:
 * the unused SecurityEntityTrailUserProvider/controllers are compiled away. No router is
 * configured — the test drives the container and EM directly, not HTTP.
 */
final class EntityTrailTestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $trailConfig
     */
    public function __construct(private readonly array $trailConfig = [])
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new AngleEntityTrailBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret'                => 'test',
                'test'                  => true,
                'http_method_override'  => false,
                'handle_all_throwables' => true,
                'php_errors'            => ['log' => true],
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url'    => 'sqlite:///:memory:',
                ],
                'orm' => [
                    'enable_native_lazy_objects'   => true,
                    'report_fields_where_declared' => true,
                    'mappings' => [
                        'EntityTrailFixtures' => [
                            'type'      => 'attribute',
                            'dir'       => __DIR__ . '/Fixtures/Entity',
                            'prefix'    => 'Angle\\EntityTrailBundle\\Tests\\Functional\\Fixtures\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('angle_entity_trail', array_merge([
                'enable_admin'  => false,
                'user_provider' => StaticEntityTrailUserProvider::class,
            ], $this->trailConfig));

            $container->register(StaticEntityTrailUserProvider::class, StaticEntityTrailUserProvider::class)
                ->setPublic(true);

            // Make the EM fetchable from the test container.
            $container->setAlias('test.em', 'doctrine.orm.default_entity_manager')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/angle_entity_trail_test/cache/' . md5(serialize($this->trailConfig));
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/angle_entity_trail_test/log';
    }
}
