<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Attribute;

/**
 * Placed on an entity property to remove that field from the changeset
 * before the audit log is written.
 *
 * #[EntityTrailIgnore]
 * private string $passwordHash;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class EntityTrailIgnore
{
}
