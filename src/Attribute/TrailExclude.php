<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Attribute;

/**
 * Placed on an entity class to skip it entirely from the audit trail.
 *
 * #[TrailExclude]
 * class SessionLog { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TrailExclude
{
}
