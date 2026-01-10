<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\AccessControl\QueryEnhancer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PERSPEQTIVE\SuluAccessControlCustomQueryEnhancerBundle\QueryEnhancer\SpecificAccessControlQueryEnhancerInterface;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Bundle\SecurityBundle\Entity\AccessControl;
use Sulu\Bundle\SecurityBundle\System\SystemStore;
use Sulu\Component\Security\Authentication\UserInterface;

use function array_column;
use function count;
use function in_array;
use function sprintf;

readonly class CollectionQueryEnhancer implements SpecificAccessControlQueryEnhancerInterface
{
    public function __construct(
        private AccessControlQueryEnhancerInterface $accessControlQueryEnhancer,
        private EntityManagerInterface $entityManager,
        private SystemStore $systemStore,
    ) {
    }

    public function enhance(QueryBuilder $queryBuilder, ?UserInterface $user, int $permission, string $entityClass, string $entityAlias): void
    {
        if ($user instanceof UserInterface === false) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        if ($this->isAdminUser($user) === true) {
            return;
        }

        $this->overwriteLegacy($queryBuilder, $user, $permission, $entityClass, $entityAlias);
    }

    public function enhanceWithDynamicEntityClass(QueryBuilder $queryBuilder, ?UserInterface $user, int $permission, string $entityClass, string $entityAlias, string $entityClassField, string $entityIdField): void
    {
        if ($user instanceof UserInterface === false) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        if ($this->isAdminUser($user) === true) {
            return;
        }
        $this->accessControlQueryEnhancer->enhanceWithDynamicEntityClass($queryBuilder, $user, $permission, $entityClass, $entityAlias, $entityClassField, $entityIdField);
    }

    public function supports(string $class): bool
    {
        return $class === Collection::class;
    }

    private function isAdminUser(UserInterface $user): bool
    {
        $roles = $user->getRoleObjects();
        foreach ($roles as $role) {
            $key = $role->getKey();
            if ($key === null) {
                continue;
            }
            if (in_array($key, ['admin'], true) === true) {
                return true;
            }
        }

        return false;
    }

    private function overwriteLegacy(QueryBuilder $queryBuilder, ?UserInterface $user, int $permission, string $entityClass, string $entityAlias): void
    {
        $subQueryBuilder = $this->getAllowedItemsDql($entityClass);
        $notAllowedItemsQueryBuilder = $this->getNotAllowedQueryBuilder($entityClass, $subQueryBuilder, $user, $permission);

        $result = $notAllowedItemsQueryBuilder->getQuery()->getScalarResult();
        $ids = array_column($result, 'id');

        if (count($ids) > 0) {
            $queryBuilder->andWhere(sprintf('%s.id NOT IN (:accessControlIds)', $entityAlias));
            $queryBuilder->setParameter('accessControlIds', $ids);
        }
    }

    private function getUserRoleIds(?UserInterface $user): array
    {
        if ($user) {
            $roleIds = [];

            foreach ($user->getRoleObjects() as $role) {
                if ($role->getSystem() === $this->systemStore->getSystem()) {
                    $roleIds[] = $role->getId();
                }
            }

            return $roleIds;
        }

        $anonymousRole = $this->systemStore->getAnonymousRole();

        return $anonymousRole ? [$anonymousRole->getId()] : [];
    }

    private function getAllowedItemsDql(string $entityClass): QueryBuilder
    {
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select()
            ->from($entityClass, 'allowedEntity')
            ->addSelect('1')
            ->leftJoin(
                AccessControl::class,
                'accessControl',
                'WITH',
                'accessControl.entityIdInteger = allowedEntity.id AND accessControl.entityClass = :entityClass',
            )
            ->where(
                $subQueryBuilder->expr()->orX(
                    $subQueryBuilder->expr()->andX(
                        'accessControl.role IN (:roleIds)',
                        'BIT_AND(accessControl.permissions, :permission) = :permission',
                    ),
                    'accessControl.id IS NULL',
                ),
            );
        $subQueryBuilder->andWhere('allowedEntity.id = e.id');

        return $subQueryBuilder;
    }

    private function getNotAllowedQueryBuilder(string $entityClass, QueryBuilder $subQueryBuilder, ?UserInterface $user, int $permission): QueryBuilder
    {
        $notAllowedItemsQueryBuilder = $this->entityManager->createQueryBuilder();
        $notAllowedItemsQueryBuilder->select('e.id')
            ->from($entityClass, 'e');

        $notAllowedItemsQueryBuilder->where(
            $notAllowedItemsQueryBuilder->expr()->not(
                $notAllowedItemsQueryBuilder->expr()->exists($subQueryBuilder->getDQL()),
            ),
        );
        $notAllowedItemsQueryBuilder->setParameter('entityClass', $entityClass)
            ->setParameter('roleIds', $this->getUserRoleIds($user))
            ->setParameter('permission', $permission);

        return $notAllowedItemsQueryBuilder;
    }
}
