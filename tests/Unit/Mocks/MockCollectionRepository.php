<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Gedmo\Tree\RepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\DescendantProviderInterface;

use function array_key_last;

/**
 * Stands in for one of the two repositories the CombinedCollectionRepository delegates to and
 * records which method was called with which arguments.
 *
 * @implements ObjectRepository<CollectionInterface>
 * @implements RepositoryInterface<CollectionInterface>
 * @implements Selectable<int|string, CollectionInterface>
 */
final class MockCollectionRepository implements CollectionRepositoryInterface, DescendantProviderInterface, RepositoryInterface, ObjectRepository, Selectable
{
    /**
     * @var list<array{method: string, arguments: array<mixed>}>
     */
    public array $calls = [];

    public function __construct(public readonly string $name = 'mock')
    {
    }

    /**
     * @return array{method: string, arguments: array<mixed>}|null
     */
    public function lastCall(): ?array
    {
        return $this->calls[array_key_last($this->calls) ?? 0] ?? null;
    }

    public function findCollectionSet($depth = 0, $filter = [], ?CollectionInterface $collection = null, $sortBy = [], ?UserInterface $user = null, $permission = null)
    {
        return $this->record(__FUNCTION__, [$depth, $filter, $collection, $sortBy, $user, $permission], [$this->name]);
    }

    public function countCollections($depth = 0, $filter = [], ?CollectionInterface $collection = null)
    {
        return $this->record(__FUNCTION__, [$depth, $filter, $collection], 1);
    }

    public function countMedia(CollectionInterface $collection)
    {
        return $this->record(__FUNCTION__, [$collection], 2);
    }

    public function countSubCollections(CollectionInterface $collection)
    {
        return $this->record(__FUNCTION__, [$collection], 3);
    }

    public function findCollectionById($id)
    {
        return $this->record(__FUNCTION__, [$id], new Collection());
    }

    public function findCollections($filter = [], $limit = null, $offset = null, $sortBy = [])
    {
        return $this->record(__FUNCTION__, [$filter, $limit, $offset, $sortBy], [$this->name]);
    }

    public function findCollectionBreadcrumbById($id)
    {
        return $this->record(__FUNCTION__, [$id], [$this->name]);
    }

    public function findCollectionByKey($key)
    {
        return $this->record(__FUNCTION__, [$key], new Collection());
    }

    public function findTree($id, $locale)
    {
        return $this->record(__FUNCTION__, [$id, $locale], [$this->name]);
    }

    public function findCollectionTypeById($id)
    {
        return $this->record(__FUNCTION__, [$id], $this->name);
    }

    public function findDescendantCollectionResources(int $ancestorId): array
    {
        return $this->record(__FUNCTION__, [$ancestorId], [['id' => 1, 'resourceKey' => 'collections', 'depth' => 1]]);
    }

    public function countUnauthorizedDescendantCollections(int $ancestorId, UserInterface $user, int $permission): int
    {
        return $this->record(__FUNCTION__, [$ancestorId, $user, $permission], 4);
    }

    public function findDescendantIdsById($id)
    {
        return $this->record(__FUNCTION__, [$id], [1, 2]);
    }

    public function supportsDescendantType(string $type): bool
    {
        return $this->record(__FUNCTION__, [$type], true);
    }

    public function find(mixed $id): ?object
    {
        return $this->record(__FUNCTION__, [$id], new Collection());
    }

    public function findAll(): array
    {
        return $this->record(__FUNCTION__, [], [new Collection()]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->record(__FUNCTION__, [$criteria, $orderBy, $limit, $offset], [new Collection()]);
    }

    public function findOneBy(array $criteria): ?object
    {
        return $this->record(__FUNCTION__, [$criteria], new Collection());
    }

    public function getClassName(): string
    {
        return $this->record(__FUNCTION__, [], Collection::class);
    }

    public function getRootNodes($sortByField = null, $direction = 'asc')
    {
        return $this->record(__FUNCTION__, [$sortByField, $direction], [$this->name]);
    }

    public function getNodesHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->record(__FUNCTION__, [$node, $direct, $options, $includeNode], [$this->name]);
    }

    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->record(__FUNCTION__, [$node, $direct, $sortByField, $direction, $includeNode], [$this->name]);
    }

    public function childCount($node = null, $direct = false)
    {
        return $this->record(__FUNCTION__, [$node, $direct], 5);
    }

    public function childrenHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->record(__FUNCTION__, [$node, $direct, $options, $includeNode], [$this->name]);
    }

    public function buildTree(array $nodes, array $options = [])
    {
        return $this->record(__FUNCTION__, [$nodes, $options], [$this->name]);
    }

    public function buildTreeArray(array $nodes)
    {
        return $this->record(__FUNCTION__, [$nodes], [$this->name]);
    }

    public function setChildrenIndex($childrenIndex): void
    {
        $this->record(__FUNCTION__, [$childrenIndex], null);
    }

    public function getChildrenIndex()
    {
        return $this->record(__FUNCTION__, [], $this->name);
    }

    public function matching(Criteria $criteria)
    {
        return $this->record(__FUNCTION__, [$criteria], new ArrayCollection([new Collection()]));
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->record($method, $arguments, $this->name);
    }

    /**
     * @template T
     *
     * @param array<mixed> $arguments
     * @param T $return
     *
     * @return T
     */
    private function record(string $method, array $arguments, mixed $return): mixed
    {
        $this->calls[] = ['method' => $method, 'arguments' => $arguments];

        return $return;
    }
}
