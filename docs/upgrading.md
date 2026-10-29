# Upgrading

Run `composer update j87nl/laravel-vue-mailbox` and read the section for every version you skip. The
[changelog](https://github.com/J87NL/laravel-vue-mailbox/blob/main/CHANGELOG.md) lists every change.

## From 0.9.1 to 0.9.2: managers of inactive mailboxes

- No migration, no config change and no frontend rebuild.
- Allows `j87nl/laravel-mailbox` 0.8. With it, a user with `Manage` on an inactive mailbox sees it in the settings
  list and can open its settings and set it active again, without `MAILBOX_ADMINS`. Its folders, messages and compose
  stay closed to everybody but admins. See [Inactive mailboxes](pages/settings.md#inactive-mailboxes).
- An app that defines `manageMailbox` itself only to let creators or managers into an inactive mailbox can drop it.

## From 0.8 to 0.9: menu gate and alias array

- No migration, no config change and no frontend rebuild.
- `mailbox.index` now also lets in users who pass `manageAnyMailbox` and sends them to the settings when they have no
  mailbox to open. When your app defines `manageMailboxes` itself, gate the menu item on `accessAnyMailbox` or
  `manageAnyMailbox`, see [Navigation](navigation.md#menu-gate). With only `MAILBOX_ADMINS` nothing changes.
- An app that registers the HugeRTE editor with a static import can switch to the lazy registration in
  [Installation](installation.md#4-install-the-compose-editor) to keep about 1 MB out of its main bundle.

## From 0.8.1 to 0.8.2: folders that are not synced

- Needs `j87nl/laravel-mailbox` ^0.5.3. Rebuild the frontend (`npm run build`). No migration, no config change.
- Hosts on the default pages get the muted folders and the notice without a change.
- An app with a copied `Index` page adds the `syncNotice` prop and shows it above the list, for example `<div v-if="syncNotice" role="note">{{ syncNotice }}</div>`. A copied folder list can use `folder.syncs`.

## From 0.8.0 to 0.8.1: notifications per mailbox

- Rebuild the frontend (`npm run build`). No migration, no config change.
- Users who had notifications on keep them for every mailbox they may read; the stored `on` turns into a per-mailbox
  choice on the first poll.
- An app with copied `Index` or `Show` pages passes the mailbox to the toggle: `<NotificationToggle
  :folder-id="folder?.id ?? null" :mailbox-id="mailbox.id" />` (folder page) or `<NotificationToggle
  :mailbox-id="mailbox.id" />` (message view). Without `mailbox-id` the toggle switches all mailboxes at once.

## From 0.7 to 0.8: browser notifications

- No migration and no config change. Rebuild the frontend (`npm run build`) so the toggle is bundled.
- Hosts on the default pages (`mailbox/Index`, `mailbox/Show`) get the toggle in the toolbar without a change.
- An app with copied `Index` or `Show` pages adds `<NotificationToggle :folder-id="folder?.id ?? null" />` (folder
  page) or `<NotificationToggle />` (message view) from `@mailbox/components/NotificationToggle.vue` to its toolbar.
- To keep notifications running on other pages of the app, add the toggle to that layout and the
  `ShareMailboxNavigation` middleware to its routes. See [Browser notifications](notifications.md).
- A strict `Content-Security-Policy` needs no change: the poll is a same-origin `fetch`.

## From 0.6 to 0.7: searching the access form

- Requires `j87nl/laravel-mailbox` ^0.5 and `tranquil-tools/laravel-vue-form-builder` ^1.13. Update them together:
  `composer update j87nl/laravel-mailbox j87nl/laravel-vue-mailbox 'tranquil-tools/*' -W`, then rebuild the frontend
  (`npm run build`) so the searchable select of the new form builder is bundled. No migration.
- The access form searches users and groups on the server (new route `mailbox.settings.accessors`) instead of sending
  all of them with the page. An app with a copied `Settings/Edit` page that renders `accessForm` with `<Form>` needs no
  change.
- An app that builds the core `AccessForm` itself passes the search url as second argument, see the core
  [changelog](https://github.com/J87NL/laravel-mailbox/blob/main/CHANGELOG.md). A custom `ResolvesAccessors` needs a
  `search()` method.

## From 0.5 to 0.6: conversations, navigation and full screen

- Requires `j87nl/laravel-mailbox` ^0.4, which adds the migration `add_conversations_to_mailbox_tables`. Publish and
  run it (`php artisan vendor:publish --tag=mailbox-migrations` adds only the new one, then `php artisan migrate`) and
  thread the stored messages once with `php artisan mailbox:threads`. The message table selects `thread_key`, so the
  folder page fails until the migration ran.
- The folder page no longer lists every mailbox above the folders. It shows a compact switcher instead, or nothing
  with `vue-mailbox.mailbox_switcher` set to `false`. List the mailboxes in your own menu with
  [`MailboxNavigation`](navigation.md).
- The attachment column is gone; a paperclip follows the subject. A saved column choice for `has_attachments` is
  ignored.
- The shared translations now also hold the package's own `vue-mailbox::mailbox` keys. An app with copied pages needs
  no change.
- An app with copied pages keeps its own message view and folder page; the new props (`refreshUrl`, `conversation`)
  are extra and can be ignored.

## From 0.5.0 to 0.5.1: empty state and confirmations

- `mailbox.index` no longer answers `404` when the user can open no active mailbox. Users with `manageAnyMailbox` are
  redirected to `mailbox.settings.index`, others get the new page `vue-mailbox.pages.empty` (default `mailbox/Empty`).
  An app that resolves `mailbox/` pages from `@mailbox/pages` needs no change. An app that copied the pages and has no
  `mailbox/` resolver branch points `empty` at a page of its own in `config/vue-mailbox.php` (`mailbox:install --pages`
  copies `Empty.vue` too), otherwise the empty state throws "page not found".
- The default pages ask for delete confirmations through the function the app provides under the Vue injection key
  `confirm` instead of `window.confirm`. Provide one (see [Components and helpers](frontend/components.md#confirmations)),
  or deletes in the message view, contact form and mailbox settings do nothing.

## From 0.4 to 0.5: the pages stay in the package

The pages now live in the package by default, like the pages of `j87nl/laravel-vue-cms`:

- The default page names start with `mailbox/` instead of `Mailbox/`: `mailbox/Index`, `mailbox/Show`,
  `mailbox/Compose`, `mailbox/Contacts/Index`, `mailbox/Contacts/Edit`, `mailbox/Settings/Index` and
  `mailbox/Settings/Edit`.
- The page files moved inside the package from `resources/js/pages/Mailbox/*` to `resources/js/pages/*`, so
  `mailbox/Contacts/Index` is `resources/js/pages/Contacts/Index.vue`. The components, `types.ts`, `translations.ts` and
  `ui.ts` did not move; `@mailbox/components/...`, `@mailbox/types`, `@mailbox/translations` and `@mailbox/ui` stay the
  same.
- `mailbox:install --pages` still copies the pages to `resources/js/pages/Mailbox` with the same file names as before,
  and now also points the page names in `config/vue-mailbox.php` at the copies.

What you do depends on how your app gets its pages today. Your published `config/vue-mailbox.php` decides which names
the controllers render: every key in its `pages` array wins, and only a key that is missing falls back to the new
`mailbox/...` default.

### Your app has its own copies of the pages

Your `config/vue-mailbox.php` names your copies (`Mailbox/Index`, or names of your own) and your resolver finds them in
`resources/js/pages`. **Nothing changes.** The controllers render the names from your config, the props are the same,
and your copies keep importing `@mailbox/components/...` and `@mailbox/types`, which did not move.

This holds as long as your `pages` array has all seven keys, as the one `mailbox:install` published does. A missing
key now renders the package page under its `mailbox/...` name, which your resolver does not know. `mailbox:install`
warns when `resources/js/pages/Mailbox/Index.vue` exists while the `index` page name starts with `mailbox/`, and when a
`Mailbox/...` page name has no file in `resources/js/pages`.

### Your app loaded `Mailbox/*` pages from the vendor directory

A resolver that mapped `Mailbox/Index` to `vendor/j87nl/laravel-vue-mailbox/resources/js/pages/Mailbox/Index.vue` no
longer finds the file. Switch to the package names:

1. In `config/vue-mailbox.php`, change every `Mailbox/...` page name to `mailbox/...`, or remove the `pages` key to use
   the defaults.
2. Load names that start with `mailbox/` from `@mailbox/pages`, as shown in
   [Installation](installation.md#3-load-the-pages-from-the-package).
3. Let Tailwind scan `vendor/j87nl/laravel-vue-mailbox/resources/js` (the whole folder, `ui.ts` included).
4. Run `npm run build`.

### Your app has no `config/vue-mailbox.php`, or no `pages` in it

The controllers now render `mailbox/...`. If you have copies in `resources/js/pages/Mailbox`, they are no longer used
and the page fails to load until you act. Add the resolver branch from
[Installation](installation.md#3-load-the-pages-from-the-package), or run `php artisan mailbox:install --pages` to copy
the pages. Without a published config it publishes one that names the copies. A published config without `pages`
stays as it is: add the seven keys with their `Mailbox/...` names to it yourself (`mailbox:install` warns until you do).

Tests that assert a package page with `->component('mailbox/Index')` pass `false` as the second argument, see
[Testing](testing.md).

### Going back from copies to the package pages

Compare your copies with the package pages first, and keep only the pages you really changed. For every page you drop,
delete the file in `resources/js/pages/Mailbox` and set its key in `config/vue-mailbox.php` back to the `mailbox/...`
default. Add the resolver branch once.

## From 0.3 to 0.4

`mailbox.settings.index` needs the ability `manageAnyMailbox` (`manageMailboxes`, or `manageMailbox` on at least one
mailbox). Show the settings link in your menu with `@can('manageAnyMailbox')`. An app that already shows it only for
`manageMailboxes` needs no change.
