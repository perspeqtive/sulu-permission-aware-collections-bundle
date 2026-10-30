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

## 🛠️ Installation

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

## 👩‍🍳 Contribution

Please feel free to fork and extend existing or add new features and send a pull request with your changes! To establish a consistent code quality, please provide unit tests for all your changes and adapt the documentation.