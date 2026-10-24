# Mailbox settings

The settings pages list the mailboxes, create and edit them, and manage who has access to each mailbox.

Inside a host layout that pads its content itself, the pages can drop their own padding. See
[Padding inside your own layout](../frontend/components.md#padding-inside-your-own-layout).

## Mailbox list

Route `mailbox.settings.index` (`GET settings`) renders the `vue-mailbox.pages.settings_index` page, default
`mailbox/Settings/Index`. It needs `manageAnyMailbox`: `manageMailboxes`, or `manageMailbox` on at least one mailbox.
Other users get a 403, so gate your menu link on the same ability. The table only lists the mailboxes the user can
manage (`manageMailbox`).

| Prop | Type | Description |
|---|---|---|
| `createUrl` | `string \| null` | "New mailbox" URL, `null` without `manageMailboxes` |
| `table` | `TableData` | The `MailboxesTable`, see [Tables](../tables.md#mailboxestable) |

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
  database transaction. The core still lets only admins manage an **inactive** mailbox, so a non-admin creator of an
  inactive mailbox gets the row but a 403 on the edit page, unless the app defines `manageMailbox` itself.
- `update` redirects back with a `success` flash message.
- `destroy` (`manageMailboxes` and `manageMailbox` on that mailbox) deletes the mailbox and redirects to the mailbox list.

## Access

The access section of the edit page lists who has access (`accessTable`) and grants new access (`accessForm`). Both
need `manageMailbox`.

`POST settings/{mailbox}/access` (`mailbox.settings.access.store`) validates the core `AccessForm`:

| Field | Description |
|---|---|
| `accessor` | An assignable user or group, as `type:id`, from the core [accessor resolver](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md#users-and-groups) |
| `level` | `read`, `write` or `manage` |

`DELETE settings/{mailbox}/access/{access}` (`mailbox.settings.access.destroy`) revokes one row. Both go through the
core [`GrantAccess`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/access.md#granting-and-revoking) action and
redirect back. When the core refuses a change, for example revoking the last manager, the error is set on `accessor`.
The default page shows that error above the access table.
