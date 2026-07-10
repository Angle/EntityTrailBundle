<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Unit\DependencyInjection;

use Angle\EntityTrailBundle\DependencyInjection\Configuration;
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
        self::assertNull($config['created_at_format']['timezone']);
        self::assertSame('Y-m-d H:i', $config['created_at_format']['format']);
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
            'created_at_format' => ['timezone' => 'America/Mexico_City', 'format' => 'd/m/Y H:i:s'],
        ]);

        self::assertFalse($config['track_deletes']);
        // Symfony replaces (does not merge) array node defaults.
        self::assertSame(['password', 'token'], $config['exclude_fields']);
        self::assertSame(['App\\Entity\\SessionLog'], $config['exclude_entities']);
        self::assertFalse($config['enable_admin']);
        self::assertSame('/admin/audit', $config['admin_route_prefix']);
        self::assertSame('app.my_provider', $config['user_provider']);
        self::assertSame('America/Mexico_City', $config['created_at_format']['timezone']);
        self::assertSame('d/m/Y H:i:s', $config['created_at_format']['format']);
    }

    public function testCreatedAtFormatPartialOverrideKeepsOtherDefault(): void
    {
        $config = $this->process(['created_at_format' => ['timezone' => 'UTC']]);

        self::assertSame('UTC', $config['created_at_format']['timezone']);
        self::assertSame('Y-m-d H:i', $config['created_at_format']['format']);
    }

    public function testBooleanFlagsCoerceTruthyValues(): void
    {
        $config = $this->process(['track_creates' => false, 'track_updates' => true]);

        self::assertFalse($config['track_creates']);
        self::assertTrue($config['track_updates']);
    }
}
