<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\EventListener;

use Angle\EntityTrailBundle\Attribute\EntityTrailExclude;
use Angle\EntityTrailBundle\Attribute\EntityTrailIgnore;
use Angle\EntityTrailBundle\Contract\EntityTrailUserProviderInterface;
use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Writes a JSON diff to `entity_trail_logs` whenever a tracked entity is created,
 * updated, or deleted.
 *
 * Audit rows are queued during the lifecycle events and flushed with a raw
 * DBAL insert in postFlush — no ORM involvement in the write, so there is no
 * risk of triggering a recursive flush cycle.
 */
final class EntityTrailListener
{
    /**
     * Changesets captured in preUpdate, keyed by spl_object_id, awaiting postUpdate.
     *
     * @var array<int, array<string, array{old: mixed, new: mixed}>>
     */
    private array $pending = [];

    /**
     * Audit rows ready to be inserted on the next postFlush.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $queue = [];

    /**
     * Delete rows captured in preRemove. Doctrine dispatches preRemove at
     * EntityManager::remove() time — *before* flush — so these are buffered
     * separately and merged into the queue when the flush actually starts.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $pendingDeletes = [];

    /**
     * @var array<class-string, bool>
     */
    private array $skipCache = [];

    /**
     * @var array<class-string, list<string>>
     */
    private array $ignoredFieldsCache = [];

    /**
     * @param array{
     *     track_creates: bool,
     *     track_updates: bool,
     *     track_deletes: bool,
     *     exclude_entities: list<string>,
     *     exclude_fields: list<string>,
     *     enable_admin: bool,
     *     admin_route_prefix: string,
     *     user_provider: ?string
     * } $config
     */
    public function __construct(
        private readonly array $config,
        private readonly EntityTrailUserProviderInterface $userProvider,
    ) {
    }

    /**
     * Fires at the very start of every commit, before postPersist/postUpdate run.
     * We start the in-flight flush from a clean slate, carrying over only the deletes
     * that were registered via EntityManager::remove() (preRemove fires before flush).
     *
     * Any leftovers from a previously *failed* commit — whose postFlush never ran — are
     * dropped here, so they can't leak into this flush's audit rows.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->pending = [];
        $this->queue = $this->pendingDeletes;
        $this->pendingDeletes = [];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->config['track_updates']) {
            return;
        }

        $entity = $args->getObject();
        if ($this->shouldSkip($entity)) {
            return;
        }

        $changeset = $this->normalizeChangeSet($args->getEntityChangeSet());
        $changeset = $this->removeIgnoredFields($changeset, $entity);

        if ($changeset === []) {
            return;
        }

        $this->pending[spl_object_id($entity)] = $changeset;
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $oid = spl_object_id($entity);

        if (!isset($this->pending[$oid])) {
            return;
        }

        $changeset = $this->pending[$oid];
        unset($this->pending[$oid]);

        $this->enqueue($args->getObjectManager(), $entity, EntityTrailLog::ACTION_UPDATE, $changeset);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if (!$this->config['track_creates']) {
            return;
        }

        $entity = $args->getObject();
        if ($this->shouldSkip($entity)) {
            return;
        }

        $em = $args->getObjectManager();
        $changes = $this->removeIgnoredFields($this->snapshot($em, $entity), $entity);

        $this->enqueue($em, $entity, EntityTrailLog::ACTION_CREATE, $changes);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if (!$this->config['track_deletes']) {
            return;
        }

        $entity = $args->getObject();
        if ($this->shouldSkip($entity)) {
            return;
        }

        // preRemove runs at remove()-time, before the flush. Buffer it; onFlush will
        // move it into the queue for the flush that actually performs the delete.
        $row = $this->buildRow($args->getObjectManager(), $entity, EntityTrailLog::ACTION_DELETE, []);
        if ($row !== null) {
            $this->pendingDeletes[] = $row;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->queue === []) {
            return;
        }

        $conn = $args->getObjectManager()->getConnection();
        $rows = $this->queue;
        $this->queue = [];

        foreach ($rows as $log) {
            $conn->insert('entity_trail_logs', [
                'code'        => $log['code'],
                'entity_type' => $log['entity_type'],
                'entity_id'   => $log['entity_id'],
                'entity_code' => $log['entity_code'],
                'action'      => $log['action'],
                'changes'     => json_encode($log['changes'], JSON_UNESCAPED_UNICODE),
                'user_id'     => $log['user_id'],
                'user_label'  => $log['user_label'],
                'ip_address'  => $log['ip_address'],
                'created_at'  => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    private function enqueue(EntityManagerInterface $em, object $entity, string $action, array $changes): void
    {
        $row = $this->buildRow($em, $entity, $action, $changes);
        if ($row !== null) {
            $this->queue[] = $row;
        }
    }

    /**
     * Build a fully-resolved audit row, or null if the entity has no usable numeric id.
     *
     * @param array<string, array{old: mixed, new: mixed}> $changes
     *
     * @return array<string, mixed>|null
     */
    private function buildRow(EntityManagerInterface $em, object $entity, string $action, array $changes): ?array
    {
        $meta = $em->getClassMetadata($entity::class);

        $entityId = $this->resolveEntityId($meta, $entity);
        if ($entityId === null) {
            // Cannot anchor an audit row without a numeric identifier.
            return null;
        }

        return [
            'code'        => bin2hex(random_bytes(16)),
            'entity_type' => $meta->getName(),
            'entity_id'   => $entityId,
            'entity_code' => $this->resolveEntityCode($meta, $entity),
            'action'      => $action,
            'changes'     => $changes,
            'user_id'     => $this->userProvider->getCurrentUserId(),
            'user_label'  => $this->userProvider->getCurrentUserLabel(),
            'ip_address'  => $this->userProvider->getCurrentIpAddress(),
        ];
    }

