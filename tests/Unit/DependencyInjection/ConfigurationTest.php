<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Unit\DependencyInjection;

use Angle\TrailBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs = []): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$configs]);
    }

    public function testDefaults(): void
    {
        $config = $this->process();

        self::assertTrue($config['track_creates']);
        self::assertTrue($config['track_updates']);
        self::assertTrue($config['track_deletes']);
        self::assertSame([], $config['exclude_entities']);
        self::assertSame(['updatedAt', 'createdAt', 'deletedAt'], $config['exclude_fields']);
        self::assertTrue($config['enable_admin']);
        self::assertSame('/admin/trail-log', $config['admin_route_prefix']);
        self::assertNull($config['user_provider']);
    }

    public function testOverridesReplaceDefaults(): void
    {
        $config = $this->process([
            'track_deletes'    => false,
            'exclude_fields'   => ['password', 'token'],
            'exclude_entities' => ['App\\Entity\\SessionLog'],
            'enable_admin'     => false,
            'admin_route_prefix' => '/admin/audit',
            'user_provider'    => 'app.my_provider',
        ]);

        self::assertFalse($config['track_deletes']);
        // Symfony replaces (does not merge) array node defaults.
        self::assertSame(['password', 'token'], $config['exclude_fields']);
        self::assertSame(['App\\Entity\\SessionLog'], $config['exclude_entities']);
        self::assertFalse($config['enable_admin']);
        self::assertSame('/admin/audit', $config['admin_route_prefix']);
        self::assertSame('app.my_provider', $config['user_provider']);
    }

    public function testBooleanFlagsCoerceTruthyValues(): void
    {
        $config = $this->process(['track_creates' => false, 'track_updates' => true]);

        self::assertFalse($config['track_creates']);
        self::assertTrue($config['track_updates']);
    }
}
