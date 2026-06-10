<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Unit\Attribute;

use Angle\TrailBundle\Attribute\TrailExclude;
use Angle\TrailBundle\Attribute\TrailIgnore;
use PHPUnit\Framework\TestCase;

final class AttributesTest extends TestCase
{
    public function testTrailExcludeIsReadableFromAClass(): void
    {
        $reflection = new \ReflectionClass(ExcludedFixture::class);

        self::assertCount(1, $reflection->getAttributes(TrailExclude::class));
    }

    public function testTrailExcludeIsAbsentOnAPlainClass(): void
    {
        $reflection = new \ReflectionClass(PlainFixture::class);

        self::assertCount(0, $reflection->getAttributes(TrailExclude::class));
    }

    public function testTrailIgnoreIsReadableFromAProperty(): void
    {
        $reflection = new \ReflectionClass(PlainFixture::class);

        self::assertCount(1, $reflection->getProperty('secret')->getAttributes(TrailIgnore::class));
        self::assertCount(0, $reflection->getProperty('name')->getAttributes(TrailIgnore::class));
    }

    public function testTrailExcludeTargetsClassesOnly(): void
    {
        $reflection = new \ReflectionClass(TrailExclude::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function testTrailIgnoreTargetsPropertiesOnly(): void
    {
        $reflection = new \ReflectionClass(TrailIgnore::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        self::assertSame(\Attribute::TARGET_PROPERTY, $attribute->flags);
    }
}

#[TrailExclude]
final class ExcludedFixture
{
}

final class PlainFixture
{
    public string $name = '';

    #[TrailIgnore]
    public string $secret = '';
}
