<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Contract;

interface EntityTrailUserProviderInterface
{
    public function getCurrentUserId(): ?int;

    /**
     * Human-readable label for the acting user, e.g. "Ada Lovelace (ada@example.com)".
     */
    public function getCurrentUserLabel(): ?string;

    public function getCurrentIpAddress(): ?string;
}
