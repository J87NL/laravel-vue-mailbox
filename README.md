# J87 Laravel Vue Mailbox

Vue 3 / Inertia UI for [`j87nl/laravel-mailbox`](https://github.com/J87NL/laravel-mailbox): folder list, message list (TableBuilder), message view in a sandboxed iframe, compose/reply/forward (FormBuilder) and mailbox settings with access management.

## Requirements

- PHP 8.4+, Laravel with Inertia (v2 or v3) and Vue 3
- `tranquil-tools/laravel-vue-form-builder` and `tranquil-tools/laravel-vue-table-builder`, with their `@form-builder` and `@table-builder` Vite aliases

## Installation

```bash
composer require j87nl/laravel-vue-mailbox:^0.10 -W
php artisan mailbox:install
php artisan migrate
```

Name the version and pass `-W`: with older `tranquil-tools/*` packages locked, a plain `composer require` silently installs an old 0.1.x release instead of updating them. See [Installation](docs/installation.md#1-install-via-composer).

`mailbox:install` publishes the core and Vue config and the migrations, and adds the `@mailbox` Vite alias (`./vendor/j87nl/laravel-vue-mailbox/resources/js`, in an object or array form of `resolve.alias`) and the `@mailbox/*` tsconfig path. Running it again changes nothing that is already there.

### Pages

The Vue pages stay in the package, like those of `j87nl/laravel-vue-cms`. The controllers render them by name, and every default name starts with `mailbox/` (`mailbox/Index`, `mailbox/Show`, `mailbox/Compose`, `mailbox/Contacts/Index`, `mailbox/Contacts/Edit`, `mailbox/Settings/Index`, `mailbox/Settings/Edit`); the rest of the name is the path below the package's `resources/js/pages`. The package registers no Inertia resolver, so let your app's resolver load those names from `@mailbox/pages`:

```ts
// resources/js/app.ts
type PageModule = { default: DefineComponent };

const appPages = import.meta.glob<PageModule>('./pages/**/*.vue');
const mailboxPages = import.meta.glob<PageModule>('@mailbox/pages/**/*.vue');

function findPage(pages: Record<string, () => Promise<PageModule>>, name: string) {
    const key = Object.keys(pages).find((path) => path.endsWith(`/pages/${name}.vue`));

    return key === undefined ? undefined : pages[key];
}

resolve: async (name) => {
    const page = name.startsWith('mailbox/')
        ? findPage(mailboxPages, name.slice('mailbox/'.length))
        : appPages[`./pages/${name}.vue`];

    if (page === undefined) {
        throw new Error(`Page not found: ${name}`);
    }

    return (await page()).default;
},
```

The pages set no layout: wrap them in yours in the resolver (`page.layout ??= Layout`). An app on `resolvePageComponent()` with `./Pages` keeps it for its own pages and only adds the `mailbox/` branch, see [Installation](docs/installation.md#3-load-the-pages-from-the-package). Provide a confirmation dialog under the key `confirm`, or deleting a message, contact or mailbox does nothing. An app on the `@inertiajs/vite` plugin without a `resolve` of its own adds this one (the plugin then leaves it alone), and an app with SSR uses the same resolver in `resources/js/ssr.ts`.

To replace one page, point its key in `config/vue-mailbox.php` (`vue-mailbox.pages.*`) at a page of your own. To copy every page into your app instead, run `php artisan mailbox:install --pages`: it copies them to `resources/js/pages/Mailbox` and points the default names in `config/vue-mailbox.php` at the copies (`Mailbox/Index`, ...). See [Installation](docs/installation.md), [Customizing pages](docs/frontend/customizing-pages.md) and, when you update from 0.4, [Upgrading](docs/upgrading.md).

The compose editor defaults to HugeRTE:

```bash
npm install hugerte @hugerte/hugerte-vue
```

```ts
import { registerWysiwygEditor } from '@form-builder/wysiwyg/registry'
import { defineAsyncComponent } from 'vue'

registerWysiwygEditor(
    'hugerte',
    defineAsyncComponent(async () => {
        const editor = await import('@form-builder/wysiwyg/HugeRteEditor.vue')

        await Promise.all([
            import('hugerte/plugins/autolink'),
            import('hugerte/plugins/link'),
            import('hugerte/plugins/lists'),
        ])

        return editor
    }),
)
```

Loading it lazily keeps about 1 MB out of the main bundle. The plugins are imported after the editor because they need `globalThis.hugerte`.

The pages use plain Tailwind utility classes and no host UI kit. Let Tailwind scan the package so those classes are generated, e.g. in Tailwind 4:

```css
@source '../../vendor/j87nl/laravel-vue-mailbox/resources/js';
```

Then follow the core package README for admins, the sync schedule, the queue worker and the configuration keys (`MAILBOX_DISPLAY_TIMEZONE`, `MAILBOX_CONNECTION_TIMEOUT`, …).

## Pages and props

The default pages read their texts from the Inertia prop `vue_mailbox_translations` (the core `mailbox::mailbox` file, nl and en) through `useMailboxTranslations()` from `@mailbox/translations`. The empty state texts come from this package's own `vue-mailbox::mailbox` file (nl and en) and arrive as props. Deletes ask for confirmation through the function the host provides under the Vue injection key `confirm` (`app.provide('confirm', (message) => Promise<boolean>)`), never `window.confirm`; without it they do nothing. The prop is shared only on the mailbox routes; a host page outside them that needs it adds the `J87NL\VueMailbox\Http\Middleware\ShareMailboxTranslations` middleware.

| Page (`vue-mailbox.pages.*`) | Props |
|---|---|
| `mailbox/Index` | `composeUrl`, `contactsUrl`, `folder` (`id`, `label`, `name`, `role`, `syncs`) or null, `folderForm`, `folders`, `mailbox`, `mailboxes`, `olderMessagesUrl`, `refreshUrl`, `settingsUrl`, `syncNotice`, `syncing`, `table` |
| `mailbox/Show` | `actionsUrl`, `attachments`, `backUrl`, `composeUrl`, `contactUrl`, `conversation`, `folders`, `hasRemoteImages`, `history`, `html`, `imageOrigin`, `mailbox`, `message`, `remoteImages` |
| `mailbox/Compose` | `backUrl`, `folders`, `form`, `mailbox`, `mode` |
| `mailbox/Contacts/Index` | `backUrl`, `createUrl`, `folders`, `mailbox`, `table` |
| `mailbox/Contacts/Edit` | `backUrl`, `destroyUrl`, `form`, `mailbox`, `title` |
| `mailbox/Empty` | `hint`, `title` |
| `mailbox/Settings/Index`, `mailbox/Settings/Edit` | unchanged |

Reply, reply all and forward put the original HTML in the compose body (Gmail-style header or "On … wrote:" blockquote). Its inline images use signed inline URLs with `data-mailbox-cid`; keep that attribute in your editor (the default HugeRTE options do). Inline SVG images are served with a locked-down CSP. After sending a reply or forward the app returns to the folder of the original message.

The message table polls every `vue-mailbox.poll_seconds` (30); pass `:only="['table', 'folders']"` to `TableBuilder` so a tick reloads only the table and the folder counts. Unread rows get `font-semibold` from the server, and a paperclip follows the subject of a message with attachments. The Refresh button queues a sync of the folder (`mailbox.folders.refresh`). When the server holds more mail in the folder than is stored (for example from before the core's `sync.initial_days`), an "Oudere berichten ophalen" button queues the core `FetchOlderMessagesJob` (`mailbox.folders.older`) and reloads the list a few seconds later. With the core `conversation_view` setting of a mailbox on, the list shows one row per conversation and the message view lists the conversation.

The folder page shows only the folders of the open mailbox, with a compact mailbox switcher when the user has several (`vue-mailbox.mailbox_switcher`, default `true`). To list every mailbox in the host menu, use `J87NL\VueMailbox\Support\MailboxNavigation::for($user)` or the `ShareMailboxNavigation` middleware, which shares it as the `mailboxNavigation` prop (`id`, `name`, `email`, `unseen`, `url`, `active_path`); see `docs/navigation.md`.

Folders the core does not sync (Gmail's All Mail, Important and Starred) have `syncs: false`: the folder list mutes them with a "Niet gesynct" badge and the folder page shows `syncNotice` above the list.

Browser notifications: the folder page and the message view have a "Meldingen aanzetten voor deze mailbox" toggle, so notifications are switched per mailbox (stored per browser). Once the user turns it on (the browser asks for permission then, never on load), the open tab polls `mailbox.notifications` every `poll_seconds` and shows a notification per new unread inbox message of the mailboxes that have notifications on and the user may read (one "N nieuwe berichten" notification above three). Clicking it opens the message. No service worker: it only works while a tab of the app is open. See `docs/notifications.md`.

The message view fills the viewport and has a Full screen button that opens the message, with its actions, in a package-owned dialog (Esc or Close closes it). It needs no dialog component of the host; the host's `confirm` dialog must sit above `z-40`.

## Routes

All routes live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`) with the `web` and `auth` middleware. Menu links: `route('mailbox.index')` (show it with `@canany(['accessAnyMailbox', 'manageAnyMailbox'])`; without an open mailbox it redirects users who pass `manageAnyMailbox` to the settings and shows others the `mailbox/Empty` page) and `route('mailbox.settings.index')` (show it with `@can('manageAnyMailbox')`). Every route except the signed inline image route is gated with the core abilities (`accessAnyMailbox`, `accessMailbox`, `workInMailbox`, `manageMailbox`, `manageMailboxes`) or `manageAnyMailbox`, which this package defines unless the app already has it: `manageMailboxes`, or `manageMailbox` on at least one mailbox. The pages hide controls the user cannot use.

| Route | Ability |
|---|---|
| `mailbox.notifications` `GET notifications?after=` (new unread inbox messages, JSON, throttled) | `accessAnyMailbox`, then `accessMailbox` per message |
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
