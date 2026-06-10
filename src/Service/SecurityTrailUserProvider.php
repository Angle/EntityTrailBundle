<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Service;

use Angle\TrailBundle\Contract\TrailUserProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default user provider. Resolves the acting user from the security context and
 * the client IP from the current request. In CLI/worker contexts where there is
 * no authenticated user, every getter returns null.
 */
final class SecurityTrailUserProvider implements TrailUserProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCurrentUserId(): ?int
    {
        $user = $this->security->getUser();

        if ($user !== null && method_exists($user, 'getId')) {
            $id = $user->getId();

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }

    public function getCurrentUserLabel(): ?string
    {
        $user = $this->security->getUser();

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
}
