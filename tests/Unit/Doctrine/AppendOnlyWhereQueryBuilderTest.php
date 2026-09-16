<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Doctrine\AppendOnlyWhereQueryBuilder;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\Collection;

final class AppendOnlyWhereQueryBuilderTest extends TestCase
{
    public function testWhereKeepsTheConditionsAddedBefore(): void
    {
        $queryBuilder = $this->createQueryBuilder()
            ->andWhere('acl_restriction = 1')
            ->where('collection.id = :id');

        self::assertSame(
            'SELECT collection FROM ' . Collection::class . ' collection WHERE acl_restriction = 1 AND collection.id = :id',
            $queryBuilder->getDQL(),
        );
    }

    public function testWhereAcceptsSeveralPredicates(): void
    {
        $queryBuilder = $this->createQueryBuilder()
            ->andWhere('acl_restriction = 1')
            ->where('collection.id = :id', 'collection.depth = :depth');

        self::assertStringEndsWith(
            'WHERE acl_restriction = 1 AND collection.id = :id AND collection.depth = :depth',
            $queryBuilder->getDQL(),
        );
    }

    public function testOrWhereBehavesLikeInTheParentQueryBuilder(): void
    {
        $queryBuilder = $this->createQueryBuilder()
            ->where('collection.id = :id')
            ->orWhere('collection.parent IS NULL');

        self::assertStringEndsWith(
            'WHERE collection.id = :id OR collection.parent IS NULL',
            $queryBuilder->getDQL(),
        );
    }

    public function testDoctrinesQueryBuilderDropsTheConditionsAddedBefore(): void
    {
        // documents the behaviour AppendOnlyWhereQueryBuilder exists for
        $queryBuilder = (new QueryBuilder($this->createEntityManager()))
            ->select('collection')
            ->from(Collection::class, 'collection')
            ->andWhere('acl_restriction = 1')
            ->where('collection.id = :id');

        self::assertStringEndsWith('WHERE collection.id = :id', $queryBuilder->getDQL());
    }

    private function createQueryBuilder(): AppendOnlyWhereQueryBuilder
    {
        $queryBuilder = new AppendOnlyWhereQueryBuilder($this->createEntityManager());
        $queryBuilder->select('collection')->from(Collection::class, 'collection');

        return $queryBuilder;
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        return $entityManager;
    }
}
