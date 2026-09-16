<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\AccessControl;

use Doctrine\ORM\QueryBuilder;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use WeakMap;

/**
 * Applies the wrapped enhancer at most once per query builder.
 *
 * Sulu's enhancer names the aliases of its sub queries statically ("acl", "acl_check", "role",
 * "role_check"), and Doctrine requires identification variables to be unique within a whole DQL
 * statement. Restricting the same query builder twice therefore produces a query which Doctrine
 * rejects with "'acl_check' is already defined". This happens as soon as the repository restricts a
 * query builder which Sulu's own collection repository also restricts - findCollectionSet() with a
 * permission is the case in point.
 *
 * @internal
 */
final class SingleRestrictionQueryEnhancer implements AccessControlQueryEnhancerInterface
{
    /**
     * @var WeakMap<QueryBuilder, true>
     */
    private WeakMap $restrictedQueryBuilders;

    public function __construct(private readonly AccessControlQueryEnhancerInterface $inner)
    {
        $this->restrictedQueryBuilders = new WeakMap();
    }

    public function enhance(
        QueryBuilder $queryBuilder,
        ?UserInterface $user,
        int $permission,
        string $entityClass,
        string $entityAlias,
        string $entityIdField = 'id',
    ): void {
        if ($this->isRestricted($queryBuilder)) {
            return;
        }

        $this->inner->enhance($queryBuilder, $user, $permission, $entityClass, $entityAlias, $entityIdField);
        $this->restrictedQueryBuilders[$queryBuilder] = true;
    }

    public function enhanceWithDynamicEntityClass(
        QueryBuilder $queryBuilder,
        ?UserInterface $user,
        int $permission,
        string $entityClass,
        string $entityAlias,
        string $entityClassField,
        string $entityIdField,
    ): void {
        if ($this->isRestricted($queryBuilder)) {
            return;
        }

        $this->inner->enhanceWithDynamicEntityClass(
            $queryBuilder,
            $user,
            $permission,
            $entityClass,
            $entityAlias,
            $entityClassField,
            $entityIdField,
        );
        $this->restrictedQueryBuilders[$queryBuilder] = true;
    }

    public function isRestricted(QueryBuilder $queryBuilder): bool
    {
        return isset($this->restrictedQueryBuilders[$queryBuilder]);
    }
}
