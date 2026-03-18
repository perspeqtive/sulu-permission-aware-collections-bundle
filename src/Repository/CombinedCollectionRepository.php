<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository;

use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\DescendantProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CombinedCollectionRepository implements CollectionRepositoryInterface, DescendantProviderInterface
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
}
