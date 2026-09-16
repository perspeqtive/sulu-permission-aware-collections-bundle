<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support;

use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stands in for "security.firewall.map" and always reports the same firewall.
 */
final readonly class StaticFirewallMap
{
    public function __construct(private ?FirewallConfig $firewallConfig)
    {
    }

    public function getFirewallConfig(Request $request): ?FirewallConfig
    {
        return $this->firewallConfig;
    }
}
