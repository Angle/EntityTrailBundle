<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Functional;

use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Angle\EntityTrailBundle\Tests\Functional\Fixtures\Entity\Product;
use Angle\EntityTrailBundle\Tests\Functional\Fixtures\Entity\SessionLog;
use Angle\EntityTrailBundle\Tests\Functional\Fixtures\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class EntityTrailListenerFunctionalTest extends TestCase
{
    private ?EntityTrailTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;

        // Booting a Symfony kernel registers an exception handler; restore the previous
        // one so PHPUnit's risky-test detection stays quiet. (No error handler is added,
        // so we must NOT restore_error_handler() — that would pop PHPUnit's own.)
        restore_exception_handler();
    }

    /**
     * @param array<string, mixed> $trailConfig
     */
    private function boot(array $trailConfig = []): EntityManagerInterface
    {
        $this->kernel = new EntityTrailTestKernel($trailConfig);
        $this->kernel->boot();

        /** @var EntityManagerInterface $em */
        $em = $this->kernel->getContainer()->get('test.em');

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(EntityManagerInterface $em, ?string $action = null): array
    {
        $sql = 'SELECT * FROM entity_trail_logs';
        $params = [];
        if ($action !== null) {
            $sql .= ' WHERE action = ?';
            $params[] = $action;
        }
        $sql .= ' ORDER BY id';

        $rows = $em->getConnection()->fetchAllAssociative($sql, $params);

        return array_map(static function (array $row): array {
            $row['changes'] = json_decode((string) $row['changes'], true, 512, JSON_THROW_ON_ERROR);

            return $row;
        }, $rows);
    }

    public function testCreateWritesACreateRowWithSnapshotAndActor(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget')->setCode('ABC123');
        $em->persist($product);
        $em->flush();

        $rows = $this->rows($em);
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame('create', $row['action']);
        self::assertSame(Product::class, $row['entity_type']);
        self::assertSame($product->getId(), (int) $row['entity_id']);
        self::assertSame('ABC123', $row['entity_code']);
        self::assertSame(32, \strlen((string) $row['code']));

        // Snapshot records new values with null "old"; excluded/ignored fields are absent.
        self::assertArrayHasKey('name', $row['changes']);
        self::assertSame(['old' => null, 'new' => 'Widget'], $row['changes']['name']);
        self::assertArrayNotHasKey('updatedAt', $row['changes']);
        self::assertArrayNotHasKey('id', $row['changes']);

        // Actor captured from the (overridden) user provider.
        self::assertSame(7, (int) $row['user_id']);
        self::assertSame('Ada Lovelace (ada@example.com)', $row['user_label']);
        self::assertSame('203.0.113.9', $row['ip_address']);
    }

    public function testUpdateWritesAnUpdateRowWithDiffAndDropsExcludedFields(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget');
        $em->persist($product);
        $em->flush();

        $product->setName('Gadget');
        $product->setUpdatedAt('2026-06-10');
        $em->flush();

        $rows = $this->rows($em, EntityTrailLog::ACTION_UPDATE);
        self::assertCount(1, $rows);

        $changes = $rows[0]['changes'];
        self::assertSame(['old' => 'Widget', 'new' => 'Gadget'], $changes['name']);
        self::assertArrayNotHasKey('updatedAt', $changes, 'exclude_fields must drop updatedAt');
    }

    public function testAssociationChangeRecordsWhichRelationChangedToWhich(): void
    {
        $em = $this->boot();

        $alice = (new User())->setEmail('alice@example.com');
        $bob = (new User())->setEmail('bob@example.com');
        $em->persist($alice);
        $em->persist($bob);

        $product = (new Product())->setName('Widget')->setOwner($alice);
        $em->persist($product);
        $em->flush();

        // Reassign the relation: alice → bob (the Client.agent scenario).
        $product->setOwner($bob);
        $em->flush();

        $rows = array_values(array_filter(
            $this->rows($em, EntityTrailLog::ACTION_UPDATE),
            static fn (array $r): bool => $r['entity_type'] === Product::class,
        ));
        self::assertCount(1, $rows);

        $changes = $rows[0]['changes'];
        self::assertArrayHasKey('owner', $changes);
        // The stored diff must identify WHICH record on each side, not just the class.
        self::assertStringContainsString('#' . $alice->getId(), (string) $changes['owner']['old']);
        self::assertStringContainsString('#' . $bob->getId(), (string) $changes['owner']['new']);
        self::assertNotSame($changes['owner']['old'], $changes['owner']['new']);
    }

    public function testNumericallyEqualDecimalChangeIsNotLogged(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget')->setPrice('0.00000');
        $em->persist($product);
        $em->flush();

        // Doctrine hydrates decimal as '0.00000'; the app writes back '0'.
        // Same value → must NOT produce an audit row (the feeFactor noise).
        $product->setPrice('0');
        $em->flush();

        self::assertCount(0, $this->rows($em, EntityTrailLog::ACTION_UPDATE));

        // A real numeric change must still be logged.
        $product->setPrice('2.50000');
        $em->flush();

        $rows = $this->rows($em, EntityTrailLog::ACTION_UPDATE);
        self::assertCount(1, $rows);
        self::assertArrayHasKey('price', $rows[0]['changes']);
    }

    public function testNoUpdateRowWhenOnlyExcludedFieldsChange(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget');
        $em->persist($product);
        $em->flush();

        // Only an excluded field changes → empty effective changeset → no row.
        $product->setUpdatedAt('2026-06-10');
        $em->flush();

        self::assertCount(0, $this->rows($em, EntityTrailLog::ACTION_UPDATE));
    }

    public function testDeleteWritesADeleteRowWithEmptyChanges(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget');
        $em->persist($product);
        $em->flush();
        $productId = $product->getId();

        $em->remove($product);
        $em->flush();

        $rows = $this->rows($em, EntityTrailLog::ACTION_DELETE);
        self::assertCount(1, $rows);
        self::assertSame($productId, (int) $rows[0]['entity_id']);
        self::assertSame([], $rows[0]['changes']);
    }

    public function testEntityTrailExcludeAttributeSkipsEntityEntirely(): void
    {
        $em = $this->boot();

        $em->persist((new SessionLog())->setData('something'));
        $em->flush();

        self::assertCount(0, $this->rows($em));
    }

    public function testEntityTrailIgnoreAttributeStripsField(): void
    {
        $em = $this->boot();

        $user = (new User())->setEmail('a@example.com')->setPasswordHash('hash-1');
        $em->persist($user);
        $em->flush();

        // Create snapshot must not include the ignored passwordHash.
        $createChanges = $this->rows($em, EntityTrailLog::ACTION_CREATE)[0]['changes'];
        self::assertArrayHasKey('email', $createChanges);
        self::assertArrayNotHasKey('passwordHash', $createChanges);

        $user->setEmail('b@example.com');
        $user->setPasswordHash('hash-2');
        $em->flush();

        $updateChanges = $this->rows($em, EntityTrailLog::ACTION_UPDATE)[0]['changes'];
        self::assertArrayHasKey('email', $updateChanges);
        self::assertArrayNotHasKey('passwordHash', $updateChanges);
    }

    public function testExcludeEntitiesConfigSkipsEntity(): void
    {
        $em = $this->boot(['exclude_entities' => [Product::class]]);

        $em->persist((new Product())->setName('Widget'));
        $em->flush();

        self::assertCount(0, $this->rows($em));
    }

    public function testTrackCreatesFalseDisablesCreateRows(): void
    {
        $em = $this->boot(['track_creates' => false]);

        $product = (new Product())->setName('Widget');
        $em->persist($product);
        $em->flush();
        self::assertCount(0, $this->rows($em));

        // Updates still tracked.
        $product->setName('Gadget');
        $em->flush();
        self::assertCount(1, $this->rows($em, EntityTrailLog::ACTION_UPDATE));
    }

    public function testRepositoryReadsBackThroughTheOrm(): void
    {
        $em = $this->boot();

        $product = (new Product())->setName('Widget')->setCode('XYZ');
        $em->persist($product);
        $em->flush();

        $repository = $em->getRepository(EntityTrailLog::class);
        $history = $repository->findByEntity(Product::class, (int) $product->getId());

        self::assertCount(1, $history);
        self::assertInstanceOf(EntityTrailLog::class, $history[0]);
        self::assertSame('create', $history[0]->getAction());
        self::assertSame('XYZ', $history[0]->getEntityCode());
    }
}
