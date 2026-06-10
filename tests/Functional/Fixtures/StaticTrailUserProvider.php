<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Functional\Fixtures;

use Angle\TrailBundle\Contract\TrailUserProviderInterface;

/**
 * Deterministic user provider for functional tests — no security context needed.
 */
final class StaticTrailUserProvider implements TrailUserProviderInterface
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
