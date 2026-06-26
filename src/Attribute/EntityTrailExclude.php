<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Attribute;

/**
 * Placed on an entity class to skip it entirely from the audit trail.
 *
 * #[EntityTrailExclude]
 * class SessionLog { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EntityTrailExclude
{
}
