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

use function in_array;

readonly class CollectionQueryEnhancer implements SpecificAccessControlQueryEnhancerInterface
{
    public function __construct(
        private AccessControlQueryEnhancerInterface $accessControlQueryEnhancer,
        private EntityManagerInterface $entityManager,
        private SystemStore $systemStore
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

        $this->overwriteLegacy();
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

    private function overwriteLegacy(QueryBuilder $queryBuilder, ?UserInterface $user, int $permission, string $entityClass, string $entityAlias, string $entityClassField, string $entityIdField): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('collection.id')
            ->from($entityClass, 'collection');

        $subQb = $this->entityManager->createQueryBuilder();
        $subQb->select('1')
            ->from(AccessControl::class, 'accessControl')
            ->innerJoin('accessControl.role', 'role')
            ->where('accessControl.entityIdInteger = collection.id');

        if ($entityClassField) {
            $subQb->andWhere('accessControl.entityClass = collection.' . $entityClassField);
        } else {
            $subQb->andWhere('accessControl.entityClass = :entityClass')
                ->setParameter('entityClass', $entityClass);
        }

        $subQb->andWhere('role.id IN (:roleIds)')
            ->andWhere('(BIT_AND(accessControl.permissions, :permission) = :permission)')
            ->setParameter('roleIds', $this->getUserRoleIds($user))
            ->setParameter('permission', $permission);

        $qb->andWhere($qb->expr()->not(
            $qb->expr()->exists($subQb->getDQL())
        ));

        $result = $qb->getQuery()->getScalarResult();
        $ids = array_column($result, 'id');

        if (\count($ids) > 0) {
            $queryBuilder->andWhere(\sprintf('%s.id NOT IN (:accessControlIds)', $entityAlias));
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
}
