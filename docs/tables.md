# Tables

The lists are TableBuilder tables in `J87NL\VueMailbox\Tables`. The controllers build them with `::build(...)` and pass
them as the `table` or `accessTable` prop. Bulk actions run through the TableBuilder's own signed bulk action route;
each table checks the mailbox abilities in its `authorize()` hook, and the tables with bulk actions (`MessagesTable`,
`ContactsTable`) also in `authorizeBulkAction()`.

All column labels and texts come from the core translation file `mailbox::mailbox`.

## MessagesTable

The messages of one folder, on the [Folders and message list](pages/folders.md) page.

```php
MessagesTable::build($folder);
```

| Setting | Value |
|---|---|
| Columns | From (name, or the address), Subject, Attachments (boolean), Received |
| Sort | `sent_at`, newest first |
| Global search | `subject`, `from_email`, `from_name` |
| Per page | `config('vue-mailbox.per_page')`, default 50 |
| Poll | `config('vue-mailbox.poll_seconds')`, default 30 |
| Row link | `mailbox.messages.show` |
| Row class | `font-semibold` for unread messages |

The subject is prefixed with `⚑` for flagged and `↩` for answered messages, and shows `—` when empty. The received
date shows the time (`H:i`) for today and the date (`d-m-Y`) otherwise, in the display timezone of the core package.

Viewing needs `accessMailbox`. With `workInMailbox` the table has these bulk actions:

| Bulk action | Does |
|---|---|
| Archive | core `ArchiveMessages` |
| Delete | core `DeleteMessages`, with a confirmation |
| Mark as read | core `MarkMessages::read()` |
| Mark as unread | core `MarkMessages::unread()` |
| Move to folder | core `MoveMessages`, with a choice of the other selectable folders of the mailbox |

Bulk actions only touch messages of the table's folder. What each core action does on the server is documented in the
core [message actions](https://github.com/J87NL/laravel-mailbox/blob/main/docs/message-actions.md) docs.

## ContactsTable

The address book of one mailbox, on the [Address book](pages/contacts.md) page.

```php
ContactsTable::build($mailbox);
```

| Setting | Value |
|---|---|
| Columns | Name, Email address, Organisation, all sortable |
| Sort | `name` |
| Global search | `name`, `email`, `organisation` |
| Per page | 50 |

Viewing needs `accessMailbox`. With `workInMailbox` each row has Edit and Delete (with a confirmation) actions, and the
table has a "Delete selected" bulk action.

## MailboxesTable

The mailboxes the user can manage, on the [Mailbox settings](pages/settings.md) list.

```php
MailboxesTable::build();
```

| Setting | Value |
|---|---|
| Columns | Name, Email address, Active (boolean), Last sync (relative time), Last error |
| Sort | `name` |
| Global search | `name`, `email` |
| Per page | 25 |
| Row link | `mailbox.settings.edit` |

Only a user who passes `manageAnyMailbox` may load the table. It only contains mailboxes for which the user passes
`manageMailbox`.

## MailboxAccessTable

The access rows of one mailbox, on the [Mailbox settings](pages/settings.md#access) edit page.

```php
MailboxAccessTable::build($mailbox);
```

| Setting | Value |
|---|---|
| Columns | User or group (label from the core accessor resolver), Level |
| Sort | `accessor_type` |
| Search, column toggle | off |
| Per page | 50 |
| Row action | Remove, with a confirmation, to `mailbox.settings.access.destroy` |

Viewing needs `manageMailbox`. An accessor the resolver has no label for shows as `{type} #{id}`.
