<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks\Sulu;

use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support\CollectionFixtures;
use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Sulu\Component\Security\Authentication\RoleInterface;

final class MockSystemStore implements SystemStoreInterface
{
    public function __construct(private string $system = CollectionFixtures::SYSTEM)
    {
    }

    public function getSystem(): ?string
    {
        return $this->system;
    }

    public function setSystem(string $system): void
    {
        $this->system = $system;
    }

    public function getAnonymousRole(): ?RoleInterface
    {
        return null;
    }
}
