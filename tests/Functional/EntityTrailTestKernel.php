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
            $framework = [
                'secret'               => 'test',
                'test'                 => true,
                'http_method_override' => false,
                'php_errors'           => ['log' => true],
            ];
            // Added in Symfony 6.4; unknown key on 5.4.
            if (Kernel::VERSION_ID >= 60400) {
                $framework['handle_all_throwables'] = true;
            }
            $container->loadFromExtension('framework', $framework);

            $orm = [
                'auto_generate_proxy_classes'  => true,
                'mappings' => [
                    'EntityTrailFixtures' => [
                        'type'      => 'attribute',
                        'dir'       => __DIR__ . '/Fixtures/Entity',
                        'prefix'    => 'Angle\\EntityTrailBundle\\Tests\\Functional\\Fixtures\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ];
            // Silences an ORM 2.14+/3 deprecation. PostPersistEventArgs exists exactly
            // from ORM 2.14, so it's a reliable feature marker for this option.
            if (class_exists(\Doctrine\ORM\Event\PostPersistEventArgs::class)) {
                $orm['report_fields_where_declared'] = true;
            }
            // On PHP 8.4 the Symfony VarExporter LazyGhostTrait is gone, so Doctrine
            // must use native PHP lazy objects. This doctrine-bundle key only exists on
            // recent releases — which are exactly what a PHP 8.4 install resolves to.
            if (\PHP_VERSION_ID >= 80400) {
                $orm['enable_native_lazy_objects'] = true;
            }

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url'    => 'sqlite:///:memory:',
                ],
                'orm' => $orm,
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
