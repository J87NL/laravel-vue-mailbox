# Installation

## Requirements

- PHP 8.4+
- Laravel 11, 12 or 13 (as required by `j87nl/laravel-mailbox`)
- Inertia (`inertiajs/inertia-laravel` v2 or v3) with Vue 3
- `tranquil-tools/laravel-vue-form-builder` ^1.10 and `tranquil-tools/laravel-vue-table-builder` ^1.9, with their
  `@form-builder` and `@table-builder` Vite aliases set up as their own docs describe

`j87nl/laravel-mailbox` ^0.3.2 and both TranquilTools packages are Composer dependencies, so they are installed with
this package.

## 1. Install via Composer

```bash
composer require j87nl/laravel-vue-mailbox
```

This package is the user interface. It pulls in
[`j87nl/laravel-mailbox`](https://github.com/J87NL/laravel-mailbox), the core that holds the models, migrations,
config, IMAP sync, sending, access abilities and audit trail, as a dependency. A single `require` is all you need. Both
service providers, `J87NL\VueMailbox\VueMailboxServiceProvider` and `J87NL\Mailbox\MailboxServiceProvider`, are
auto-discovered.

The UI only calls the core. The core steps in [step 7](#7-set-up-the-core-package) are therefore required, not
optional.

## 2. Run the installer

```bash
php artisan mailbox:install
php artisan migrate
```

`mailbox:install` publishes the core config (`config/mailbox.php`), the core migrations and this package's config
(`config/vue-mailbox.php`). It adds the `@mailbox` alias to your Vite config and the `@mailbox/*` path to
`tsconfig.json`. Running it again changes nothing that is already there. See [Artisan commands](artisan-commands.md).

If the installer cannot patch your Vite config, add the alias yourself:

```ts
import path from 'path';

export default defineConfig({
    resolve: {
        alias: {
            '@mailbox': path.resolve(__dirname, 'vendor/j87nl/laravel-vue-mailbox/resources/js'),
        },
    },
});
```

And the path in `tsconfig.json`:

```json
{
    "compilerOptions": {
        "paths": {
            "@mailbox/*": ["./vendor/j87nl/laravel-vue-mailbox/resources/js/*"]
        }
    }
}
```

## 3. Make the pages resolvable

The controllers render Inertia pages by name, such as `Mailbox/Index` (see [Configuration](configuration.md#pages)).
Your Inertia page resolver has to find them. The package does not register a resolver of its own.

The simplest way is to publish the default pages into your app:

```bash
php artisan mailbox:install --pages
```

This copies them to `resources/js/pages/Mailbox`. See [Customizing pages](frontend/customizing-pages.md).

Alternatively, leave the pages in the package and let your resolver also look there. For example, in
`resources/js/app.ts`:

```ts
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';

createInertiaApp({
    resolve: (name) => resolvePageComponent(
        name.startsWith('Mailbox/')
            ? `../../vendor/j87nl/laravel-vue-mailbox/resources/js/pages/${name}.vue`
            : `./pages/${name}.vue`,
        {
            ...import.meta.glob<DefineComponent>('./pages/**/*.vue'),
            ...import.meta.glob<DefineComponent>('../../vendor/j87nl/laravel-vue-mailbox/resources/js/pages/**/*.vue'),
        },
    ),
    // ...
});
```

Adapt this to the resolver your app already has. With this setup, package updates change the pages too.

## 4. Install the compose editor

The compose form uses the WYSIWYG editor set in the core config. It defaults to HugeRTE:

```bash
npm install hugerte @hugerte/hugerte-vue
```

Register it in your app entrypoint:

```ts
import HugeRteEditor from '@form-builder/wysiwyg/HugeRteEditor.vue';
import { registerWysiwygEditor } from '@form-builder/wysiwyg/registry';
import 'hugerte/plugins/autolink';
import 'hugerte/plugins/link';
import 'hugerte/plugins/lists';

registerWysiwygEditor('hugerte', HugeRteEditor);
```

The editor must keep the `data-mailbox-cid` attribute on images, or quoted inline images are lost when you send a reply
or forward. The default HugeRTE options in the core config allow it. See [Inline images](inline-images.md).

## 5. Add the Tailwind CSS source

The pages use plain Tailwind utility classes and no host UI kit. Let Tailwind scan the package, for example in
Tailwind 4 in `resources/css/app.css`:

```css
@source '../../vendor/j87nl/laravel-vue-mailbox/resources/js';
```

## 6. Build assets

```bash
npm run build
```

## 7. Set up the core package

The UI shows and changes what the core stores, so the core has to be set up. The steps are documented in the
[core installation guide](https://github.com/J87NL/laravel-mailbox/blob/main/docs/installation.md); this is what the UI
needs from it:

- **Migrations.** `mailbox:install` publishes them; run `php artisan migrate`. Every page reads the core tables.
- **[Admins](https://github.com/J87NL/laravel-mailbox/blob/main/docs/installation.md#bootstrap-an-admin)**
  (`MAILBOX_ADMINS`). Creating a mailbox on the [settings page](pages/settings.md) needs `manageMailboxes`, which only
  admins pass unless your app defines that ability itself. `php artisan mailbox:add` always works.
- **[User model](https://github.com/J87NL/laravel-mailbox/blob/main/docs/configuration.md#user-model)**
  (`MAILBOX_USER_MODEL`), when your user model is not `App\Models\User`. The access form and the message history use it.
- **[Access mode and groups](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md).** They decide who
  passes the abilities the routes check, and which users and groups the access form offers.
- **[Sync schedule](https://github.com/J87NL/laravel-mailbox/blob/main/docs/installation.md#schedule-the-sync)**
  (`mailbox:sync`) **and a queue worker.** The message list shows what the sync stored. The folder page also queues
  `SyncMailboxJob` and `SyncFolderJob` itself, and saving a mailbox queues a sync.
- **[Body cache pruning](https://github.com/J87NL/laravel-mailbox/blob/main/docs/artisan-commands.md#mailboxprune-bodies)**
  (`mailbox:prune-bodies`). Recommended: opened bodies stay cached until it runs.
- **[OAuth token provider](https://github.com/J87NL/laravel-mailbox/blob/main/docs/extension-points.md#providesaccesstoken)**,
  only for mailboxes with OAuth authentication. The settings form offers OAuth, but the core fetches no tokens itself.

Access levels are granted on the [settings page](pages/settings.md#access) or with `php artisan mailbox:grant`.
