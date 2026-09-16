<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\SecurityBundle\Entity\AccessControl;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Sulu\Component\Security\Authorization\PermissionTypes;

/**
 * Creates the collections, the role and the user the repository tests query.
 *
 * The tree looks like this, the restricted collections are the only ones with an access control
 * record. Everything hangs below a single root because Gedmo numbers several roots into each other
 * as long as the entity has no tree root column, which Sulu's collection does not have.
 *
 *     root
 *       ├── visible
 *       │     └── visible-child
 *       └── restricted
 *             └── restricted-child
 */
final class CollectionFixtures
{
    public const SYSTEM = 'Sulu';

    public const LOCALE = 'en';

    /**
     * @var array<string, Collection>
     */
    public array $collections = [];

    public User $user;

    public Role $role;

    private CollectionType $collectionType;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param int $permissions permission mask the role has on the restricted collections
     * @param bool $inherited whether the restriction is stored for the descendants as well, which
     *                        is what Sulu does when permissions are saved for a collection
     */
    public function load(int $permissions = 0, bool $inherited = true): self
    {
        $this->collectionType = new CollectionType();
        $this->collectionType->setName('Default')->setKey('collection.default');
        $this->entityManager->persist($this->collectionType);

        $root = $this->createCollection('root');
        $visible = $this->createCollection('visible', $root);
        $this->createCollection('visible-child', $visible);
        $restricted = $this->createCollection('restricted', $root);
        $this->createCollection('restricted-child', $restricted);

        $this->role = new Role();
        $this->role->setName('Editor')->setSystem(self::SYSTEM);
        $this->entityManager->persist($this->role);

        $contact = new Contact();
        $contact->setFirstName('Test')->setLastName('User');
        $this->entityManager->persist($contact);

        $this->user = new User();
        $this->user->setUsername('test')->setPassword('password')->setLocale(self::LOCALE)->setContact($contact);
        $this->entityManager->persist($this->user);

        $userRole = new UserRole();
        $userRole->setUser($this->user)->setRole($this->role)->setLocale('["' . self::LOCALE . '"]');
        $this->entityManager->persist($userRole);
        $this->user->addUserRole($userRole);

        $this->entityManager->flush();

        $restrictedCollections = $inherited
            ? [$restricted, $this->collections['restricted-child']]
            : [$restricted];

        foreach ($restrictedCollections as $collection) {
            $accessControl = new AccessControl();
            $accessControl
                ->setEntityClass(Collection::class)
                ->setEntityId((int) $collection->getId())
                ->setPermissions($permissions)
                ->setRole($this->role);
            $this->entityManager->persist($accessControl);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        foreach ($this->collections as $key => $collection) {
            $this->collections[$key] = $this->entityManager->getRepository(Collection::class)
                ->findOneBy(['key' => $key]);
        }

        $this->user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $this->role = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => 'Editor']);

        return $this;
    }

    /**
     * Stores an access control record for a collection which was loaded without one.
     */
    public function restrict(string $key, int $permissions = 0): self
    {
        $accessControl = new AccessControl();
        $accessControl
            ->setEntityClass(Collection::class)
            ->setEntityId((int) $this->collection($key)->getId())
            ->setPermissions($permissions)
            ->setRole($this->role);

        $this->entityManager->persist($accessControl);
        $this->entityManager->flush();

        return $this;
    }

    public function viewPermission(): int
    {
        return $this->permissions()[PermissionTypes::VIEW];
    }

    /**
     * @return array<string, int>
     */
    public function permissions(): array
    {
        return [
            PermissionTypes::VIEW => 64,
            PermissionTypes::ADD => 32,
            PermissionTypes::EDIT => 16,
            PermissionTypes::DELETE => 8,
            PermissionTypes::ARCHIVE => 4,
            PermissionTypes::LIVE => 2,
            PermissionTypes::SECURITY => 1,
        ];
    }

    public function collection(string $key): Collection
    {
        return $this->collections[$key];
    }

    private function createCollection(string $key, ?Collection $parent = null): Collection
    {
        $collection = new Collection();
        $collection->setType($this->collectionType)->setKey($key);
        if (null !== $parent) {
            $collection->setParent($parent);
        }

        $meta = new CollectionMeta();
        $meta->setTitle('Title of ' . $key)->setLocale(self::LOCALE)->setCollection($collection);
        $collection->addMeta($meta);
        $collection->setDefaultMeta($meta);

        $this->entityManager->persist($collection);
        $this->entityManager->persist($meta);

        $this->collections[$key] = $collection;

        return $collection;
    }
}
