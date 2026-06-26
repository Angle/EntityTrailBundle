<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Unit\Attribute;

use Angle\EntityTrailBundle\Attribute\EntityTrailExclude;
use Angle\EntityTrailBundle\Attribute\EntityTrailIgnore;
use PHPUnit\Framework\TestCase;

final class AttributesTest extends TestCase
{
    public function testEntityTrailExcludeIsReadableFromAClass(): void
    {
        $reflection = new \ReflectionClass(ExcludedFixture::class);

        self::assertCount(1, $reflection->getAttributes(EntityTrailExclude::class));
    }

    public function testEntityTrailExcludeIsAbsentOnAPlainClass(): void
    {
        $reflection = new \ReflectionClass(PlainFixture::class);

        self::assertCount(0, $reflection->getAttributes(EntityTrailExclude::class));
    }

    public function testEntityTrailIgnoreIsReadableFromAProperty(): void
    {
        $reflection = new \ReflectionClass(PlainFixture::class);

        self::assertCount(1, $reflection->getProperty('secret')->getAttributes(EntityTrailIgnore::class));
        self::assertCount(0, $reflection->getProperty('name')->getAttributes(EntityTrailIgnore::class));
    }

    public function testEntityTrailExcludeTargetsClassesOnly(): void
    {
        $reflection = new \ReflectionClass(EntityTrailExclude::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function testEntityTrailIgnoreTargetsPropertiesOnly(): void
    {
        $reflection = new \ReflectionClass(EntityTrailIgnore::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        self::assertSame(\Attribute::TARGET_PROPERTY, $attribute->flags);
    }
}

#[EntityTrailExclude]
final class ExcludedFixture
{
}

final class PlainFixture
{
    public string $name = '';

    #[EntityTrailIgnore]
    public string $secret = '';
}
