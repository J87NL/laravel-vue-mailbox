# J87 Laravel Vue Mailbox

Vue 3 / Inertia UI for [`j87nl/laravel-mailbox`](https://github.com/J87NL/laravel-mailbox): folder list, message list (TableBuilder), message view in a sandboxed iframe, compose/reply/forward (FormBuilder) and mailbox settings with access management.

## Requirements

- PHP 8.4+, Laravel with Inertia (v2 or v3) and Vue 3
- `tranquil-tools/laravel-vue-form-builder` and `tranquil-tools/laravel-vue-table-builder`, with their `@form-builder` and `@table-builder` Vite aliases

## Installation

```bash
composer require j87nl/laravel-vue-mailbox
php artisan vendor:publish --tag=mailbox-migrations
php artisan migrate
php artisan vendor:publish --tag=vue-mailbox-config
php artisan vendor:publish --tag=vue-mailbox-pages
```

Add the `@mailbox` alias to `vite.config.ts` (and the matching `paths` entry in `tsconfig.json`):

```ts
'@mailbox': path.resolve(__dirname, 'vendor/j87nl/laravel-vue-mailbox/resources/js'),
```

The compose editor defaults to HugeRTE:

```bash
npm install hugerte @hugerte/hugerte-vue
```

```ts
import HugeRteEditor from '@form-builder/wysiwyg/HugeRteEditor.vue'
import { registerWysiwygEditor } from '@form-builder/wysiwyg/registry'
import 'hugerte/plugins/autolink'
import 'hugerte/plugins/link'
import 'hugerte/plugins/lists'

registerWysiwygEditor('hugerte', HugeRteEditor)
```

The pages use plain Tailwind utility classes and no host UI kit. Let Tailwind scan the package so those classes are generated, e.g. in Tailwind 4:

```css
@source '../../vendor/j87nl/laravel-vue-mailbox/resources/js';
```

Then follow the core package README for admins, the sync schedule, the queue worker and the configuration keys (`MAILBOX_DISPLAY_TIMEZONE`, `MAILBOX_CONNECTION_TIMEOUT`, …).

## Routes

All routes live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`) with the `web` and `auth` middleware. Menu links: `route('mailbox.index')` (show it with `@can('accessAnyMailbox')` / `$can('accessAnyMailbox')`) and `route('mailbox.settings.index')`. Every route is gated with the core abilities (`accessMailbox`, `workInMailbox`, `manageMailbox`, `manageMailboxes`); the pages hide controls the user cannot use.

## Development

The core package comes from Packagist (`j87nl/laravel-mailbox`). To work against a local checkout of the core, add a path repository temporarily and do not commit it:

```bash
composer config repositories.core path ../laravel-mailbox
composer require j87nl/laravel-mailbox:@dev
# when done
git checkout composer.json && composer update j87nl/laravel-mailbox
```

```bash
composer install
composer test
composer analyse
composer format
```
