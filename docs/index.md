# Laravel Vue Mailbox

A Vue 3 / Inertia user interface for `j87nl/laravel-mailbox`, the package that syncs shared IMAP mailboxes into your
database and sends mail through them.

This package adds the routes, controllers and Inertia pages around that core: a folder list, a message list, a message
view in a sandboxed iframe, compose, reply and forward, an address book per mailbox, and mailbox settings with access
management. Forms are built with the TranquilTools FormBuilder and tables with the TableBuilder.

## How the packages fit together

The mailbox comes in two packages that lean on each other:

- **[`j87nl/laravel-mailbox`](https://github.com/J87NL/laravel-mailbox)**, the core: models, migrations, config, IMAP
  sync, actions, sending, access abilities, audit trail, HTML sanitizing, forms, translations and Artisan commands.
- **`j87nl/laravel-vue-mailbox`**, this package: routes, controllers, tables and Vue/Inertia pages that call the core,
  plus `mailbox:install` and the signed inline image route.

The boundary rule: **what talks to the mail server, stores data or decides access belongs in the core; what a user
clicks belongs here.** This package has no models, migrations or IMAP and SMTP code of its own.

Which one to install:

- **You want a mailbox screen in your app.** Install this package. It requires `j87nl/laravel-mailbox`, so you get
  both. See [Installation](installation.md).
- **You only sync, send or automate from code** (listeners, jobs, your own UI). Install `j87nl/laravel-mailbox` alone.

These docs cover the UI. Sync, sending, access levels, the audit trail, events and the core config are documented in
the [laravel-mailbox docs](https://github.com/J87NL/laravel-mailbox/blob/main/docs/index.md).

## How it works

1. Install the package and run `php artisan mailbox:install`. It publishes the config and migrations and adds the
   `@mailbox` Vite alias.
2. The package registers its routes under `/mailbox`, behind `web` and `auth`. Every route except the signed inline
   image route is gated with an ability the core package defines (`accessAnyMailbox`, `accessMailbox`,
   `workInMailbox`, `manageMailbox`, `manageMailboxes`), or with `manageAnyMailbox` for the settings list and, next to
   `accessAnyMailbox`, for `mailbox.index`, see
   [core access control](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md).
3. The controllers render Inertia pages (`mailbox/Index`, `mailbox/Show`, `mailbox/Compose`, ...). The pages stay in
   the package: your app's resolver loads names that start with `mailbox/` from `@mailbox/pages`. Point a name in the
   config at your own page to replace one page, or copy all of them with `mailbox:install --pages`.

## Quick example

Link to the mailbox from your menu, for users who can open at least one mailbox or manage mailboxes:

```blade
@canany(['accessAnyMailbox', 'manageAnyMailbox'])
    <a href="{{ route('mailbox.index') }}">Mailbox</a>
@endcanany
```

`mailbox.index` redirects to the first mailbox the user can open. Without one it sends mailbox administrators and managers
to `route('mailbox.settings.index')` and shows everyone else an empty state. Show a direct link to the settings with
`@can('manageAnyMailbox')`.

To change one page, point its name in `config/vue-mailbox.php` at a page of your own. To copy all pages into your app
and edit them there:

```bash
php artisan mailbox:install --pages
```

## Next steps

- [Installation](installation.md) - set up the package
- [Configuration](configuration.md) - route prefix, page names, polling and signed URLs
- [Routes and authorization](routes.md) - every route and the ability it needs
- [Customizing pages](frontend/customizing-pages.md) - replace or edit the Inertia pages
- [Browser notifications](notifications.md) - notifications for new mail while the app is open
- [Upgrading](upgrading.md) - what to change when you update the package
- [laravel-mailbox docs](https://github.com/J87NL/laravel-mailbox/blob/main/docs/index.md) - the core package
