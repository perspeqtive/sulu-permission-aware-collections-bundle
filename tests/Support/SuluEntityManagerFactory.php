<?php

declare(strict_types=1);

namespace PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Gedmo\Tree\TreeListener;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionInterface;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Persistence\EventSubscriber\ORM\MetadataSubscriber;
use Sulu\Component\Persistence\EventSubscriber\ORM\TimestampableSubscriber;
use Sulu\Component\Persistence\EventSubscriber\ORM\UserBlameSubscriber;
use Sulu\Component\Security\Authentication\RoleInterface;
use Sulu\Component\Security\Authentication\UserInterface;

use function dirname;
use function glob;
use function simplexml_load_file;
use function str_ends_with;
use function strrpos;
use function substr;
use function sys_get_temp_dir;

/**
 * Builds an entity manager for Sulu's entities on an in-memory SQLite database.
 *
 * The mappings, the interface resolution and the metadata subscribers are wired the same way a
 * Sulu application wires them, so the queries built by the repository run against the real Sulu
 * schema instead of a simplified test schema.
 */
final class SuluEntityManagerFactory
{
    public static function create(): EntityManagerInterface
    {
        $mappings = self::collectMappings();

        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new SimplifiedXmlDriver($mappings['prefixes'], '.orm.xml', false));
        $configuration->setProxyDir(sys_get_temp_dir() . '/sulu-permission-aware-collections-proxies');
        $configuration->setProxyNamespace('SuluPermissionAwareCollectionsTestProxies');

        $eventManager = new EventManager();

        // promotes Sulu's mapped superclasses (Collection, Role, User, ...) to entities
        $eventManager->addEventListener(
            [Events::loadClassMetadata],
            new MetadataSubscriber(['test' => $mappings['models']]),
        );
        // maps the creator and changer associations
        $eventManager->addEventListener([Events::loadClassMetadata], new UserBlameSubscriber());
        // maps the created and changed fields
        $eventManager->addEventListener(
            [Events::loadClassMetadata, Events::prePersist, Events::preUpdate],
            new TimestampableSubscriber(),
        );
        $eventManager->addEventSubscriber(new TreeListener());
        $eventManager->addEventSubscriber(self::createResolveTargetEntityListener($mappings['interfaces']));

        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $configuration,
            $eventManager,
        );

        $entityManager = new EntityManager($connection, $configuration, $eventManager);

        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    /**
     * @return array{
     *     prefixes: array<string, string>,
     *     models: array<string, array{model: class-string}>,
     *     interfaces: array<class-string, class-string>
     * }
     */
    private static function collectMappings(): array
    {
        $prefixes = [];
        $models = [];
        $entitiesByShortName = [];
        $referencedInterfaces = [];

        foreach (glob(self::suluSourceDirectory() . '/Bundle/*/Resources/config/doctrine') ?: [] as $directory) {
            foreach (glob($directory . '/*.orm.xml') ?: [] as $file) {
                $xml = simplexml_load_file($file);
                if (false === $xml) {
                    continue;
                }

                foreach (['entity', 'mapped-superclass'] as $element) {
                    foreach ($xml->{$element} as $mapping) {
                        /** @var class-string $class */
                        $class = (string) $mapping['name'];
                        $entitiesByShortName[self::shortName($class)] = $class;
                        $prefixes[$directory] ??= self::namespaceOf($class);

                        if ('mapped-superclass' === $element) {
                            $models[$class] = ['model' => $class];
                        }
                    }
                }

                foreach ($xml->xpath('//*[@target-entity]') ?: [] as $association) {
                    /** @var class-string $target */
                    $target = (string) $association['target-entity'];
                    if (str_ends_with($target, 'Interface')) {
                        $referencedInterfaces[$target] = substr(self::shortName($target), 0, -9);
                    }
                }
            }
        }

        // the entity of an interface carries the same short name, e.g. TagInterface -> Tag
        $interfaces = [];
        foreach ($referencedInterfaces as $interface => $shortName) {
            if (isset($entitiesByShortName[$shortName])) {
                $interfaces[$interface] = $entitiesByShortName[$shortName];
            }
        }

        // interfaces which do not live in the namespace of their entity
        $interfaces[UserInterface::class] = User::class;
        $interfaces[RoleInterface::class] = Role::class;
        $interfaces[CollectionInterface::class] = Collection::class;
        $interfaces[MediaInterface::class] = Media::class;

        return ['prefixes' => $prefixes, 'models' => $models, 'interfaces' => $interfaces];
    }

    /**
     * @param array<class-string, class-string> $interfaces
     */
    private static function createResolveTargetEntityListener(array $interfaces): ResolveTargetEntityListener
    {
        $listener = new ResolveTargetEntityListener();
        foreach ($interfaces as $interface => $class) {
            $listener->addResolveTargetEntity($interface, $class, []);
        }

        return $listener;
    }

    private static function suluSourceDirectory(): string
    {
        return dirname(__DIR__, 2) . '/vendor/sulu/sulu/src/Sulu';
    }

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    private static function namespaceOf(string $class): string
    {
        return substr($class, 0, (int) strrpos($class, '\\'));
    }
}
