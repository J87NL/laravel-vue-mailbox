# J87 Laravel Vue Mailbox

Vue 3 / Inertia UI for [`j87nl/laravel-mailbox`](https://github.com/J87NL/laravel-mailbox): folder list, message list (TableBuilder), message view in a sandboxed iframe, compose/reply/forward (FormBuilder) and mailbox settings with access management.

## Requirements

- PHP 8.4+, Laravel with Inertia (v2 or v3) and Vue 3
- `tranquil-tools/laravel-vue-form-builder` and `tranquil-tools/laravel-vue-table-builder`, with their `@form-builder` and `@table-builder` Vite aliases

## Installation

```bash
composer require j87nl/laravel-vue-mailbox
php artisan mailbox:install
php artisan migrate
```

`mailbox:install` publishes the core and Vue config and the migrations, adds the `@mailbox` Vite alias (`./vendor/j87nl/laravel-vue-mailbox/resources/js`) and the `@mailbox/*` tsconfig path, and with `--pages` copies the default pages to `resources/js/pages/Mailbox` for customising. Running it again changes nothing that is already there.

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

## Pages and props

The default pages read their texts from the shared Inertia prop `vue_mailbox_translations` (the core `mailbox::mailbox` file, nl and en) through `useMailboxTranslations()` from `@mailbox/translations`.

| Page (`vue-mailbox.pages.*`) | Props |
|---|---|
| `Mailbox/Index` | `composeUrl`, `contactsUrl`, `folder` (`id`, `label`, `name`, `role`) or null, `folderForm`, `folders`, `mailbox`, `mailboxes`, `settingsUrl`, `syncing`, `table` |
| `Mailbox/Show` | `actionsUrl`, `attachments`, `backUrl`, `composeUrl`, `contactUrl`, `folders`, `hasRemoteImages`, `history`, `html`, `imageOrigin`, `mailbox`, `message`, `remoteImages` |
| `Mailbox/Compose` | `backUrl`, `folders`, `form`, `mailbox`, `mode` |
| `Mailbox/Contacts/Index` | `backUrl`, `createUrl`, `folders`, `mailbox`, `table` |
| `Mailbox/Contacts/Edit` | `backUrl`, `destroyUrl`, `form`, `mailbox`, `title` |
| `Mailbox/Settings/Index`, `Mailbox/Settings/Edit` | unchanged |

The message table polls every `vue-mailbox.poll_seconds` (30); pass `:only="['table']"` to `TableBuilder` so a tick reloads only the table. Unread rows get `font-semibold` from the server.

## Routes

All routes live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`) with the `web` and `auth` middleware. Menu links: `route('mailbox.index')` (show it with `@can('accessAnyMailbox')` / `$can('accessAnyMailbox')`) and `route('mailbox.settings.index')`. Every route is gated with the core abilities (`accessMailbox`, `workInMailbox`, `manageMailbox`, `manageMailboxes`); the pages hide controls the user cannot use.

| Route | Ability |
|---|---|
| `mailbox.contacts.index` `GET {mailbox}/contacts` | `accessMailbox` |
| `mailbox.contacts.create`, `.store`, `.edit`, `.update`, `.destroy` | `workInMailbox` |
| `mailbox.messages.contact` `POST {mailbox}/messages/{message}/contact` (add the sender to the address book) | `workInMailbox` |
| `mailbox.addresses` `GET {mailbox}/addresses?q=` (recipient suggestions, JSON) | `workInMailbox` |

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
