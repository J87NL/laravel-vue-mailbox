# Routes and authorization

The routes are registered by the package. They live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`)
and their names start with `config('vue-mailbox.routes.name')` (default `mailbox.`). The tables below use the defaults.

## Abilities

Every route checks one of the Gate abilities the core package defines. Which users pass them depends on the access
mode, administrators and per-mailbox access levels of `j87nl/laravel-mailbox`.

| Ability | Argument | Meaning |
|---|---|---|
| `accessAnyMailbox` | - | The user can open at least one mailbox |
| `accessMailbox` | `Mailbox` | Read access to the mailbox |
| `workInMailbox` | `Mailbox` | Write access: send, move, delete, manage contacts |
| `manageMailbox` | `Mailbox` | Manage access: edit the mailbox settings and its access list |
| `manageMailboxes` | - | Mailbox administrator: create and delete mailboxes |

The pages hide controls the user cannot use. The controllers pass `null` for a URL the user is not allowed to call,
for example `composeUrl` for a reader.

## Menu links

```blade
@can('accessAnyMailbox')
    <a href="{{ route('mailbox.index') }}">Mailbox</a>
@endcan

<a href="{{ route('mailbox.settings.index') }}">Mailbox settings</a>
```

In a Vue page with an ability helper, use `$can('accessAnyMailbox')` in the same way.

## Mailbox routes

All routes below run behind `config('vue-mailbox.routes.middleware')` (default `web`, `auth`). In this group the
`{mailbox}` parameter only matches numbers. Nested models (`{folder}`, `{message}`, `{contact}`) are scoped to the mailbox, so a
message of another mailbox answers `404`.

| Method | URI | Name | Ability |
|---|---|---|---|
| GET | `/` | `mailbox.index` | `accessAnyMailbox` |
| GET | `{mailbox}/folders/{folder?}` | `mailbox.folders.show` | `accessMailbox` |
| GET | `{mailbox}/messages/{message}` | `mailbox.messages.show` | `accessMailbox` |
| GET | `{mailbox}/messages/{message}/attachments/{index}` | `mailbox.attachments.download` | `accessMailbox` |
| GET | `{mailbox}/contacts` | `mailbox.contacts.index` | `accessMailbox` |
| POST | `{mailbox}/folders` | `mailbox.folders.store` | `workInMailbox` |
| POST | `{mailbox}/messages/actions` | `mailbox.messages.actions` | `workInMailbox` |
| GET | `{mailbox}/compose` | `mailbox.compose.create` | `workInMailbox` |
| POST | `{mailbox}/compose` | `mailbox.compose.store` | `workInMailbox` |
| GET | `{mailbox}/addresses` | `mailbox.addresses` | `workInMailbox` |
| GET | `{mailbox}/contacts/create` | `mailbox.contacts.create` | `workInMailbox` |
| POST | `{mailbox}/contacts` | `mailbox.contacts.store` | `workInMailbox` |
| GET | `{mailbox}/contacts/{contact}/edit` | `mailbox.contacts.edit` | `workInMailbox` |
| PUT | `{mailbox}/contacts/{contact}` | `mailbox.contacts.update` | `workInMailbox` |
| DELETE | `{mailbox}/contacts/{contact}` | `mailbox.contacts.destroy` | `workInMailbox` |
| POST | `{mailbox}/messages/{message}/contact` | `mailbox.messages.contact` | `workInMailbox` |

`mailbox.addresses` takes the search text as the `q` query parameter, see
[Address suggestions](pages/compose.md#address-suggestions).

`mailbox.index` redirects to the first active mailbox the user can open, sorted by name.

## Settings routes

| Method | URI | Name | Ability |
|---|---|---|---|
| GET | `settings` | `mailbox.settings.index` | none, only the route middleware |
| GET | `settings/create` | `mailbox.settings.create` | `manageMailboxes` |
| POST | `settings` | `mailbox.settings.store` | `manageMailboxes` |
| DELETE | `settings/{mailbox}` | `mailbox.settings.destroy` | `manageMailboxes` |
| GET | `settings/{mailbox}` | `mailbox.settings.edit` | `manageMailbox` |
| PUT | `settings/{mailbox}` | `mailbox.settings.update` | `manageMailbox` |
| POST | `settings/{mailbox}/access` | `mailbox.settings.access.store` | `manageMailbox` |
| DELETE | `settings/{mailbox}/access/{access}` | `mailbox.settings.access.destroy` | `manageMailbox` |

`mailbox.settings.index` is open to every logged-in user. Its table only lists the mailboxes the user can manage. See
[Mailbox settings](pages/settings.md).

## Inline image route

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `inline/{message}/{cid}` | `mailbox.inline` | `web`, `signed` |

This route does not use the configured middleware and checks no ability. It only answers a valid, unexpired signature.
See [Inline images](inline-images.md).

## Translations middleware

Every route above also runs `J87NL\VueMailbox\Http\Middleware\ShareMailboxTranslations`. See
[Translations](frontend/translations.md).
