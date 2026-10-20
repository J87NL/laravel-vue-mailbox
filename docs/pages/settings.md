# Mailbox settings

The settings pages list the mailboxes, create and edit them, and manage who has access to each mailbox.

## Mailbox list

Route `mailbox.settings.index` (`GET settings`) renders the `vue-mailbox.pages.settings_index` page, default
`Mailbox/Settings/Index`. Every logged-in user can open it; the table only lists the mailboxes the user can manage
(`manageMailbox`).

| Prop | Type | Description |
|---|---|---|
| `createUrl` | `string \| null` | "New mailbox" URL, `null` without `manageMailboxes` |
| `table` | `TableData` | The `MailboxesTable`, see [Tables](../tables.md#mailboxestable) |

## Create and edit

Routes `mailbox.settings.create` (`manageMailboxes`) and `mailbox.settings.edit` (`manageMailbox`) render the
`vue-mailbox.pages.settings_edit` page, default `Mailbox/Settings/Edit`.

| Prop | Type | Description |
|---|---|---|
| `accessForm` | `FormSchema \| null` | Form to grant access, `null` when creating |
| `accessTable` | `TableData \| null` | The `MailboxAccessTable`, `null` when creating |
| `backUrl` | `string` | The mailbox list |
| `destroyUrl` | `string \| null` | Delete URL, only with `manageMailboxes` when editing |
| `form` | `FormSchema` | The core `MailboxForm` |
| `title` | `string` | The mailbox name, or "New mailbox" |

The form and saving are handled by the core `MailboxForm` and `SaveMailbox`. `SaveMailbox` returns validation errors,
and saves nothing, when:

- the connection test fails (errors on `imap_host` or `smtp_host`);
- the server, port, encryption or username of an existing mailbox changes while its password field is left empty;
- a user without `manageMailboxes` changes the server or username of an OAuth mailbox.

- `store` redirects to the edit page of the new mailbox with a `success` flash message.
- `update` redirects back with a `success` flash message.
- `destroy` (`manageMailboxes`) deletes the mailbox and redirects to the mailbox list.

## Access

The access section of the edit page lists who has access (`accessTable`) and grants new access (`accessForm`). Both
need `manageMailbox`.

`POST settings/{mailbox}/access` (`mailbox.settings.access.store`) validates the core `AccessForm`:

| Field | Description |
|---|---|
| `accessor` | An assignable user or group, as `type:id`, from the core accessor resolver |
| `level` | `read`, `write` or `manage` |

`DELETE settings/{mailbox}/access/{access}` (`mailbox.settings.access.destroy`) revokes one row. Both go through the
core `GrantAccess` action and redirect back. When the core refuses a change, for example revoking the last manager,
the error is set on `accessor`. The default page shows that error above the access table.
