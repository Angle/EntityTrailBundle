<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Tests\Unit\Service;

use Angle\EntityTrailBundle\Service\SecurityEntityTrailUserProvider;
use Angle\EntityTrailBundle\Tests\Unit\Service\Fixtures\FullUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityEntityTrailUserProviderTest extends TestCase
{
    private function security(?UserInterface $user): Security
    {
        $tokenStorage = new TokenStorage();
        if ($user !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        return new Security($container);
    }

    private function requestStack(?string $ip): RequestStack
    {
        $stack = new RequestStack();
        if ($ip !== null) {
            $stack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]));
        }

        return $stack;
    }

    public function testReturnsNullsWhenNoUserIsAuthenticated(): void
    {
        $provider = new SecurityEntityTrailUserProvider($this->security(null), $this->requestStack('10.0.0.1'));

        self::assertNull($provider->getCurrentUserId());
        self::assertNull($provider->getCurrentUserLabel());
        self::assertSame('10.0.0.1', $provider->getCurrentIpAddress());
    }

    public function testResolvesIdAndCompositeLabelFromRichUser(): void
    {
        $user = new FullUser(7, 'Ada Lovelace', 'ada@example.com');
        $provider = new SecurityEntityTrailUserProvider($this->security($user), $this->requestStack('192.168.1.5'));

        self::assertSame(7, $provider->getCurrentUserId());
        self::assertSame('Ada Lovelace (ada@example.com)', $provider->getCurrentUserLabel());
        self::assertSame('192.168.1.5', $provider->getCurrentIpAddress());
    }

    public function testFallsBackToUserIdentifierWhenNoNameOrEmail(): void
    {
        $user = new InMemoryUser('worker@system', null);
        $provider = new SecurityEntityTrailUserProvider($this->security($user), $this->requestStack(null));

        // InMemoryUser exposes no getId()/getEmail()/getFullName().
        self::assertNull($provider->getCurrentUserId());
        self::assertSame('worker@system', $provider->getCurrentUserLabel());
        self::assertNull($provider->getCurrentIpAddress());
    }

    public function testReturnsNullIpWhenThereIsNoCurrentRequest(): void
    {
        $provider = new SecurityEntityTrailUserProvider($this->security(null), $this->requestStack(null));

        self::assertNull($provider->getCurrentIpAddress());
    }
}
