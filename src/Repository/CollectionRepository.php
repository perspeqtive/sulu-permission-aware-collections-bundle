<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\AccessControl\SingleRestrictionQueryEnhancer;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Doctrine\AppendOnlyWhereQueryBuilder;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepository as SuluCollectionRepository;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Symfony\Bundle\SecurityBundle\Security;

class CollectionRepository extends SuluCollectionRepository
{
    private const RESTRICTED_ALIAS = 'collection';

    private readonly SingleRestrictionQueryEnhancer $queryEnhancer;

    private bool $restrictionsSuspended = false;

    /**
     * @param ClassMetadata<CollectionInterface> $class
     * @param array<string, int> $permissions
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadata $class,
        AccessControlQueryEnhancerInterface $accessControlQueryEnhancer,
        private readonly Security $security,
        private readonly array $permissions,
    ) {
        parent::__construct($entityManager, $class);

        $this->queryEnhancer = new SingleRestrictionQueryEnhancer($accessControlQueryEnhancer);
        $this->setAccessControlQueryEnhancer($this->queryEnhancer);
    }

    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        $queryBuilder = (new AppendOnlyWhereQueryBuilder($this->getEntityManager()))
            ->select($alias)
            ->from($this->getEntityName(), $alias, $indexBy);

        if ($this->restrictionsSuspended || self::RESTRICTED_ALIAS !== $alias) {
            return $queryBuilder;
        }

        $user = $this->security->getUser();

        $this->queryEnhancer->enhance(
            $queryBuilder,
            $user instanceof UserInterface ? $user : null,
            $this->permissions[PermissionTypes::VIEW],
            Collection::class,
            $alias,
        );

        return $queryBuilder;
    }

    public function countUnauthorizedDescendantCollections(int $ancestorId, UserInterface $user, int $permission): int
    {
        return $this->withoutRestrictions(
            fn (): int => parent::countUnauthorizedDescendantCollections($ancestorId, $user, $permission),
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withoutRestrictions(callable $callback): mixed
    {
        $this->restrictionsSuspended = true;

        try {
            return $callback();
        } finally {
            $this->restrictionsSuspended = false;
        }
    }
}
