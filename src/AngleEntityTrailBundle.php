<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AngleEntityTrailBundle extends Bundle
{
    /**
     * The bundle class lives in src/, but config/ and templates/ sit at the
     * package root. Point getPath() there so Symfony registers the `@AngleEntityTrail`
     * Twig namespace from templates/ (otherwise the admin templates 500).
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register the bundle's own entity (EntityTrailLog) as an attribute-mapped entity
        // so projects don't have to touch their Doctrine config. Writes use raw DBAL,
        // but reads (repository / admin UI) need the entity to be mapped.
        $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
            ['Angle\\EntityTrailBundle\\Entity'],
            [__DIR__ . '/Entity'],
            [],
            false,
            [],
            true,
        ));
    }
}
