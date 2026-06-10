<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Functional;

use Angle\TrailBundle\AngleTrailBundle;
use Angle\TrailBundle\Tests\Functional\Fixtures\StaticTrailUserProvider;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal kernel that boots FrameworkBundle + DoctrineBundle + AngleTrailBundle
 * against an in-memory SQLite database.
 *
 * The admin UI is disabled and the user provider is overridden with a static test
 * implementation, so neither twig-bundle nor security-bundle needs to be registered:
 * the unused SecurityTrailUserProvider/controllers are compiled away. No router is
 * configured — the test drives the container and EM directly, not HTTP.
 */
final class TrailTestKernel extends Kernel
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
            new AngleTrailBundle(),
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
                        'TrailFixtures' => [
                            'type'      => 'attribute',
                            'dir'       => __DIR__ . '/Fixtures/Entity',
                            'prefix'    => 'Angle\\TrailBundle\\Tests\\Functional\\Fixtures\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('angle_trail', array_merge([
                'enable_admin'  => false,
                'user_provider' => StaticTrailUserProvider::class,
            ], $this->trailConfig));

            $container->register(StaticTrailUserProvider::class, StaticTrailUserProvider::class)
                ->setPublic(true);

            // Make the EM fetchable from the test container.
            $container->setAlias('test.em', 'doctrine.orm.default_entity_manager')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/angle_trail_test/cache/' . md5(serialize($this->trailConfig));
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/angle_trail_test/log';
    }
}
