<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Unit\Service\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user that exposes getId()/getFullName()/getEmail() — the shape
 * SecurityTrailUserProvider probes for via method_exists().
 */
final class FullUser implements UserInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $fullName,
        private readonly string $email,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
