# SuluPermissionAwareCollectionsBundle
![Static Badge](https://img.shields.io/badge/sulu_compatibility-%5E2.6_%7C%7C_%5E3.0-DCE5E4?labelColor=112A46)

The **Sulu Permission Aware Collections Bundle** applies the access control of media collections to
*every* collection query in the Sulu admin. Sulu itself filters collections by permission only where it
asks for it explicitly — the collection tree, the sub collection and media counters, breadcrumbs and the
collection search still show collections the current user is not allowed to view. This bundle moves the
permission filter into the repository, so a collection without view permission is invisible everywhere
in the admin, while the website keeps seeing all collections.

No configuration is needed: the permissions you already maintain per collection in the Sulu admin are
the ones being applied.

## 🚀 Features
- Collections without view permission disappear from the collection tree, the media overview, the
  breadcrumb and the collection search
- Uses the permissions configured per collection in the Sulu admin — no additional configuration
- Only active inside the `admin` firewall, the website is served by Sulu's own repository
- Registers as access control descendant provider, so permissions keep being inherited to sub
  collections
- Honours a custom enhancer registered through the
  [Sulu Access Control Custom Query Enhancer Bundle](https://github.com/perspeqtive/sulu-access-control-custom-query-enhancer-bundle)
- No core patching — the bundle decorates `sulu_media.collection_repository`

## 📋 Requirements

| Requirement                                                     | Version |
|:----------------------------------------------------------------|:--------|
| PHP                                                             | `^8.2`  |
| Sulu                                                            | `^3.0`  |
| Doctrine ORM                                                    | `^3.3`  |
| `perspeqtive/sulu-access-control-custom-query-enhancer-bundle`  | `^3.0`  |

Sulu 3 still allows Doctrine ORM 2, this bundle does not: it extends Doctrine's `QueryBuilder`, whose
signatures changed with ORM 3. Make sure your project is not pinned to `doctrine/orm: ^2`.

## 🛠️ Installation

Neither this bundle nor the access control enhancer bundle is published on Packagist, so add both
repositories to your project's `composer.json` first:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/perspeqtive/sulu-permission-aware-collections-bundle.git"
        },
        {
            "type": "vcs",
            "url": "https://github.com/perspeqtive/sulu-access-control-custom-query-enhancer-bundle.git"
        }
    ]
}
```

### Install the bundle via Composer:

```bash
composer require perspeqtive/sulu-permission-aware-collections-bundle
```

### Enable the bundles

Register them in your `config/bundles.php`:

```php
return [
// ...
    PERSPEQTIVE\SuluAccessControlCustomQueryEnhancerBundle\SuluAccessControlCustomQueryEnhancerBundle::class => ['all' => true],
    PERSPEQTIVE\SuluPermissionAwareCollectionsBundle\SuluPermissionAwareCollectionsBundle::class => ['all' => true],
];
```

That is all the setup there is — the bundle has no configuration.

## 🛠️ Usage

Assign the permissions as usual in the Sulu admin: **Media** → select a collection → **Permissions**,
then grant or revoke *view* for the roles in question. From that moment on the collection is only part
of any admin query for users whose role has the view permission.

Permissions are inherited: revoking view on a collection hides its sub collections as well.

### What the bundle filters

| Query                                                                                                              | Filtered |
|:-------------------------------------------------------------------------------------------------------------------|:---------|
| Collection lists and search (`findCollectionSet()`, `findCollections()`)                                            | ✅        |
| Number of collections (`countCollections()`)                                                                        | ✅        |
| Lookups by key or type (`findCollectionByKey()`, `findCollectionTypeById()`)                                        | ✅        |
| Descendant resources (`findDescendantCollectionResources()`)                                                        | ✅        |
| The collection tree (`findTree()`)                                                                                  | partly   |
| Media and sub collection counters (`countMedia()`, `countSubCollections()`)                                         | partly   |
| `findCollectionById()` and `findCollectionBreadcrumbById()`                                                         | ❌        |
| Doctrine's own `find()`, `findBy()`, `findOneBy()` and the Gedmo tree helpers                                       | ❌        |
| `findDescendantIdsById()` and `countUnauthorizedDescendantCollections()`                                            | ❌        |

- **The collection tree** hides restricted branches, but not collections without a parent: Sulu
  combines the condition of the tree query with `OR parent IS NULL`, which ors the restriction away
  for top level collections.
- **The counters** are restricted to collections the user may see, but what they count is not: the
  number of sub collections of a visible collection also contains sub collections without view
  permission.
- **The unfiltered lookups** build their queries without a query builder, which is why they cannot
  carry the restriction. Opening a single collection is guarded by Sulu's security checker, so a user
  still cannot open a collection they have no permission for — they only reach it through a direct id.
- **The descendant queries** stay unrestricted on purpose: Sulu uses them to inherit permissions to
  sub collections and to count the descendants a user may *not* see, which both need the full set.

The permissions are evaluated per collection. Sulu stores them for the descendants of a collection as
well when they are saved in the admin, so a restricted collection hides its sub collections - but only
because those carry their own access control records, not because the query walks the tree.

### How it works

| Class                              | Role                                                                                                                                  |
|:-----------------------------------|:--------------------------------------------------------------------------------------------------------------------------------------|
| `CombinedCollectionRepository`     | Decorates `sulu_media.collection_repository` and delegates to the permission aware repository inside the `admin` firewall, to Sulu's own repository everywhere else |
| `CollectionRepository`             | Extends Sulu's collection repository and adds the view permission restriction to every query builder it creates                        |
| `AppendOnlyWhereQueryBuilder`      | Keeps that restriction in place when Sulu's repository sets its own condition through `where()`                                        |
| `SingleRestrictionQueryEnhancer`   | Makes sure a query builder is restricted only once - Sulu names the aliases of its restriction statically, so a query carrying it twice is rejected by Doctrine |

## ⬆️ Upgrade from 1.x to 2.x (Sulu 3)

The public interface of the bundle did not change: service ids, class names, the decoration of
`sulu_media.collection_repository` and the bundle registration stay the same, and there is still no
configuration. Projects only need to update their constraints:

```bash
composer require perspeqtive/sulu-permission-aware-collections-bundle:^2.0
```
