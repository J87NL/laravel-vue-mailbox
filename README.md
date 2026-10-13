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

Then follow the core package README for admins, the sync schedule and the queue worker.

## Routes

All routes live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`) with the `web` and `auth` middleware. Menu links: `route('mailbox.index')` and `route('mailbox.settings.index')`. Every route is gated with the core abilities (`accessMailbox`, `workInMailbox`, `manageMailbox`, `manageMailboxes`); the pages hide controls the user cannot use.

## Development

The core package is required through a Composer path repository (`../laravel-mailbox`), so check out both repositories side by side.

```bash
composer install
composer test
composer analyse
composer format
```
