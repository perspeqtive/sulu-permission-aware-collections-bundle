<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use InvalidArgumentException;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancerInterface;
use Sulu\Component\Media\SystemCollections\SystemCollectionManagerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\DescendantProviderInterface;
use Sulu\Component\Security\Authorization\AccessControl\SecuredEntityRepositoryTrait;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Symfony\Bundle\SecurityBundle\Security;

use function array_key_exists;
use function array_map;
use function count;
use function is_array;
use function sprintf;
use function strtolower;

class CollectionRepository extends NestedTreeRepository implements CollectionRepositoryInterface, DescendantProviderInterface
{
    use SecuredEntityRepositoryTrait;

    public function __construct(
        EntityManagerInterface $em,
        ClassMetadata $class,
        private readonly AccessControlQueryEnhancerInterface $accessControlQueryEnhancer,
        private readonly Security $security,
        private readonly array $permissions,
    ) {
        parent::__construct($em, $class);
    }

    public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
    {
        $queryBuilder = parent::createQueryBuilder($alias, $indexBy);

        $this->accessControlQueryEnhancer->enhance(
            $queryBuilder,
            $this->security->getUser(),
            $this->permissions[PermissionTypes::VIEW],
            Collection::class,
            'collection',
        );

        return $queryBuilder;
    }

    public function findCollectionById($id)
    {
        $dql = sprintf(
            'SELECT n, collectionMeta, defaultMeta, collectionType, collectionParent, parentMeta, collectionChildren
                 FROM %s AS n
                     LEFT JOIN n.meta AS collectionMeta
                     LEFT JOIN n.defaultMeta AS defaultMeta
                     LEFT JOIN n.type AS collectionType
                     LEFT JOIN n.parent AS collectionParent
                     LEFT JOIN n.children AS collectionChildren
                     LEFT JOIN collectionParent.meta AS parentMeta
                 WHERE n.id = :id',
            $this->_entityName,
        );

        $query = new Query($this->_em);
        $query->setDQL($dql);
        $query->setParameter('id', $id);
        $result = $query->getResult();

        if (0 === count($result)) {
            return null;
        }

        return $result[0];
    }

    public function findCollectionSet(
        $depth = 0,
        $filter = [],
        ?CollectionInterface $collection = null,
        $sortBy = [],
        ?UserInterface $user = null,
        $permission = null,
    ) {
        $ids = $this->getIdsQuery($depth, $filter, $sortBy, $collection)->getScalarResult();

        $queryBuilder = $this->createQueryBuilder('collection')
            ->addSelect('collectionMeta')
            ->addSelect('defaultMeta')
            ->addSelect('collectionType')
            ->addSelect('collectionParent')
            ->addSelect('parentMeta')
            ->leftJoin('collection.meta', 'collectionMeta')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->leftJoin('collection.type', 'collectionType')
            ->leftJoin('collection.parent', 'collectionParent')
            ->leftJoin('collectionParent.meta', 'parentMeta')
            ->andWhere('collection.id IN (:ids)')
            ->setParameter('ids', $ids);

        if (null !== $sortBy && is_array($sortBy) && count($sortBy) > 0) {
            foreach ($sortBy as $column => $order) {
                $queryBuilder->addOrderBy(
                    'collectionMeta.' . $column,
                    'asc' === strtolower($order) ? 'ASC' : 'DESC',
                );
            }
        }

        $queryBuilder->addOrderBy('collection.id', 'ASC');

        if (null !== $permission) {
            if ($this->accessControlQueryEnhancer) {
                $this->accessControlQueryEnhancer->enhance(
                    $queryBuilder,
                    $user,
                    $permission,
                    Collection::class,
                    'collection',
                );
            } else {
                $this->addAccessControl(
                    $queryBuilder,
                    $user,
                    $permission,
                    Collection::class,
                    'collection',
                );
            }
        }

        /** @var Collection[] */
        return $queryBuilder->getQuery()->getResult();
    }

    public function countCollections($depth = 0, $filter = [], ?CollectionInterface $collection = null)
    {
        try {
            $ids = $this->getIdsQuery($depth, $filter, [], $collection, 'DISTINCT collection.id')->getScalarResult();

            return count($ids);
        } catch (NoResultException) {
        }
    }

    public function countMedia(CollectionInterface $collection): int
    {
        if (!$collection->getId()) {
            throw new InvalidArgumentException();
        }

        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('COUNT(collectionMedia.id)')
            ->leftJoin('collection.media', 'collectionMedia')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $collection->getId());

        /** @var numeric-string $value */
        $value = $queryBuilder->getQuery()->getSingleScalarResult();

