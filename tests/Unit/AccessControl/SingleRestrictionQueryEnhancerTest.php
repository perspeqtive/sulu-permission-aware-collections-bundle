<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\AccessControl;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\AccessControl\SingleRestrictionQueryEnhancer;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Mocks\MockAccessControlQueryEnhancer;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\Collection;

final class SingleRestrictionQueryEnhancerTest extends TestCase
{
    private MockAccessControlQueryEnhancer $inner;

    private SingleRestrictionQueryEnhancer $enhancer;

    protected function setUp(): void
    {
        $this->inner = new MockAccessControlQueryEnhancer();
        $this->enhancer = new SingleRestrictionQueryEnhancer($this->inner);
    }

    public function testRestrictsAQueryBuilderOnce(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->enhancer->enhance($queryBuilder, null, 64, Collection::class, 'collection');
        $this->enhancer->enhance($queryBuilder, null, 64, Collection::class, 'collection');

        self::assertCount(1, $this->inner->enhanceCalls);
        self::assertStringEndsWith('WHERE ' . MockAccessControlQueryEnhancer::CONDITION, $queryBuilder->getDQL());
    }

    public function testRestrictsEveryQueryBuilderSeparately(): void
    {
        $this->enhancer->enhance($this->createQueryBuilder(), null, 64, Collection::class, 'collection');
        $this->enhancer->enhance($this->createQueryBuilder(), null, 64, Collection::class, 'collection');

        self::assertCount(2, $this->inner->enhanceCalls);
    }

    public function testPassesEveryArgumentToTheWrappedEnhancer(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->enhancer->enhance($queryBuilder, null, 64, Collection::class, 'collection', 'uuid');

        self::assertSame([[
            'queryBuilder' => $queryBuilder,
            'user' => null,
            'permission' => 64,
            'entityClass' => Collection::class,
            'entityAlias' => 'collection',
            'entityIdField' => 'uuid',
        ]], $this->inner->enhanceCalls);
    }

    public function testRestrictsWithADynamicEntityClassOnce(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->enhancer->enhanceWithDynamicEntityClass($queryBuilder, null, 64, Collection::class, 'collection', 'entityClass', 'id');
        $this->enhancer->enhanceWithDynamicEntityClass($queryBuilder, null, 64, Collection::class, 'collection', 'entityClass', 'id');

        self::assertCount(1, $this->inner->enhanceWithDynamicEntityClassCalls);
    }

    public function testDoesNotRestrictAQueryBuilderTwiceAcrossBothMethods(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->enhancer->enhance($queryBuilder, null, 64, Collection::class, 'collection');
        $this->enhancer->enhanceWithDynamicEntityClass($queryBuilder, null, 64, Collection::class, 'collection', 'entityClass', 'id');

        self::assertCount(1, $this->inner->enhanceCalls);
        self::assertCount(0, $this->inner->enhanceWithDynamicEntityClassCalls);
    }

    public function testReportsWhetherAQueryBuilderIsRestricted(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        self::assertFalse($this->enhancer->isRestricted($queryBuilder));

        $this->enhancer->enhance($queryBuilder, null, 64, Collection::class, 'collection');

        self::assertTrue($this->enhancer->isRestricted($queryBuilder));
        self::assertFalse($this->enhancer->isRestricted($this->createQueryBuilder()));
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this->createStub(EntityManagerInterface::class));
        $queryBuilder->select('collection')->from(Collection::class, 'collection');

        return $queryBuilder;
    }
}
