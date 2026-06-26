<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Unit\Controller\Ajax;

use Angle\EntityTrailBundle\Controller\Ajax\EntityTrailLogController;
use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Angle\EntityTrailBundle\Repository\EntityTrailLogRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntityTrailLogControllerTest extends TestCase
{
    private function sampleLog(): EntityTrailLog
    {
        return (new EntityTrailLog())
            ->setCode('abcdef0123456789abcdef0123456789')
            ->setEntityType('App\\Entity\\Product')
            ->setEntityId(42)
            ->setEntityCode('ABC123')
            ->setAction(EntityTrailLog::ACTION_UPDATE)
            ->setChanges(['name' => ['old' => 'a', 'new' => 'b'], 'email' => ['old' => null, 'new' => 'x']])
            ->setUserLabel('Ada Lovelace')
            ->setCreatedAt(new \DateTimeImmutable('2026-06-10 14:32:05'));
    }

    public function testDataReturnsDataTablesPayload(): void
    {
        $log = $this->sampleLog();

        $repository = $this->createMock(EntityTrailLogRepository::class);
        $repository->expects(self::once())
            ->method('findForDataTable')
            ->with('cli', 0, 10, 'created_at', 'ASC', 'App\\Entity\\Product', 'update')
            ->willReturn(['recordsTotal' => 500, 'recordsFiltered' => 1, 'items' => [$log]]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with('angle_entity_trail_view', ['code' => $log->getCode()])
            ->willReturn('/admin/trail-log/' . $log->getCode());

        $controller = new EntityTrailLogController($repository, $urlGenerator);

        $request = new Request([], [
            'draw'             => 3,
            'start'            => 0,
            'length'           => 10,
            'search'           => ['value' => 'cli'],
            'order'            => [['column' => 5, 'dir' => 'asc']],
            'filterEntityType' => 'App\\Entity\\Product',
            'filterAction'     => 'update',
        ]);

        $response = $controller->data($request);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(3, $payload['draw']);
        self::assertSame(500, $payload['recordsTotal']);
        self::assertSame(1, $payload['recordsFiltered']);
        self::assertCount(1, $payload['data']);

        $row = $payload['data'][0];
        self::assertSame('Product', $row['entity']);
        self::assertSame('ABC123', $row['record']);
        self::assertStringContainsString('badge', $row['action']);
        self::assertStringContainsString('bg-warning', $row['action']);
        self::assertStringContainsString('update', $row['action']);
        self::assertSame('Ada Lovelace', $row['user']);
        self::assertSame('name, email', $row['changes']);
        self::assertSame('2026-06-10 14:32', $row['created_at']);
        self::assertStringContainsString('/admin/trail-log/' . $log->getCode(), $row['actions']);
        self::assertStringContainsString('View', $row['actions']);
    }

    public function testDataDefaultsToCreatedAtDescWhenNoOrderGiven(): void
    {
        $repository = $this->createMock(EntityTrailLogRepository::class);
        $repository->expects(self::once())
            ->method('findForDataTable')
            ->with('', 0, 25, 'created_at', 'DESC', null, null)
            ->willReturn(['recordsTotal' => 0, 'recordsFiltered' => 0, 'items' => []]);

        $controller = new EntityTrailLogController($repository, $this->createMock(UrlGeneratorInterface::class));

        $response = $controller->data(new Request([], ['draw' => 1]));
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['draw']);
        self::assertSame([], $payload['data']);
    }

    public function testRecordFallsBackToEntityIdWhenNoCode(): void
    {
        $log = $this->sampleLog()->setEntityCode(null)->setAction(EntityTrailLog::ACTION_DELETE);

        $repository = $this->createMock(EntityTrailLogRepository::class);
        $repository->method('findForDataTable')
            ->willReturn(['recordsTotal' => 1, 'recordsFiltered' => 1, 'items' => [$log]]);

        $controller = new EntityTrailLogController($repository, $this->createMock(UrlGeneratorInterface::class));

        $payload = json_decode(
            (string) $controller->data(new Request([], ['draw' => 1]))->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('42', $payload['data'][0]['record']);
        self::assertStringContainsString('bg-danger', $payload['data'][0]['action']);
    }
}