        return (int) $value;
    }

    public function countSubCollections(CollectionInterface $collection): int
    {
        if (!$collection->getId()) {
            throw new InvalidArgumentException();
        }

        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('COUNT(subCollections.id)')
            ->leftJoin('collection.children', 'subCollections')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $collection->getId());

        /** @var numeric-string $value */
        $value = $queryBuilder->getQuery()->getSingleScalarResult();

        return (int) $value;
    }

    public function findCollections($filter = [], $limit = null, $offset = null, $sortBy = [])
    {
        list($parent, $depth, $search) = [
            $filter['parent'] ?? null,
            $filter['depth'] ?? null,
            $filter['search'] ?? null,
        ];

        try {
            $qb = $this->createQueryBuilder('collection')
                ->leftJoin('collection.meta', 'collectionMeta')
                ->leftJoin('collection.defaultMeta', 'defaultMeta')
                ->leftJoin('collection.type', 'type')
                ->leftJoin('collection.parent', 'parent')
                ->leftJoin('collection.children', 'children')
                ->addSelect('collectionMeta')
                ->addSelect('defaultMeta')
                ->addSelect('type')
                ->addSelect('parent')
                ->addSelect('children');

            if (is_array($sortBy) && count($sortBy) > 0) {
                foreach ($sortBy as $column => $order) {
                    $qb->addOrderBy('collectionMeta.' . $column, 'asc' === strtolower($order) ? 'ASC' : 'DESC');
                }
            }
            $qb->addOrderBy('collection.id', 'ASC');

            if (null !== $parent) {
                $qb->andWhere('parent.id = :parent');
                $qb->setParameter('parent', $parent);
            } elseif (null !== $depth) {
                // the combination of depth and parent needs a bigger refactoring of this query.
                $qb->andWhere('collection.depth <= :depth');
                $qb->setParameter('depth', (int) $depth);
            }
            if (null !== $search) {
                $qb->andWhere('collectionMeta.title LIKE :search');
                $qb->setParameter('search', '%' . $search . '%');
            }
            if (null !== $offset) {
                $qb->setFirstResult($offset);
            }
            if (null !== $limit) {
                $qb->setMaxResults($limit);
            }

            /** @var Collection[] */
            return new Paginator($qb->getQuery());
        } catch (NoResultException $ex) {
            return;
        }
    }

    public function findCollectionBreadcrumbById($id)
    {
        try {
            $sql = sprintf(
                'SELECT n, collectionMeta, defaultMeta
                 FROM %s AS p,
                      %s AS n
                        LEFT JOIN n.meta AS collectionMeta
                        LEFT JOIN n.defaultMeta AS defaultMeta
                 WHERE p.id = :id AND p.lft > n.lft AND p.rgt < n.rgt
                 ORDER BY n.lft',
                $this->_entityName,
                $this->_entityName,
            );

            $query = new Query($this->_em);
            $query->setDQL($sql);
            $query->setParameter('id', $id);

            return $query->getResult();
        } catch (NoResultException $ex) {
            return [];
        }
    }

    public function findCollectionByKey($key)
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->leftJoin('collection.meta', 'collectionMeta')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->andWhere('collection.key = :key');

        $query = $queryBuilder->getQuery();
        $query->setParameter('key', $key);

        try {
            return $query->getSingleResult();
        } catch (NoResultException $ex) {
            return;
        }
    }

    public function findTree($id, $locale): array
    {
        $subQueryBuilder = $this->createQueryBuilder('subCollection')
            ->select('subCollection.id')
            ->leftJoin($this->_entityName, 'c', Join::WITH, 'c.id = :id')
            ->andWhere('subCollection.lft <= c.lft AND subCollection.rgt > c.lft');

        $queryBuilder = $this->createQueryBuilder('collection')
            ->addSelect('meta')
            ->addSelect('defaultMeta')
            ->addSelect('type')
            ->addSelect('parent')
            ->leftJoin('collection.meta', 'meta', Join::WITH, 'meta.locale = :locale')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->innerJoin('collection.type', 'type')
            ->leftJoin('collection.parent', 'parent')
            ->andWhere(sprintf('parent.id IN (%s)', $subQueryBuilder->getDQL()))
            ->orWhere('parent.id is NULL')
            ->orderBy('collection.lft')
            ->setParameter('id', $id)
            ->setParameter('locale', $locale);

        /** @var Collection[] */
        return $queryBuilder->getQuery()->getResult();
    }

    public function findCollectionTypeById($id): string
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('collectionType.key')
            ->leftJoin('collection.type', 'collectionType')
            ->andWhere('collection.id = :id')
            ->setParameter('id', $id);

        /** @var string */
        return $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns the basic query which selects the ids of a collection for a given
     * set of parameters.
     *
     * @param int $depth
     *
     * @return Query
     */
    private function getIdsQuery(
        $depth = 0,
        array $filter = [],
        array $sortBy = [],
        ?CollectionInterface $collection = null,
        string $select = 'collection.id',
    ) {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->select($select)
            ->andWhere('collection.depth <= :depth');

        $collectionDepth = null !== $collection ? $collection->getDepth() : 0;
        $queryBuilder->setParameter('depth', $collectionDepth + $depth);

        if (null !== $collection) {
            $queryBuilder->andWhere('collection.lft BETWEEN :lft AND :rgt AND collection.id != :id');
            $queryBuilder->setParameter('lft', $collection->getLft());
            $queryBuilder->setParameter('rgt', $collection->getRgt());
            $queryBuilder->setParameter('id', $collection->getId());
        }

        if (array_key_exists('search', $filter) && null !== $filter['search']
            || array_key_exists('locale', $filter)
            || count($sortBy) > 0
        ) {
            $queryBuilder->leftJoin('collection.meta', 'collectionMeta');
            $queryBuilder->leftJoin('collection.defaultMeta', 'defaultMeta');
        }

        if (array_key_exists('search', $filter) && null !== $filter['search']) {
            $queryBuilder->andWhere('collectionMeta.title LIKE :search OR defaultMeta.locale != :locale');
            $queryBuilder->setParameter('search', '%' . $filter['search'] . '%');
        }

        if (array_key_exists('locale', $filter)) {
            $queryBuilder->andWhere('collectionMeta.locale = :locale OR defaultMeta.locale != :locale');
            $queryBuilder->setParameter('locale', $filter['locale']);
        }

        if (array_key_exists('systemCollections', $filter) && !$filter['systemCollections']) {
            $queryBuilder->leftJoin('collection.type', 'collectionType');
            $queryBuilder->andWhere('collectionType.key != :type');
            $queryBuilder->setParameter('type', SystemCollectionManagerInterface::COLLECTION_TYPE);
        }

        if (count($sortBy) > 0) {
            foreach ($sortBy as $column => $order) {
                $queryBuilder->addOrderBy(
                    'collectionMeta.' . $column,
                    'asc' === strtolower($order) ? 'ASC' : 'DESC',
                );
            }
        }

        $queryBuilder->addOrderBy('collection.id', 'ASC');

        if (array_key_exists('limit', $filter)) {
            $queryBuilder->setMaxResults($filter['limit']);
        }
        if (array_key_exists('offset', $filter)) {
            $queryBuilder->setFirstResult($filter['offset']);
        }

        return $queryBuilder->getQuery();
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

    /**
     * @return int[]
     */
    public function findDescendantIdsById($id): array
    {
        $queryBuilder = $this->createQueryBuilder('subCollection')
            ->select('subCollection.id')
            ->from($this->_entityName, 'collection')
            ->andWhere('collection.id = :id')
            ->andWhere('subCollection.lft > collection.lft AND subCollection.rgt < collection.rgt')
            ->setParameter('id', $id);

        /** @var array<array{id: numeric-string}> $result */
        $result = $queryBuilder->getQuery()->getScalarResult();

        return array_map(function ($collection) {
            return (int) $collection['id'];
        }, $result);
    }

    public function supportsDescendantType(string $type): bool
    {
        return $this->getClassName() === $type;
    }

    private function createDescendantCollectionsQueryBuilder(string $alias, int $ancestorId): QueryBuilder
    {
        $ancestorCollectionAlias = $alias . '_ancestorCollection';

        return $this->createQueryBuilder($alias)
            ->innerJoin(
                CollectionInterface::class,
                $ancestorCollectionAlias,
                Join::WITH,
                $alias . '.lft > ' . $ancestorCollectionAlias . '.lft AND ' . $alias . '.rgt < ' . $ancestorCollectionAlias . '.rgt',
            )
            ->andWhere($ancestorCollectionAlias . '.id = :id')
            ->setParameter('id', $ancestorId);
    }

    /**
     * @return array<array{id: int, resourceKey: string, depth: int}>
     */
    public function findDescendantCollectionResources(int $ancestorId): array
    {
        return $this->createDescendantCollectionsQueryBuilder('collection', $ancestorId)
            ->select('collection.id AS id')
            ->addSelect('\'' . CollectionInterface::RESOURCE_KEY . '\' AS resourceKey')
            ->addSelect('collection.depth AS depth')
            ->distinct()
            ->orderBy('collection.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    private function createAuthorizedDescendantCollectionsQueryBuilder(
        string $alias,
        int $ancestorId,
        UserInterface $user,
        int $permission,
    ): QueryBuilder {
        $qb = $this->createDescendantCollectionsQueryBuilder($alias, $ancestorId);

        $this->accessControlQueryEnhancer->enhance(
            $qb,
            $user,
            $permission,
            Collection::class,
            $alias,
        );

        return $qb;
    }

    private function createUnauthorizedDescendantCollectionsQueryBuilder(
        string $alias,
        int $ancestorId,
        UserInterface $user,
        int $permission,
    ): QueryBuilder {
        $qb = $this->createDescendantCollectionsQueryBuilder($alias, $ancestorId);

        $authorizedDescendantCollectionsQb = $this
            ->createAuthorizedDescendantCollectionsQueryBuilder('authorizedCollection', $ancestorId, $user, $permission)
            ->select('authorizedCollection.id')
            ->distinct()
            ->orderBy('authorizedCollection.id', 'ASC');

        $qb
            ->andWhere($alias . '.id NOT IN (' . $authorizedDescendantCollectionsQb->getDQL() . ')')
            ->setParameters($authorizedDescendantCollectionsQb->getParameters());

        return $qb;
    }

    public function countUnauthorizedDescendantCollections(int $ancestorId, UserInterface $user, int $permission): int
    {
        /** @var int */
        return $this
            ->createUnauthorizedDescendantCollectionsQueryBuilder('collection', $ancestorId, $user, $permission)
            ->select('COUNT(collection.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
