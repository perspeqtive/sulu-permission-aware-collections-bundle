<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Listener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository\CollectionRepository;
use Sulu\Bundle\MediaBundle\Entity\Collection;

class OverrideCollectionRepositoryListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        /** @var ClassMetadata $metadata */
        $metadata = $args->getClassMetadata();

        if ($metadata->getName() !== Collection::class) {
            return;
        }

        $metadata->setCustomRepositoryClass(CollectionRepository::class);
    }
}