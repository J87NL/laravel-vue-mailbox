# Laravel Vue Mailbox

A Vue 3 / Inertia user interface for `j87nl/laravel-mailbox`, the package that syncs shared IMAP mailboxes into your
database and sends mail through them.

This package adds the routes, controllers and Inertia pages around that core: a folder list, a message list, a message
view in a sandboxed iframe, compose, reply and forward, an address book per mailbox, and mailbox settings with access
management. Forms are built with the TranquilTools FormBuilder and tables with the TableBuilder.

## How it works

1. Install the package and run `php artisan mailbox:install`. It publishes the config and migrations and adds the
   `@mailbox` Vite alias.
2. The package registers its routes under `/mailbox`, behind `web` and `auth`. Every route is gated with the abilities
   the core package defines (`accessAnyMailbox`, `accessMailbox`, `workInMailbox`, `manageMailbox`, `manageMailboxes`).
3. The controllers render Inertia pages (`Mailbox/Index`, `Mailbox/Show`, `Mailbox/Compose`, ...). The package
   registers no page resolver, so publish the default pages or let your own resolver find them; you can also point
   the config at your own pages.

## Quick example

Link to the mailbox from your menu, for users who can open at least one mailbox:

```blade
@can('accessAnyMailbox')
    <a href="{{ route('mailbox.index') }}">Mailbox</a>
@endcan
```

`mailbox.index` redirects to the first mailbox the user can open. Mailbox administrators and managers reach the
settings through `route('mailbox.settings.index')`.

To change a page, publish the defaults and edit them in your app:

```bash
php artisan mailbox:install --pages
```

## Next steps

- [Installation](installation.md) - set up the package
- [Configuration](configuration.md) - route prefix, page names, polling and signed URLs
- [Routes and authorization](routes.md) - every route and the ability it needs
- [Customizing pages](frontend/customizing-pages.md) - replace or edit the Inertia pages
