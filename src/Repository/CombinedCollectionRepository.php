<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ObjectRepository;
use Gedmo\Tree\RepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\DescendantProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates Sulu's collection repository and hands every call to the repository which fits the
 * current request: the permission aware one inside the admin, Sulu's own one everywhere else.
 *
 * The website does not know about admin users and their roles, so filtering collections by
 * access control would hide media from visitors.
 *
 * @phpstan-type DelegateRepository = CollectionRepositoryInterface&DescendantProviderInterface&ObjectRepository<CollectionInterface>&RepositoryInterface<CollectionInterface>&Selectable<int|string, CollectionInterface>
 *
 * @implements ObjectRepository<CollectionInterface>
 * @implements RepositoryInterface<CollectionInterface>
 * @implements Selectable<int|string, CollectionInterface>
 */
readonly class CombinedCollectionRepository implements CollectionRepositoryInterface, DescendantProviderInterface, RepositoryInterface, ObjectRepository, Selectable
{
    /**
     * @param DelegateRepository $permissionAwareCollectionRepository
     * @param DelegateRepository $mediaCollectionRepository
     */
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private CollectionRepositoryInterface $permissionAwareCollectionRepository,
        private CollectionRepositoryInterface $mediaCollectionRepository,
    ) {
    }

    /**
     * @return DelegateRepository
     */
    private function getRepository(): CollectionRepositoryInterface
    {
        if ($this->getFirewall() !== 'admin') {
            return $this->mediaCollectionRepository;
        }

        return $this->permissionAwareCollectionRepository;
    }

    /**
     * @param int $depth
     * @param array<string, mixed> $filter
     * @param array<string, string> $sortBy
     * @param int|null $permission
     *
     * @return CollectionInterface[]
     */
    public function findCollectionSet($depth = 0, $filter = [], ?CollectionInterface $collection = null, $sortBy = [], ?UserInterface $user = null, $permission = null)
    {
        return $this->getRepository()->findCollectionSet($depth, $filter, $collection, $sortBy, $user, $permission);
    }

    /**
     * @param int $depth
     * @param array<string, mixed> $filter
     *
     * @return int
     */
    public function countCollections($depth = 0, $filter = [], ?CollectionInterface $collection = null)
    {
        return $this->getRepository()->countCollections($depth, $filter, $collection);
    }

    /**
     * @return int
     */
    public function countMedia(CollectionInterface $collection)
    {
        return $this->getRepository()->countMedia($collection);
    }

    /**
     * @return int
     */
    public function countSubCollections(CollectionInterface $collection)
    {
        return $this->getRepository()->countSubCollections($collection);
    }

    /**
     * @param int $id
     *
     * @return CollectionInterface|null
     */
    public function findCollectionById($id)
    {
        return $this->getRepository()->findCollectionById($id);
    }

    /**
     * @param array<string, mixed> $filter
     * @param int|null $limit
     * @param int|null $offset
     * @param array<string, string> $sortBy
     *
     * @return Paginator<CollectionInterface>|CollectionInterface[]
     */
    public function findCollections($filter = [], $limit = null, $offset = null, $sortBy = [])
    {
        return $this->getRepository()->findCollections($filter, $limit, $offset, $sortBy);
    }

    /**
     * @param int $id
     *
     * @return CollectionInterface[]
     */
    public function findCollectionBreadcrumbById($id)
    {
        return $this->getRepository()->findCollectionBreadcrumbById($id);
    }

    /**
     * @param string $key
     *
     * @return CollectionInterface|null
     */
    public function findCollectionByKey($key)
    {
        return $this->getRepository()->findCollectionByKey($key);
    }

    /**
     * @param int $id
     * @param string $locale
     *
     * @return CollectionInterface[]
     */
    public function findTree($id, $locale)
    {
        return $this->getRepository()->findTree($id, $locale);
    }

    /**
     * @param int $id
     *
     * @return string
     */
    public function findCollectionTypeById($id)
    {
        return $this->getRepository()->findCollectionTypeById($id);
    }

    /**
     * @return array<array{id: int, resourceKey: string, depth: int}>
     */
    public function findDescendantCollectionResources(int $ancestorId): array
    {
        return $this->getRepository()->findDescendantCollectionResources($ancestorId);
    }

    public function countUnauthorizedDescendantCollections(int $ancestorId, UserInterface $user, int $permission): int
    {
        return $this->getRepository()->countUnauthorizedDescendantCollections($ancestorId, $user, $permission);
    }

    /**
     * @param string|int $id
     *
     * @return string[]|int[]
     */
    public function findDescendantIdsById($id)
    {
        return $this->getRepository()->findDescendantIdsById($id);
    }

    public function supportsDescendantType(string $type): bool
    {
        return $this->getRepository()->supportsDescendantType($type);
    }

    private function getFirewall(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request === false) {
            return null;
        }

        return $this->security->getFirewallConfig($request)?->getName();
    }

    public function find(mixed $id): ?object
    {
        return $this->getRepository()->find($id);
    }

    /**
     * @return CollectionInterface[]
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'asc'|'desc'|'ASC'|'DESC'>|null $orderBy
     *
     * @return CollectionInterface[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?object
    {
        return $this->getRepository()->findOneBy($criteria);
    }

    /**
     * @return class-string<CollectionInterface>
     */
    public function getClassName(): string
    {
        return $this->getRepository()->getClassName();
    }

    /**
     * @param string|null $sortByField
     * @param string $direction
     *
     * @return iterable<int|string, CollectionInterface>
     */
    public function getRootNodes($sortByField = null, $direction = 'asc')
    {
        return $this->getRepository()->getRootNodes($sortByField, $direction);
    }

    /**
     * @param CollectionInterface|null $node
     * @param bool $direct
     * @param array<string, mixed> $options
     * @param bool $includeNode
     *
     * @return iterable<int|string, CollectionInterface>
     */
    public function getNodesHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->getRepository()->getNodesHierarchy($node, $direct, $options, $includeNode);
    }

    /**
     * @param CollectionInterface|null $node
     * @param bool $direct
     * @param string|string[]|null $sortByField
     * @param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     * @param bool $includeNode
     *
     * @return iterable<int|string, CollectionInterface>
     */
    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->getRepository()->getChildren($node, $direct, $sortByField, $direction, $includeNode);
    }

    /**
     * @param CollectionInterface|null $node
     * @param bool $direct
     *
     * @return int
     */
    public function childCount($node = null, $direct = false)
    {
        return $this->getRepository()->childCount($node, $direct);
    }

    /**
     * @param CollectionInterface|null $node
     * @param bool $direct
     * @param array<string, mixed> $options
     * @param bool $includeNode
     *
     * @return array<int, array<string, mixed>>|string
     */
    public function childrenHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->getRepository()->childrenHierarchy($node, $direct, $options, $includeNode);
    }

    /**
     * @param array<int|string, object> $nodes
     * @param array<string, mixed> $options
     *
     * @return array<int, array<string, mixed>>|string
     */
    public function buildTree(array $nodes, array $options = [])
    {
        return $this->getRepository()->buildTree($nodes, $options);
    }

    /**
     * @param object[] $nodes
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildTreeArray(array $nodes)
    {
        return $this->getRepository()->buildTreeArray($nodes);
    }

    /**
     * @param string $childrenIndex
     */
    public function setChildrenIndex($childrenIndex): void
    {
        $this->getRepository()->setChildrenIndex($childrenIndex);
    }

    /**
     * @return string
     */
    public function getChildrenIndex()
    {
        return $this->getRepository()->getChildrenIndex();
    }

    /**
     * @return ReadableCollection<int|string, CollectionInterface>&Selectable<int|string, CollectionInterface>
     */
    public function matching(Criteria $criteria)
    {
        return $this->getRepository()->matching($criteria);
    }

    /**
     * Sulu and Doctrine occasionally rely on repository methods which are not part of any of the
     * implemented interfaces, so everything else is handed over as well.
     *
     * @param string $method
     * @param array<mixed> $arguments
     */
    public function __call($method, $arguments): mixed
    {
        return $this->getRepository()->$method(...$arguments);
    }
}
