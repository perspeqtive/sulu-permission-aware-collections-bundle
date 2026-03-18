<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Gedmo\Tree\RepositoryInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\DescendantProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CombinedCollectionRepository implements CollectionRepositoryInterface, DescendantProviderInterface, RepositoryInterface, ObjectRepository, Selectable

{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private CollectionRepositoryInterface $permissionAwareCollectionRepository,
        private CollectionRepositoryInterface $mediaCollectionRepository,
    ) {
    }

    private function getRepository(): CollectionRepositoryInterface
    {
        if ($this->getFirewall() !== 'admin') {
            return $this->mediaCollectionRepository;
        }

        return $this->permissionAwareCollectionRepository;
    }

    public function findCollectionSet($depth = 0, $filter = [], ?CollectionInterface $collection = null, $sortBy = [], ?UserInterface $user = null, $permission = null)
    {
        return $this->getRepository()->findCollectionSet($depth, $filter, $collection, $sortBy, $user, $permission);
    }

    public function countCollections($depth = 0, $filter = [], ?CollectionInterface $collection = null)
    {
        return $this->getRepository()->countCollections($depth, $filter, $collection);
    }

    public function countMedia(CollectionInterface $collection)
    {
        return $this->getRepository()->countMedia($collection);
    }

    public function countSubCollections(CollectionInterface $collection)
    {
        return $this->getRepository()->countSubCollections($collection);
    }

    public function findCollectionById($id)
    {
        return $this->getRepository()->findCollectionById($id);
    }

    public function findCollections($filter = [], $limit = null, $offset = null, $sortBy = [])
    {
        return $this->getRepository()->findCollections($filter, $limit, $offset, $sortBy);
    }

    public function findCollectionBreadcrumbById($id)
    {
        return $this->getRepository()->findCollectionBreadcrumbById($id);
    }

    public function findCollectionByKey($key)
    {
        return $this->getRepository()->findCollectionByKey($key);
    }

    public function findTree($id, $locale)
    {
        return $this->getRepository()->findTree($id, $locale);
    }

    public function findCollectionTypeById($id)
    {
        return $this->getRepository()->findCollectionTypeById($id);
    }

    public function findDescendantCollectionResources(int $ancestorId): array
    {
        return $this->getRepository()->findDescendantCollectionResources($ancestorId);
    }

    public function countUnauthorizedDescendantCollections(int $ancestorId, UserInterface $user, int $permission): int
    {
        return $this->getRepository()->countUnauthorizedDescendantCollections($ancestorId, $user, $permission);
    }

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

    public function find($id)
    {
        return $this->getRepository()->find($id);
    }

    public function findAll()
    {
        return $this->getRepository()->findAll();
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)
    {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria)
    {
        return $this->getRepository()->findOneBy($criteria);
    }

    public function getClassName()
    {
        return $this->getRepository()->getClassName();
    }

    public function getRootNodes($sortByField = null, $direction = 'asc')
    {
        return $this->getRepository()->getRootNodes($sortByField, $direction);
    }

    public function getNodesHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->getRepository()->getNodesHierarchy($node, $direct, $options, $includeNode);
    }

    public function getChildren($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        return $this->getRepository()->getChildren($node, $direct, $sortByField, $direction, $includeNode);
    }

    public function childCount($node = null, $direct = false)
    {
        return $this->getRepository()->childCount($node, $direct);
    }

    public function childrenHierarchy($node = null, $direct = false, array $options = [], $includeNode = false)
    {
        return $this->getRepository()->childrenHierarchy($node, $direct, $options, $includeNode);
    }

    public function buildTree(array $nodes, array $options = [])
    {
        return $this->getRepository()->buildTree($nodes, $options);
    }

    public function buildTreeArray(array $nodes)
    {
        return $this->getRepository()->buildTreeArray($nodes);
    }

    public function setChildrenIndex($childrenIndex)
    {
        $this->getRepository()->setChildrenIndex($childrenIndex);
    }

    public function getChildrenIndex()
    {
        return $this->getRepository()->getChildrenIndex();
    }

    public function matching(Criteria $criteria)
    {
        return $this->getRepository()->matching($criteria);
    }

    public function __call($method, $arguments) {
        return $this->getRepository()->$method(...$arguments);
    }
}
