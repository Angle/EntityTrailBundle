<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Functional;

use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Angle\EntityTrailBundle\Repository\EntityTrailLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class EntityTrailLogRepositoryUserFilterTest extends TestCase
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

    private function persistLog(EntityManagerInterface $em, string $code, ?int $userId, ?string $userLabel): void
    {
        $log = (new EntityTrailLog())
            ->setCode($code)
            ->setEntityType('App\\Entity\\Product')
            ->setEntityId(1)
            ->setAction(EntityTrailLog::ACTION_UPDATE)
            ->setUserId($userId)
            ->setUserLabel($userLabel)
            ->setCreatedAt(new \DateTimeImmutable('2026-06-15 12:00:00'));

        $em->persist($log);
        $em->flush();
    }

    public function testUserIdNarrowsResultsToExactMatch(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), 7, 'Alice');
        $this->persistLog($em, str_repeat('b', 32), 8, 'Bob');
        $this->persistLog($em, str_repeat('c', 32), null, null);

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC', filterUserId: 7);

        self::assertSame(3, $result['recordsTotal']);
        self::assertSame(1, $result['recordsFiltered']);
        self::assertCount(1, $result['items']);
        self::assertSame(str_repeat('a', 32), $result['items'][0]->getCode());
    }

    public function testUserIdZeroMatchesEntriesWithoutUser(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), 7, 'Alice');
        $this->persistLog($em, str_repeat('b', 32), null, null);

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC', filterUserId: 0);

        self::assertSame(2, $result['recordsTotal']);
        self::assertSame(1, $result['recordsFiltered']);
        self::assertCount(1, $result['items']);
        self::assertSame(str_repeat('b', 32), $result['items'][0]->getCode());
    }

    public function testNullUserIdAppliesNoFilter(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), 7, 'Alice');
        $this->persistLog($em, str_repeat('b', 32), null, null);

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $result = $repo->findForDataTable('', 0, 25, 'created_at', 'DESC');

        self::assertSame(2, $result['recordsTotal']);
        self::assertSame(2, $result['recordsFiltered']);
    }

    public function testFindDistinctUsersUsesLabelFromLatestEntry(): void
    {
        $em = $this->boot();
        // Same user logged under an old name first, then a newer one that sorts
        // alphabetically earlier — the latest entry (highest id) must win.
        $this->persistLog($em, str_repeat('a', 32), 7, 'Zed Oldname');
        $this->persistLog($em, str_repeat('b', 32), 7, 'Alice Newname');

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        self::assertSame([['userId' => 7, 'userLabel' => 'Alice Newname']], $repo->findDistinctUsers());
    }

    public function testFindDistinctUsersReturnsDeduplicatedPairs(): void
    {
        $em = $this->boot();
        $this->persistLog($em, str_repeat('a', 32), 7, 'Alice');
        $this->persistLog($em, str_repeat('b', 32), 7, 'Alice');
        $this->persistLog($em, str_repeat('c', 32), 8, 'Bob');
        $this->persistLog($em, str_repeat('d', 32), null, null);

        /** @var EntityTrailLogRepository $repo */
        $repo = $em->getRepository(EntityTrailLog::class);

        $users = $repo->findDistinctUsers();

        self::assertSame([
            ['userId' => 7, 'userLabel' => 'Alice'],
            ['userId' => 8, 'userLabel' => 'Bob'],
        ], $users);
    }
}
