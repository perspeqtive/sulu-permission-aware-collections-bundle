<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Builds a real Symfony security service on top of a token storage and a firewall map, so the
 * repositories are tested against the behaviour of the actual class instead of a test double.
 */
final class SecurityFactory
{
    public static function create(?UserInterface $user = null, ?string $firewall = null): Security
    {
        $tokenStorage = new TokenStorage();
        if (null !== $user) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, $firewall ?? 'admin'));
        }

        $firewallConfig = null === $firewall
            ? null
            : new FirewallConfig($firewall, 'security.user_checker');

        return new Security(new ServiceLocator([
            'security.token_storage' => static fn (): TokenStorage => $tokenStorage,
            'security.firewall.map' => static fn (): StaticFirewallMap => new StaticFirewallMap($firewallConfig),
        ]));
    }
}
