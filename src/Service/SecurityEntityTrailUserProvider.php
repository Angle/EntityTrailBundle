<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Service;

use Angle\EntityTrailBundle\Contract\EntityTrailUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default user provider. Resolves the acting user from the security token storage
 * and the client IP from the current request. In CLI/worker contexts where there is
 * no authenticated user, every getter returns null.
 *
 * Depends on TokenStorageInterface (stable across Symfony 5.4/6.4/7.x) rather than
 * the SecurityBundle `Security` helper, whose class moved between those versions.
 */
final class SecurityEntityTrailUserProvider implements EntityTrailUserProviderInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCurrentUserId(): ?int
    {
        $user = $this->getUser();

        if ($user !== null && method_exists($user, 'getId')) {
            $id = $user->getId();

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }

    public function getCurrentUserLabel(): ?string
    {
        $user = $this->getUser();

        if ($user === null) {
            return null;
        }

        $name = null;
        foreach (['getFullName', 'getFullname', 'getName', 'getDisplayName'] as $method) {
            if (method_exists($user, $method)) {
                $value = $user->$method();
                if (is_string($value) && $value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        $email = null;
        if (method_exists($user, 'getEmail')) {
            $value = $user->getEmail();
            if (is_string($value) && $value !== '') {
                $email = $value;
            }
        }
        $email ??= $user->getUserIdentifier();

        if ($name !== null && $email !== '') {
            return sprintf('%s (%s)', $name, $email);
        }

        return $name ?? ($email !== '' ? $email : null);
    }

    public function getCurrentIpAddress(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }

    private function getUser(): ?UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
