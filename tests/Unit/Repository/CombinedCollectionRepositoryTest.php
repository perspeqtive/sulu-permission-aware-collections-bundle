<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Repository;

use Doctrine\Common\Collections\Criteria;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository\CombinedCollectionRepository;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support\SecurityFactory;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks\MockCollectionRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CombinedCollectionRepositoryTest extends TestCase
{
    private MockCollectionRepository $permissionAware;

    private MockCollectionRepository $media;

    protected function setUp(): void
    {
        $this->permissionAware = new MockCollectionRepository('permission-aware');
        $this->media = new MockCollectionRepository('media');
    }

    public function testUsesThePermissionAwareRepositoryInsideTheAdminFirewall(): void
    {
        $repository = $this->createRepository('admin');

        self::assertSame(['permission-aware'], $repository->findCollectionSet());
        self::assertCount(1, $this->permissionAware->calls);
        self::assertCount(0, $this->media->calls);
    }

    public function testUsesSulusRepositoryInsideTheWebsiteFirewall(): void
    {
        $repository = $this->createRepository('website');

        self::assertSame(['media'], $repository->findCollectionSet());
        self::assertCount(0, $this->permissionAware->calls);
        self::assertCount(1, $this->media->calls);
    }

    public function testUsesSulusRepositoryWithoutARequest(): void
    {
        $repository = new CombinedCollectionRepository(
            SecurityFactory::create(null, 'admin'),
            new RequestStack(),
            $this->permissionAware,
            $this->media,
        );

        self::assertSame(['media'], $repository->findCollectionSet());
    }

    public function testUsesSulusRepositoryWithoutAFirewall(): void
    {
        $repository = $this->createRepository(null);

        self::assertSame(['media'], $repository->findCollectionSet());
    }

    public function testDecidesPerCallWhichRepositoryIsUsed(): void
    {
        $requestStack = new RequestStack();
        $repository = new CombinedCollectionRepository(
            SecurityFactory::create(null, 'admin'),
            $requestStack,
            $this->permissionAware,
            $this->media,
        );

        self::assertSame(['media'], $repository->findCollectionSet());

        $requestStack->push(new Request());

        self::assertSame(['permission-aware'], $repository->findCollectionSet());
    }

    /**
     * @return iterable<string, array{string, array<mixed>}>
     */
    public static function delegatedMethodProvider(): iterable
    {
        $collection = new Collection();

        yield 'findCollectionSet' => ['findCollectionSet', [2, ['locale' => 'en'], null, ['title' => 'asc'], null, 64]];
        yield 'countCollections' => ['countCollections', [2, ['locale' => 'en'], null]];
        yield 'countMedia' => ['countMedia', [$collection]];
        yield 'countSubCollections' => ['countSubCollections', [$collection]];
        yield 'findCollectionById' => ['findCollectionById', [7]];
        yield 'findCollections' => ['findCollections', [['search' => 'x'], 10, 5, ['title' => 'desc']]];
        yield 'findCollectionBreadcrumbById' => ['findCollectionBreadcrumbById', [7]];
        yield 'findCollectionByKey' => ['findCollectionByKey', ['system_collections']];
        yield 'findTree' => ['findTree', [7, 'en']];
        yield 'findCollectionTypeById' => ['findCollectionTypeById', [7]];
        yield 'findDescendantCollectionResources' => ['findDescendantCollectionResources', [7]];
        yield 'countUnauthorizedDescendantCollections' => ['countUnauthorizedDescendantCollections', [7, new User(), 64]];
        yield 'findDescendantIdsById' => ['findDescendantIdsById', [7]];
        yield 'supportsDescendantType' => ['supportsDescendantType', [Collection::class]];
        yield 'find' => ['find', [7]];
        yield 'findAll' => ['findAll', []];
        yield 'findBy' => ['findBy', [['key' => 'x'], ['id' => 'asc'], 10, 5]];
        yield 'findOneBy' => ['findOneBy', [['key' => 'x']]];
        yield 'getClassName' => ['getClassName', []];
        yield 'getRootNodes' => ['getRootNodes', ['id', 'asc']];
        yield 'getNodesHierarchy' => ['getNodesHierarchy', [$collection, true, [], false]];
        yield 'getChildren' => ['getChildren', [$collection, true, 'id', 'ASC', false]];
        yield 'childCount' => ['childCount', [$collection, true]];
        yield 'childrenHierarchy' => ['childrenHierarchy', [$collection, true, [], false]];
        yield 'buildTree' => ['buildTree', [[], []]];
        yield 'buildTreeArray' => ['buildTreeArray', [[]]];
        yield 'setChildrenIndex' => ['setChildrenIndex', ['__children']];
        yield 'getChildrenIndex' => ['getChildrenIndex', []];
        yield 'matching' => ['matching', [new Criteria()]];
        yield 'findIdByMediaId (through __call)' => ['findIdByMediaId', [7]];
    }

    /**
     * @param array<mixed> $arguments
     */
    #[DataProvider('delegatedMethodProvider')]
    public function testForwardsMethodAndArgumentsToTheSelectedRepository(string $method, array $arguments): void
    {
        $repository = $this->createRepository('admin');

        $repository->{$method}(...$arguments);

        self::assertSame(
            ['method' => $method, 'arguments' => $arguments],
            $this->permissionAware->lastCall(),
        );
        self::assertCount(0, $this->media->calls);
    }

    private function createRepository(?string $firewall): CombinedCollectionRepository
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return new CombinedCollectionRepository(
            SecurityFactory::create(null, $firewall),
            $requestStack,
            $this->permissionAware,
            $this->media,
        );
    }
}
