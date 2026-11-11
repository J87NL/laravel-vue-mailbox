# Mailbox settings

The settings pages list the mailboxes, create and edit them, and manage who has access to each mailbox.

Inside a host layout that pads its content itself, the pages can drop their own padding. See
[Padding inside your own layout](../frontend/components.md#padding-inside-your-own-layout).

## Mailbox list

Route `mailbox.settings.index` (`GET settings`) renders the `vue-mailbox.pages.settings_index` page, default
`mailbox/Settings/Index`. It needs `manageAnyMailbox`: `manageMailboxes`, or `manageMailbox` on at least one mailbox.
Other users get a 403, so gate your menu link on the same ability. The table only lists the mailboxes the user can
manage (`manageMailbox`), inactive ones included.

| Prop | Type | Description |
|---|---|---|
| `createUrl` | `string \| null` | "New mailbox" URL, `null` without `manageMailboxes` |
| `table` | `TableData` | The `MailboxesTable`, see [Tables](../tables.md#mailboxestable) |

## Inactive mailboxes

From `j87nl/laravel-mailbox` 0.8, a user with `Manage` on an inactive mailbox (their own access row or a group) still
passes `manageMailbox` on it. They see it in the mailbox list, open its edit page, change its access and set it active
again, also when `MAILBOX_ADMINS` is empty. Its folder, message and compose routes need `accessMailbox` or
`workInMailbox`, which on an inactive mailbox only admins pass, so the manager gets a 403 there and `mailbox.index` does
not offer it. Users with `Read` or `Write` on it get nothing. With core 0.7 or older, only admins reach an inactive
mailbox.

## Create and edit

Routes `mailbox.settings.create` (`manageMailboxes`) and `mailbox.settings.edit` (`manageMailbox`) render the
`vue-mailbox.pages.settings_edit` page, default `mailbox/Settings/Edit`.

| Prop | Type | Description |
|---|---|---|
| `accessForm` | `FormSchema \| null` | Form to grant access, `null` when creating |
| `accessTable` | `TableData \| null` | The `MailboxAccessTable`, `null` when creating |
| `backUrl` | `string` | The mailbox list |
| `destroyUrl` | `string \| null` | Delete URL, only with `manageMailboxes` when editing |
| `form` | `FormSchema` | The core `MailboxForm` |
| `spamAllowlistForm` | `FormSchema \| null` | The `SpamAllowlistForm` to add a trusted sender, `null` when creating |
| `spamAllowlistTable` | `TableData \| null` | The `SpamAllowlistTable`, `null` when creating |
| `templateCreateUrl` | `string \| null` | `mailbox.settings.templates.create`, `null` when creating |
| `templatesTable` | `TableData \| null` | The `MailboxTemplatesTable`, `null` when creating |
| `title` | `string` | The mailbox name, or "New mailbox" |

The form and saving are handled by the core
[`MailboxForm`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/forms.md#mailboxform) and
[`SaveMailbox`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/message-actions.md#saving-a-mailbox). It tests
the connection and guards the stored passwords; when it refuses, the page shows the validation errors and nothing is
saved.

- `store` redirects to the edit page of the new mailbox with a `success` flash message. When the creator could not
  manage the new mailbox otherwise (an app that lets non-admins pass `manageMailboxes`), `store` grants them `Manage`
  on it with the core `GrantAccess`, so they land on its edit page instead of a 403 and can share it from there.
  When the grant fails, the new mailbox is deleted again. The connection test and the queued sync run outside any
  database transaction. The same holds for a new **inactive** mailbox: with core 0.8 the creator manages it through
  that row (see [Inactive mailboxes](#inactive-mailboxes)). With core 0.7 or older they get the row but a 403 on the
  edit page, unless the app defines `manageMailbox` itself.
- `update` redirects back with a `success` flash message.
- `destroy` (`manageMailboxes` and `manageMailbox` on that mailbox) deletes the mailbox and redirects to the mailbox list.

## Access

The access section of the edit page lists who has access (`accessTable`) and grants new access (`accessForm`). Both
need `manageMailbox`.

`POST settings/{mailbox}/access` (`mailbox.settings.access.store`) validates the core `AccessForm`:

| Field | Description |
|---|---|
| `accessor` | An existing user or group, as `type:id`, from the core [accessor resolver](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md#users-and-groups), checked with the core `ExistingAccessor` rule |
| `level` | `read`, `write` or `manage` |

The `accessor` field does not list every user and group. From two characters on it searches
`GET settings/{mailbox}/accessors?q=<term>` (`mailbox.settings.accessors`), which answers with at most 20 matching
users and groups as `[{"value": "type:id", "label": "User: Jane Doe"}]`. Users match on name and e-mail, groups on their
label, case-insensitively. The search needs `manageMailbox` on the mailbox and is throttled to 60 requests per minute
per user. A shorter term returns `[]`.

`DELETE settings/{mailbox}/access/{access}` (`mailbox.settings.access.destroy`) revokes one row. Both go through the
core [`GrantAccess`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md#granting-and-revoking) action and
redirect back. When the core refuses a change, for example revoking the last manager, the error is set on `accessor`.
The default page shows that error above the access table.

## Message templates

The edit page has a "Berichtsjablonen" section between the form and the access section: the `templatesTable` and a
"Nieuw sjabloon" link. Managing templates needs `manageMailbox`, like the rest of the settings. Inserting them in
compose needs `workInMailbox`, see [Templates](compose.md#templates).

The create and edit routes render the `vue-mailbox.pages.settings_template` page, default `mailbox/Settings/Template`,
with the core [`TemplateForm`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/templates.md): name, optional
subject and body in the compose editor, with the available placeholders below it.

| Prop | Type | Description |
|---|---|---|
| `backUrl` | `string` | The edit page of the mailbox |
| `destroyUrl` | `string \| null` | Delete URL, `null` when creating |
| `form` | `FormSchema` | The core `TemplateForm` |
| `title` | `string` | The template name, or "Nieuw sjabloon" |

Saving goes through the core `SaveTemplate`, which sanitizes the body. Store, update and destroy redirect to the edit
page of the mailbox with a `success` flash message.

## Delete confirmation

The general section of the form has "Bevestigen bij verwijderen" (`confirm_delete`, off by default). When it is on,
deleting messages from the message view or the bulk action asks for confirmation through the host's `confirm` dialog.
When it is off, they are deleted right away. Deleting a contact, template, access row or mailbox always asks.

## Spam filter

The form has the "Spamfilter" select of the core `MailboxForm` (`spam_filter_mode`: `off`, `measure`, `suggest` or
`auto`, `off` by default). The core does the filtering, see its
[spam filter docs](https://github.com/J87NL/laravel-mailbox/blob/main/docs/spam-filter.md). Without a classifier bound
in the app, no mode does anything.

## Trusted senders

The edit page has a "Vertrouwde afzenders" section between the templates and the access section: the
`spamAllowlistTable` and the `spamAllowlistForm`. It manages the core
[allowlist](https://github.com/J87NL/laravel-mailbox/blob/main/docs/spam-filter.md#the-allowlist) of the mailbox: mail
from these addresses and domains skips the classifier. It shows for every mailbox, whatever its spam filter mode, and
needs `manageMailbox`, like the rest of the settings.

`POST settings/{mailbox}/spam-allowlist` (`mailbox.settings.spam-allowlist.store`) validates the
`J87NL\VueMailbox\Forms\SpamAllowlistForm`:

| Field | Description |
|---|---|
| `pattern` | Required, at most 255 characters: an e-mail address or a domain with a dot (`name@example.org`, `example.org` or `@example.org`) |

It adds the pattern to the mailbox's allowlist with the core `SpamAllowlist::add()`, source `manual`. The core trims and
lowercases it and drops a leading `@`; a pattern that is already on the list keeps its entry and source.

`DELETE settings/{mailbox}/spam-allowlist/{entry}` (`mailbox.settings.spam-allowlist.destroy`) removes one entry of
the mailbox with `SpamAllowlist::remove()`. An entry of another mailbox, or a global one, answers `404`. Both redirect
back with a `success` flash message.

Entries with a `null` `mailbox_id` apply to every mailbox. The table lists them too, marked "Alle mailboxen", but
read-only: the package offers no screen to add or remove them, because no mailbox setting is the right place for a
rule that covers every mailbox. The app manages them with `SpamAllowlist` (for example a seeder with source `default`).
Entries the users add with "Geen spam" show with source "Geen spam".
