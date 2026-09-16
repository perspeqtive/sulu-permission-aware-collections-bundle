<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Doctrine;

use Doctrine\ORM\QueryBuilder;

final class AppendOnlyWhereQueryBuilder extends QueryBuilder
{
    public function where(mixed ...$predicates): static
    {
        return $this->andWhere(...$predicates);
    }
}