    /**
     * should_skip(entity):
     *   1. Entity is EntityTrailLog itself          → skip (prevent recursion)
     *   2. Class has #[EntityTrailExclude]           → skip
     *   3. FQCN is in exclude_entities config  → skip
     *   4. otherwise                           → track
     */
    private function shouldSkip(object $entity): bool
    {
        if ($entity instanceof EntityTrailLog) {
            return true;
        }

        $class = $this->resolveRealClass($entity);

        if (isset($this->skipCache[$class])) {
            return $this->skipCache[$class];
        }

        $skip = false;

        if (in_array($class, $this->config['exclude_entities'], true)) {
            $skip = true;
        } else {
            $reflection = new \ReflectionClass($class);
            if ($reflection->getAttributes(EntityTrailExclude::class) !== []) {
                $skip = true;
            }
        }

        return $this->skipCache[$class] = $skip;
    }

    /**
     * remove_ignored_fields(changeset, entity):
     *   1. Remove fields listed in exclude_fields config.
     *   2. Remove properties carrying #[EntityTrailIgnore].
     *
     * @param array<string, array{old: mixed, new: mixed}> $changeset
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function removeIgnoredFields(array $changeset, object $entity): array
    {
        foreach ($this->config['exclude_fields'] as $field) {
            unset($changeset[$field]);
        }

        foreach ($this->ignoredFields($entity) as $field) {
            unset($changeset[$field]);
        }

        return $changeset;
    }

    /**
     * @return list<string>
     */
    private function ignoredFields(object $entity): array
    {
        $class = $this->resolveRealClass($entity);

        if (isset($this->ignoredFieldsCache[$class])) {
            return $this->ignoredFieldsCache[$class];
        }

        $ignored = [];
        $reflection = new \ReflectionClass($class);

        while ($reflection !== false) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getAttributes(EntityTrailIgnore::class) !== []) {
                    $ignored[] = $property->getName();
                }
            }
            $reflection = $reflection->getParentClass();
        }

        return $this->ignoredFieldsCache[$class] = array_values(array_unique($ignored));
    }

    /**
     * Snapshot of an entity's scalar fields as a {field: {old: null, new: value}} changeset.
     *
     * @return array<string, array{old: null, new: mixed}>
     */
    private function snapshot(EntityManagerInterface $em, object $entity): array
    {
        $meta = $em->getClassMetadata($entity::class);
        $identifiers = $meta->getIdentifierFieldNames();
        $snapshot = [];

        foreach ($meta->getFieldNames() as $field) {
            if (in_array($field, $identifiers, true)) {
                continue;
            }

            $value = $meta->getFieldValue($entity, $field);
            $snapshot[$field] = ['old' => null, 'new' => $this->normalizeValue($value)];
        }

        return $snapshot;
    }

    /**
     * Convert Doctrine's [field => [old, new]] changeset into JSON-safe values.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function normalizeChangeSet(array $changeSet): array
    {
        $normalized = [];

        foreach ($changeSet as $field => [$old, $new]) {
            $normalized[$field] = [
                'old' => $this->normalizeValue($old),
                'new' => $this->normalizeValue($new),
            ];
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeValue($item), $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'getId') && $value->getId() !== null) {
                return $this->resolveRealClass($value) . '#' . $value->getId();
            }
            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return $this->resolveRealClass($value);
        }

        return $value;
    }

    private function resolveEntityId(object $meta, object $entity): ?int
    {
        /** @var array<string, mixed> $ids */
        $ids = $meta->getIdentifierValues($entity);

        if (count($ids) !== 1) {
            return null;
        }

        $id = reset($ids);

        return is_numeric($id) ? (int) $id : null;
    }

    private function resolveEntityCode(object $meta, object $entity): ?string
    {
        if (!in_array('code', $meta->getFieldNames(), true)) {
            return null;
        }

        $code = $meta->getFieldValue($entity, 'code');

        return $code === null ? null : (string) $code;
    }

    /**
     * Resolve the real class behind a possible Doctrine proxy.
     */
    private function resolveRealClass(object $entity): string
    {
        $class = $entity::class;
        $marker = '\\__CG__\\';
        $pos = strpos($class, $marker);

        return $pos === false ? $class : substr($class, $pos + strlen($marker));
    }
}
