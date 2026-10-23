# Routes and authorization

The routes are registered by the package. They live under `config('vue-mailbox.routes.prefix')` (default `/mailbox`)
and their names start with `config('vue-mailbox.routes.name')` (default `mailbox.`). The tables below use the defaults.

## Abilities

Every route except the signed inline image route checks a Gate ability. The core package defines
`accessAnyMailbox`, `accessMailbox` (read), `workInMailbox` (write), `manageMailbox` (manage) and `manageMailboxes`
(admin). This package adds `manageAnyMailbox` for the settings list: it passes with `manageMailboxes`, or with
`manageMailbox` on at least one mailbox. Like the core abilities it is only defined when your app has not defined it
itself, and it follows your own `manageMailboxes` and `manageMailbox` definitions. What each level means, and how the access mode, admins and access rows decide who passes, is
documented in the core [access control](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md) docs. The
tables below show which route needs which ability.

The pages hide controls the user cannot use. The controllers pass `null` for a URL the user is not allowed to call,
for example `composeUrl` for a reader.

## Menu links

```blade
@can('accessAnyMailbox')
    <a href="{{ route('mailbox.index') }}">Mailbox</a>
@endcan

@can('manageAnyMailbox')
    <a href="{{ route('mailbox.settings.index') }}">Mailbox settings</a>
@endcan
```

In a Vue page with an ability helper, use `$can('accessAnyMailbox')` and `$can('manageAnyMailbox')` in the same way.

## Mailbox routes

All routes below run behind `config('vue-mailbox.routes.middleware')` (default `web`, `auth`). In this group the
`{mailbox}` parameter only matches numbers. Nested models (`{folder}`, `{message}`, `{contact}`) are scoped to the
mailbox, so a message of another mailbox answers `404`.

| Method | URI | Name | Ability |
|---|---|---|---|
| GET | `/` | `mailbox.index` | `accessAnyMailbox` |
| GET | `{mailbox}/folders/{folder?}` | `mailbox.folders.show` | `accessMailbox` |
| POST | `{mailbox}/folders/{folder}/refresh` | `mailbox.folders.refresh` | `accessMailbox`, `throttle:12,1` |
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

`mailbox.messages.show` fetches the body on first open and marks the message read for a user with `workInMailbox`. The
row and the unread count change right away; the `\Seen` flag is set on the server after the response (core
`MarkMessages::readInBackground()`), so opening does not wait for IMAP. The attachment download and the inline route
read the raw message the core cached on that first fetch (core `cache.raw_hours`), after the same checks as always.

`mailbox.addresses` takes the search text as the `q` query parameter, see
[Address suggestions](pages/compose.md#address-suggestions).

`mailbox.index` redirects to the first active mailbox the user can open, sorted by name. When there is none, it redirects a user with `manageAnyMailbox` to `mailbox.settings.index`, and renders the `vue-mailbox.pages.empty` page (default `mailbox/Empty`) for anyone else instead of answering `404`.

## Settings routes

| Method | URI | Name | Ability |
|---|---|---|---|
| GET | `settings` | `mailbox.settings.index` | `manageAnyMailbox` |
| GET | `settings/create` | `mailbox.settings.create` | `manageMailboxes` |
| POST | `settings` | `mailbox.settings.store` | `manageMailboxes` |
| DELETE | `settings/{mailbox}` | `mailbox.settings.destroy` | `manageMailboxes` |
| GET | `settings/{mailbox}` | `mailbox.settings.edit` | `manageMailbox` |
| PUT | `settings/{mailbox}` | `mailbox.settings.update` | `manageMailbox` |
| POST | `settings/{mailbox}/access` | `mailbox.settings.access.store` | `manageMailbox` |
| DELETE | `settings/{mailbox}/access/{access}` | `mailbox.settings.access.destroy` | `manageMailbox` |

`mailbox.settings.index` needs `manageAnyMailbox`; other users get a 403. Its table only lists the mailboxes the user
can manage. See
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
