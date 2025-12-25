<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepository as BaseCollectionRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Symfony\Bundle\SecurityBundle\Security;

class CollectionRepository extends BaseCollectionRepository
{

    private AccessControlQueryEnhancerInterface $accessControlQueryEnhancer;
    private Security $security;
    private array $permissions = [];

    public function createQueryBuilder($alias, $indexBy = null): QueryBuilder {

        $queryBuilder = parent::createQueryBuilder($alias, $indexBy);

        $this->accessControlQueryEnhancer->enhance(
            $queryBuilder,
            $this->security->getUser(),
            $this->permissions[PermissionTypes::VIEW],
            Collection::class,
            'collection'
        );

        return $queryBuilder;
    }
    protected function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = parent::getQueryBuilder();

        $this->accessControlQueryEnhancer->enhance(
            $queryBuilder,
            $this->security->getUser(),
            $this->permissions[PermissionTypes::VIEW],
            Collection::class,
            'collection'
        );

        return $queryBuilder;
    }

    public function setAccessControlQueryEnhancer(AccessControlQueryEnhancerInterface $accessControlQueryEnhancer): void
    {
        parent::setAccessControlQueryEnhancer($accessControlQueryEnhancer);
        $this->accessControlQueryEnhancer = $accessControlQueryEnhancer;
    }

    public function setSecurity(Security $security): void {
        $this->security = $security;
    }

    public function setPermissions(array $permissions): void {
        $this->permissions = $permissions;
    }

    public function countMedia(CollectionInterface $collection): int
    {
        if (!$collection->getId()) {
            throw new \InvalidArgumentException();
        }

        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('COUNT(collectionMedia.id)')
            ->leftJoin('collection.media', 'collectionMedia')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $collection->getId());

        /** @var numeric-string $value */
        $value = $queryBuilder->getQuery()->getSingleScalarResult();

        return \intval($value);
    }

    public function countSubCollections(CollectionInterface $collection): int
    {
        if (!$collection->getId()) {
            throw new \InvalidArgumentException();
        }

        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('COUNT(subCollections.id)')
            ->leftJoin('collection.children', 'subCollections')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $collection->getId());

        /** @var numeric-string $value */
        $value = $queryBuilder->getQuery()->getSingleScalarResult();

        return \intval($value);
    }

    public function findCollectionByKey($key): ?CollectionInterface
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->leftJoin('collection.meta', 'collectionMeta')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->andWhere('collection.key = :key');

        $query = $queryBuilder->getQuery();
        $query->setParameter('key', $key);

        try {
            return $query->getSingleResult();
        } catch (NoResultException) {}
        return null;
    }

    public function findCollectionTypeById($id): ?string
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('collectionType.key')
            ->leftJoin('collection.type', 'collectionType')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $id);

        return $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function findIdByMediaId(int $mediaId): ?int
    {
        $queryBuilder = $this->_em->createQueryBuilder()
            ->from(MediaInterface::class, 'media')
            ->select('IDENTITY(media.collection)')
            ->andWhere('media.id = :mediaId')
            ->setParameter('mediaId', $mediaId);

        try {
            return (int) $queryBuilder->getQuery()->getSingleScalarResult();
        } catch (NoResultException $e) {
            return null;
        }
    }
}