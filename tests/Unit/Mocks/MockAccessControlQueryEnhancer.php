<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks;

use Doctrine\ORM\QueryBuilder;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * Records every restriction it is asked for and adds a recognisable condition to the query.
 */
final class MockAccessControlQueryEnhancer implements AccessControlQueryEnhancerInterface
{
    public const CONDITION = 'acl_restriction = 1';

    /**
     * @var list<array{
     *     queryBuilder: QueryBuilder,
     *     user: UserInterface|null,
     *     permission: int,
     *     entityClass: string,
     *     entityAlias: string,
     *     entityIdField: string
     * }>
     */
    public array $enhanceCalls = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $enhanceWithDynamicEntityClassCalls = [];

    public function enhance(
        QueryBuilder $queryBuilder,
        ?UserInterface $user,
        int $permission,
        string $entityClass,
        string $entityAlias,
        string $entityIdField = 'id',
    ): void {
        $this->enhanceCalls[] = [
            'queryBuilder' => $queryBuilder,
            'user' => $user,
            'permission' => $permission,
            'entityClass' => $entityClass,
            'entityAlias' => $entityAlias,
            'entityIdField' => $entityIdField,
        ];

        $queryBuilder->andWhere(self::CONDITION);
        $queryBuilder->setParameter('aclRestriction', $permission);
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
        $this->enhanceWithDynamicEntityClassCalls[] = [
            'queryBuilder' => $queryBuilder,
            'user' => $user,
            'permission' => $permission,
            'entityClass' => $entityClass,
            'entityAlias' => $entityAlias,
            'entityClassField' => $entityClassField,
            'entityIdField' => $entityIdField,
        ];

        $queryBuilder->andWhere(self::CONDITION);
    }
}
