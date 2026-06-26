<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Functional\Fixtures;

use Angle\EntityTrailBundle\Contract\EntityTrailUserProviderInterface;

/**
 * Deterministic user provider for functional tests — no security context needed.
 */
final class StaticEntityTrailUserProvider implements EntityTrailUserProviderInterface
{
    public function getCurrentUserId(): ?int
    {
        return 7;
    }

    public function getCurrentUserLabel(): ?string
    {
        return 'Ada Lovelace (ada@example.com)';
    }

    public function getCurrentIpAddress(): ?string
    {
        return '203.0.113.9';
    }
}
