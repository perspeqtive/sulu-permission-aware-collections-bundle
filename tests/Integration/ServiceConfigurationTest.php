<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository\CollectionRepository;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Repository\CombinedCollectionRepository;
use PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\SuluPermissionAwareCollectionsBundle;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionRepository as SuluCollectionRepository;
use Sulu\Bundle\SecurityBundle\AccessControl\AccessControlQueryEnhancer;
use Sulu\Bundle\SecurityBundle\System\SystemStoreInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

use function sys_get_temp_dir;

class ServiceConfigurationTest extends TestCase
{
    public function testContainerCompilesWithTheServicesOfTheBundle(): void
    {
        $container = $this->buildContainer();

        $container->compile();

        self::assertTrue($container->hasDefinition('sulu_media.collection_repository'));
        self::assertSame(
            CombinedCollectionRepository::class,
            $container->getDefinition('sulu_media.collection_repository')->getClass(),
        );
    }

    public function testDecoratesSulusCollectionRepository(): void
    {
        $container = $this->buildContainer();

        $definition = $container->getDefinition(CombinedCollectionRepository::class);

        self::assertSame(['sulu_media.collection_repository', null, 0], $definition->getDecoratedService());
        self::assertSame('.inner', (string) $definition->getArgument('$mediaCollectionRepository'));
        self::assertSame(
            CollectionRepository::class,
            (string) $definition->getArgument('$permissionAwareCollectionRepository'),
        );
    }

    public function testKeepsSulusRepositoryAsTheDecoratedService(): void
    {
        $container = $this->buildContainer();

        $container->compile();

        $inner = $container->getDefinition('sulu_media.collection_repository')->getArgument(3);
        $innerClass = $inner instanceof Definition
            ? $inner->getClass()
            : $container->getDefinition((string) $inner)->getClass();

        self::assertSame(SuluCollectionRepository::class, $innerClass);
    }

    public function testRegistersThePermissionAwareRepositoryAsDescendantProvider(): void
    {
        $container = $this->buildContainer();

        $definition = $container->getDefinition(CollectionRepository::class);

        self::assertTrue($definition->hasTag('sulu_security.access_control_descendant_provider'));
        self::assertSame(CollectionRepository::class, $definition->getClass());
    }

    public function testAliasesSulusRepositoryClassToTheDecorator(): void
    {
        $container = $this->buildContainer();

        $alias = $container->getAlias(SuluCollectionRepository::class);

        self::assertSame(CombinedCollectionRepository::class, (string) $alias);
    }

    public function testTypeDeclarationsOfTheServicesAreSatisfied(): void
    {
        $container = $this->buildContainer();
        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);

        $container->compile();

        self::assertTrue($container->hasDefinition(CollectionRepository::class));
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('sulu_media.collection_entity', Collection::class);
        $container->setParameter('sulu_security.permissions', [
            'view' => 64,
            'add' => 32,
            'edit' => 16,
            'delete' => 8,
            'archive' => 4,
            'live' => 2,
            'security' => 1,
        ]);

        // the Sulu and Symfony services the bundle configuration references
        $container->register('doctrine.orm.entity_manager', EntityManagerInterface::class)->setPublic(true);
        $container->register('security.helper', Security::class)
            ->setPublic(true)
            ->setArguments([new Reference('service_container')]);
        $container->register('request_stack', RequestStack::class)->setPublic(true);
        $container->register('sulu_security.system_store', SystemStoreInterface::class)->setPublic(true);
        $container->register('sulu_security.access_control_query_enhancer', AccessControlQueryEnhancer::class)
            ->setPublic(true)
            ->setArguments([
                new Reference('sulu_security.system_store'),
                new Reference('doctrine.orm.entity_manager'),
            ]);
        $container->register('sulu_media.collection_repository', SuluCollectionRepository::class)
            ->setPublic(true)
            ->setArguments(['%sulu_media.collection_entity%'])
            ->setFactory([new Reference('doctrine.orm.entity_manager'), 'getRepository'])
            ->addTag('sulu_security.access_control_descendant_provider');

        $bundle = new SuluPermissionAwareCollectionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);

        $extension->load([], $container);

        return $container;
    }
}
