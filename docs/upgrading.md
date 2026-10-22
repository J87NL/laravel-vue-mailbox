# Upgrading

Run `composer update j87nl/laravel-vue-mailbox` and read the section for every version you skip. The
[changelog](https://github.com/J87NL/laravel-vue-mailbox/blob/main/CHANGELOG.md) lists every change.

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
the controllers render. Laravel merges a package config one level deep, so a `pages` array in your file replaces the
package's `pages` array as a whole: the new defaults never reach an app that has its own `pages` array.

### Your app has its own copies of the pages

Your `config/vue-mailbox.php` names your copies (`Mailbox/Index`, or names of your own) and your resolver finds them in
`resources/js/pages`. **Nothing changes.** The controllers render the names from your config, the props are the same,
and your copies keep importing `@mailbox/components/...` and `@mailbox/types`, which did not move.

This holds as long as your `config/vue-mailbox.php` has a `pages` array with all seven keys, as the one
`mailbox:install` published does. To move a page back to the package later, see below.

### Your app loaded `Mailbox/*` pages from the vendor directory

A resolver that mapped `Mailbox/Index` to `vendor/j87nl/laravel-vue-mailbox/resources/js/pages/Mailbox/Index.vue` no
longer finds the file. Switch to the package names:

1. In `config/vue-mailbox.php`, change every `Mailbox/...` page name to `mailbox/...`, or remove the `pages` key to use
   the defaults.
2. Load names that start with `mailbox/` from `@mailbox/pages`, as shown in
   [Installation](installation.md#3-load-the-pages-from-the-package).
3. Let Tailwind scan `vendor/j87nl/laravel-vue-mailbox/resources/js/**/*.vue`.
4. Run `npm run build`.

### Your app has no `config/vue-mailbox.php`

The controllers now render `mailbox/...`. Add the resolver branch from
[Installation](installation.md#3-load-the-pages-from-the-package), or run `php artisan mailbox:install --pages` to copy
the pages and publish a config that names the copies.

### Going back from copies to the package pages

Compare your copies with the package pages first, and keep only the pages you really changed. For every page you drop,
delete the file in `resources/js/pages/Mailbox` and set its key in `config/vue-mailbox.php` back to the `mailbox/...`
default. Add the resolver branch once.

## From 0.3 to 0.4

`mailbox.settings.index` needs the ability `manageAnyMailbox` (`manageMailboxes`, or `manageMailbox` on at least one
mailbox). Show the settings link in your menu with `@can('manageAnyMailbox')`. An app that already shows it only for
`manageMailboxes` needs no change.
