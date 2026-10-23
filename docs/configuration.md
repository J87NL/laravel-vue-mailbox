# Configuration

`php artisan mailbox:install` publishes the config file. To publish only this package's config:

```bash
php artisan vendor:publish --tag="vue-mailbox-config"
```

This creates `config/vue-mailbox.php`. None of its keys read an environment variable.

The core settings (access mode, administrators, display timezone, compose editor, remote images, sync) live in
`config/mailbox.php` and are documented in the core
[configuration](https://github.com/J87NL/laravel-mailbox/blob/main/docs/configuration.md) docs.

## Routes

```php
'routes' => [
    'prefix' => 'mailbox',
    'name' => 'mailbox.',
    'middleware' => ['web', 'auth'],
],
```

| Key | Default | Description |
|---|---|---|
| `prefix` | `'mailbox'` | URL prefix of every route |
| `name` | `'mailbox.'` | Route name prefix. Every controller builds its URLs with this prefix |
| `middleware` | `['web', 'auth']` | Middleware of every route except the signed inline image route |

The inline image route always uses `['web', 'signed']`, whatever `middleware` holds. See
[Routes and authorization](routes.md).

## Pages

```php
'pages' => [
    'compose' => 'mailbox/Compose',
    'contacts_edit' => 'mailbox/Contacts/Edit',
    'contacts_index' => 'mailbox/Contacts/Index',
    'index' => 'mailbox/Index',
    'settings_edit' => 'mailbox/Settings/Edit',
    'settings_index' => 'mailbox/Settings/Index',
    'show' => 'mailbox/Show',
],
```

The Inertia component each controller renders. The defaults start with `mailbox/` and name the pages inside the package
(`mailbox/Index` is `resources/js/pages/Index.vue` of the package); your page resolver loads them from `@mailbox/pages`,
see [Installation](installation.md#3-load-the-pages-from-the-package). Point a key at your own page to replace that one
page; a key you leave out keeps its default. Your page receives the props listed on the page's docs. See [Customizing pages](frontend/customizing-pages.md).

`mailbox:install --pages` copies the pages to `resources/js/pages/Mailbox` and changes every default name in this file
to the copy (`mailbox/Index` becomes `Mailbox/Index`). A name you changed yourself is left alone.

| Key | Rendered by | Docs |
|---|---|---|
| `index` | folder and message list | [Folders and message list](pages/folders.md) |
| `show` | message view | [Message view](pages/messages.md) |
| `compose` | new message, reply, reply all, forward | [Compose, reply and forward](pages/compose.md) |
| `contacts_index` | address book list | [Address book](pages/contacts.md) |
| `contacts_edit` | new and edit contact | [Address book](pages/contacts.md) |
| `settings_index` | mailbox list in the settings | [Mailbox settings](pages/settings.md) |
| `settings_edit` | new and edit mailbox, with access | [Mailbox settings](pages/settings.md) |

## Message list

```php
'per_page' => 50,

'poll_seconds' => 30,
```

| Key | Default | Description |
|---|---|---|
| `per_page` | `50` | Messages per page in the message table |
| `poll_seconds` | `30` | Interval in seconds at which the message table reloads itself |

See [Tables](tables.md#messagestable).

## Signed URLs

```php
'signed_url_minutes' => 30,
```

How long a signed inline image URL stays valid, in minutes. See [Inline images](inline-images.md).
