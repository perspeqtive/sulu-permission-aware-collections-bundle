<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository\CollectionRepository;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support\CollectionFixtures;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks\Sulu\MockSystemStore;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support\SecurityFactory;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support\SuluEntityManagerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancer;

use function iterator_to_array;

class PermissionAwareCollectionRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private CollectionFixtures $fixtures;

    protected function setUp(): void
    {
        $this->entityManager = SuluEntityManagerFactory::create();
        $this->fixtures = new CollectionFixtures($this->entityManager);
    }

    public function testHidesCollectionsTheUserMayNotView(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0));

        self::assertSame(
            ['root', 'visible', 'visible-child'],
            $this->keysOf($repository->findCollectionSet(3, ['locale' => CollectionFixtures::LOCALE])),
        );
    }

    public function testShowsCollectionsTheUserMayView(): void
    {
        $repository = $this->createRepository($this->fixtures->load($this->fixtures->viewPermission()));

        self::assertSame(
            ['root', 'visible', 'visible-child', 'restricted', 'restricted-child'],
            $this->keysOf($repository->findCollectionSet(3, ['locale' => CollectionFixtures::LOCALE])),
        );
    }

    public function testHidesCollectionsFromAnAnonymousUser(): void
    {
        $this->fixtures->load(0);
        $repository = $this->createRepository($this->fixtures, withUser: false);

        self::assertSame(
            ['root', 'visible', 'visible-child'],
            $this->keysOf($repository->findCollectionSet(3, ['locale' => CollectionFixtures::LOCALE])),
        );
    }

    public function testCountsOnlyCollectionsTheUserMayView(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0));

        self::assertSame(3, $repository->countCollections(3));
    }

    public function testHidesRestrictedCollectionsFromFindCollections(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0));

        /** @var iterable<CollectionInterface> $collections */
        $collections = $repository->findCollections();

        self::assertSame(['root', 'visible', 'visible-child'], $this->keysOf(iterator_to_array($collections)));
    }

    public function testHidesRestrictedCollectionsFromFindCollectionByKey(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0));

        self::assertNull($repository->findCollectionByKey('restricted'));
        self::assertNotNull($repository->findCollectionByKey('visible'));
    }

    public function testHidesTheWholeRestrictedSubtree(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0));

        self::assertSame(
            ['root', 'visible', 'visible-child'],
            $this->keysOf($repository->findCollectionSet(3, [], null, [], $this->fixtures->user, $this->fixtures->viewPermission())),
        );
    }

    public function testRestrictsEveryCollectionWithItsOwnAccessControl(): void
    {
        $repository = $this->createRepository($this->fixtures->load(0, inherited: false));

        self::assertSame(
            ['root', 'visible', 'visible-child', 'restricted-child'],
            $this->keysOf($repository->findCollectionSet(3, ['locale' => CollectionFixtures::LOCALE])),
        );
    }

    public function testCountsTheDescendantsTheUserMayNotView(): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        self::assertSame(
            1,
            $repository->countUnauthorizedDescendantCollections(
                (int) $fixtures->collection('restricted')->getId(),
                $fixtures->user,
                $fixtures->viewPermission(),
            ),
        );
    }

    public function testKeepsCountingTheMediaOfAVisibleCollection(): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        self::assertSame(0, $repository->countMedia($fixtures->collection('visible')));
        self::assertSame(1, $repository->countSubCollections($fixtures->collection('visible')));
    }

    public function testDoesNotRestrictTheLookupOfASingleCollection(): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        self::assertNotNull($repository->findCollectionById($fixtures->collection('restricted')->getId()));
    }

    public function testHidesRestrictedBranchesFromTheCollectionTree(): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        self::assertSame(
            ['root', 'visible', 'visible-child'],
            $this->keysOf($repository->findTree($fixtures->collection('visible-child')->getId(), CollectionFixtures::LOCALE)),
        );
    }

    public function testDoesNotRestrictTopLevelCollectionsInTheCollectionTree(): void
    {
        $fixtures = $this->fixtures->load(0)->restrict('root');
        $repository = $this->createRepository($fixtures);

        self::assertContains(
            'root',
            $this->keysOf($repository->findTree($fixtures->collection('visible-child')->getId(), CollectionFixtures::LOCALE)),
        );
        self::assertNotContains('root', $this->keysOf($repository->findCollectionSet(3)));
    }

    public function testDoesNotRestrictTheDescendantIdsUsedForPermissionInheritance(): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        self::assertSame(
            [(int) $fixtures->collection('restricted-child')->getId()],
            $repository->findDescendantIdsById($fixtures->collection('restricted')->getId()),
        );
    }

    /**
     * @return iterable<string, array{callable(CollectionRepository, CollectionFixtures): mixed}>
     */
    public static function readMethodProvider(): iterable
    {
        yield 'findCollectionSet' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionSet(3, ['locale' => CollectionFixtures::LOCALE, 'search' => 'Title', 'systemCollections' => false], $fixtures->collection('visible'), ['title' => 'asc'])];
        yield 'findCollectionSet with permission' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionSet(3, [], null, [], $fixtures->user, $fixtures->viewPermission())];
        yield 'countCollections' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->countCollections(3, ['search' => 'Title', 'locale' => CollectionFixtures::LOCALE, 'systemCollections' => false])];
        yield 'countMedia' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->countMedia($fixtures->collection('visible'))];
        yield 'countSubCollections' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->countSubCollections($fixtures->collection('visible'))];
        yield 'findCollections' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => iterator_to_array($repository->findCollections(['depth' => 1, 'search' => 'Title'], 10, 0, ['title' => 'asc']))];
        yield 'findCollections by parent' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => iterator_to_array($repository->findCollections(['parent' => $fixtures->collection('visible')->getId()]))];
        yield 'findCollectionById' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionById($fixtures->collection('visible')->getId())];
        yield 'findCollectionBreadcrumbById' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionBreadcrumbById($fixtures->collection('visible-child')->getId())];
        yield 'findCollectionByKey' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionByKey('visible')];
        yield 'findTree' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findTree($fixtures->collection('visible-child')->getId(), CollectionFixtures::LOCALE)];
        yield 'findCollectionTypeById' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findCollectionTypeById($fixtures->collection('visible')->getId())];
        yield 'findIdByMediaId' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findIdByMediaId(1)];
        yield 'findDescendantIdsById' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findDescendantIdsById($fixtures->collection('visible')->getId())];
        yield 'findDescendantCollectionResources' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findDescendantCollectionResources((int) $fixtures->collection('visible')->getId())];
        yield 'countUnauthorizedDescendantCollections' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->countUnauthorizedDescendantCollections((int) $fixtures->collection('visible')->getId(), $fixtures->user, $fixtures->viewPermission())];
        yield 'getChildren' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->getChildren($fixtures->collection('visible'))];
        yield 'childCount' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->childCount($fixtures->collection('visible'))];
        yield 'find' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->find($fixtures->collection('visible')->getId())];
        yield 'findAll' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findAll()];
        yield 'findOneBy' => [static fn (CollectionRepository $repository, CollectionFixtures $fixtures) => $repository->findOneBy(['key' => 'visible'])];
    }

    /**
     * Every read method has to produce a query Doctrine accepts. The restriction is added with
     * fixed sub query aliases, so a query which carries it twice is rejected by Doctrine.
     *
     * @param callable(CollectionRepository, CollectionFixtures): mixed $call
     */
    #[DataProvider('readMethodProvider')]
    public function testReadMethodBuildsAQueryDoctrineAccepts(callable $call): void
    {
        $fixtures = $this->fixtures->load(0);
        $repository = $this->createRepository($fixtures);

        $call($repository, $fixtures);

        $this->expectNotToPerformAssertions();
    }

    private function createRepository(CollectionFixtures $fixtures, bool $withUser = true): CollectionRepository
    {
        return new CollectionRepository(
            $this->entityManager,
            $this->entityManager->getClassMetadata(Collection::class),
            new AccessControlQueryEnhancer(new MockSystemStore(), $this->entityManager),
            SecurityFactory::create($withUser ? $fixtures->user : null, 'admin'),
            $fixtures->permissions(),
        );
    }

    /**
     * @param iterable<CollectionInterface> $collections
     *
     * @return list<string|null>
     */
    private function keysOf(iterable $collections): array
    {
        $keys = [];
        foreach ($collections as $collection) {
            $keys[] = $collection->getKey();
        }

        return $keys;
    }
}
