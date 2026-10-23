# Upgrading

Run `composer update j87nl/laravel-vue-mailbox` and read the section for every version you skip. The
[changelog](https://github.com/J87NL/laravel-vue-mailbox/blob/main/CHANGELOG.md) lists every change.

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
