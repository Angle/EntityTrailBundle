<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Unit\Entity;

use Angle\TrailBundle\Entity\TrailLog;
use PHPUnit\Framework\TestCase;

final class TrailLogTest extends TestCase
{
    public function testOnPrePersistGeneratesA32CharHexCodeAndTimestamp(): void
    {
        $log = new TrailLog();
        $log->onPrePersist();

        self::assertSame(32, \strlen($log->getCode()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $log->getCode());
        self::assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    public function testOnPrePersistDoesNotOverwriteExistingValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 00:00:00');

        $log = (new TrailLog())
            ->setCode('preset-code')
            ->setCreatedAt($createdAt);
        $log->onPrePersist();

        self::assertSame('preset-code', $log->getCode());
        self::assertSame($createdAt, $log->getCreatedAt());
    }

    public function testEachGeneratedCodeIsUnique(): void
    {
        $a = new TrailLog();
        $a->onPrePersist();
        $b = new TrailLog();
        $b->onPrePersist();

        self::assertNotSame($a->getCode(), $b->getCode());
    }

    public function testGetEntityShortNameReturnsLastNamespaceSegment(): void
    {
        $log = (new TrailLog())->setEntityType('App\\Entity\\Client');

        self::assertSame('Client', $log->getEntityShortName());
    }

    public function testGetEntityShortNameHandlesClassWithoutNamespace(): void
    {
        $log = (new TrailLog())->setEntityType('Client');

        self::assertSame('Client', $log->getEntityShortName());
    }

    public function testGettersAndSetters(): void
    {
        $log = (new TrailLog())
            ->setEntityType('App\\Entity\\Product')
            ->setEntityId(42)
            ->setEntityCode('ABC123')
            ->setAction(TrailLog::ACTION_UPDATE)
            ->setChanges(['name' => ['old' => 'a', 'new' => 'b']])
            ->setUserId(7)
            ->setUserLabel('Ada Lovelace (ada@example.com)')
            ->setIpAddress('127.0.0.1');

        self::assertSame('App\\Entity\\Product', $log->getEntityType());
        self::assertSame(42, $log->getEntityId());
        self::assertSame('ABC123', $log->getEntityCode());
        self::assertSame('update', $log->getAction());
        self::assertSame(['name' => ['old' => 'a', 'new' => 'b']], $log->getChanges());
        self::assertSame(7, $log->getUserId());
        self::assertSame('Ada Lovelace (ada@example.com)', $log->getUserLabel());
        self::assertSame('127.0.0.1', $log->getIpAddress());
        self::assertNull($log->getId());
    }

    public function testActionConstants(): void
    {
        self::assertSame('create', TrailLog::ACTION_CREATE);
        self::assertSame('update', TrailLog::ACTION_UPDATE);
        self::assertSame('delete', TrailLog::ACTION_DELETE);
    }
}
