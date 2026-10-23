# Navigation

A host can list every mailbox the user may open in its own menu, for example as one main menu item per mailbox with
its unread count, instead of one "Mailbox" item.

## MailboxNavigation

`J87NL\VueMailbox\Support\MailboxNavigation::for(?Authenticatable $user)` returns the active mailboxes the user passes
`accessMailbox` for, sorted by name, as a list of arrays:

| Key | Description |
|---|---|
| `id` | Mailbox id |
| `name` | Mailbox name |
| `email` | Mailbox address |
| `unseen` | `unseen_count` of the mailbox's inbox, `0` without an inbox |
| `url` | Absolute `mailbox.folders.show` URL of the mailbox (opens its inbox) |
| `active_path` | Path prefix of every page of the mailbox, such as `/mailbox/3/`, to highlight the menu item |
| `synced_at` | Last sync as ISO 8601, or `null` |

A guest gets an empty list. It costs three queries: the mailboxes, the access preload of the core resolver and the
inbox counts. The folder page uses the same list for its mailbox switcher.

```php
use J87NL\VueMailbox\Support\MailboxNavigation;

$items = app(MailboxNavigation::class)->for($request->user());
```

## Shared prop

`J87NL\VueMailbox\Http\Middleware\ShareMailboxNavigation` shares the list as the lazy Inertia prop `mailboxNavigation`
(`ShareMailboxNavigation::PROP`). Add it to the routes whose layout shows the menu, for example the `web` group:

```php
use J87NL\VueMailbox\Http\Middleware\ShareMailboxNavigation;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        ShareMailboxNavigation::class,
    ]);
})
```

```vue
<Link v-for="mailbox in $page.props.mailboxNavigation" :key="mailbox.id" :href="mailbox.url">
    {{ mailbox.name }} <span v-if="mailbox.unseen">{{ mailbox.unseen }}</span>
</Link>
```

A host that builds its menu on the server (such as j87.nl's `NavigationRegistry`) calls `MailboxNavigation` directly
instead. Gate the menu on `accessAnyMailbox` as before; when the list is empty, fall back to a single link to
`mailbox.index`, which sends a manager without a mailbox to the settings.

When the menu lists every mailbox, turn off the switcher on the folder page with `vue-mailbox.mailbox_switcher`
set to `false`.
