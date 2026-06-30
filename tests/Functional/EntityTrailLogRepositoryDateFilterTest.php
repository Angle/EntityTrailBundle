<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Functional;

use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Angle\EntityTrailBundle\Repository\EntityTrailLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class EntityTrailLogRepositoryDateFilterTest extends TestCase
{
    private ?EntityTrailTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        restore_exception_handler();
    }

    private function boot(): EntityManagerInterface
    {
        $this->kernel = new EntityTrailTestKernel();
        $this->kernel->boot();

        /** @var EntityManagerInterface $em */
        $em = $this->kernel->getContainer()->get('test.em');

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }

    private function persistLog(EntityManagerInterface $em, string $code, string $date): void
    {
        $log = (new EntityTrailLog())
            ->setCode($code)
            ->setEntityType('App\\Entity\\Product')
            ->setEntityId(1)
            ->setAction(EntityTrailLog::ACTION_UPDATE)
            ->setCreatedAt(new \DateTimeImmutable($date));

        $em->persist($log);
        $em->flush();
    }

    public function testDateRangeNarrowsResults(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), '2026-06-05 12:00:00');
        $this->persistLog($em, str_repeat('b', 32), '2026-06-15 12:00:00');
        $this->persistLog($em, str_repeat('c', 32), '2026-06-25 12:00:00');

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC', null, null, '2026-06-10', '2026-06-20');

        self::assertSame(3, $result['recordsTotal']);
        self::assertSame(1, $result['recordsFiltered']);
        self::assertCount(1, $result['items']);
        self::assertSame(str_repeat('b', 32), $result['items'][0]->getCode());
    }

    public function testOpenEndedFromIncludesOnlyOnOrAfter(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), '2026-06-05 12:00:00');
        $this->persistLog($em, str_repeat('b', 32), '2026-06-25 12:00:00');

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC', null, null, '2026-06-10', null);

        self::assertSame(2, $result['recordsTotal']);
        self::assertSame(1, $result['recordsFiltered']);
        self::assertSame(str_repeat('b', 32), $result['items'][0]->getCode());
    }

    public function testMalformedDateIsIgnored(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), '2026-06-05 12:00:00');

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC', null, null, 'not-a-date', '');

        self::assertSame(1, $result['recordsTotal']);
        self::assertSame(1, $result['recordsFiltered']);
    }
}
