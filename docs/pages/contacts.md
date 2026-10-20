# Address book

Every mailbox has its own address book. The contacts feed the recipient suggestions of the compose form, see
[Address suggestions](compose.md#address-suggestions).

Readers (`accessMailbox`) can view the list. Writers (`workInMailbox`) can add, edit and delete contacts.

## List

Route `mailbox.contacts.index` (`GET {mailbox}/contacts`) renders the `vue-mailbox.pages.contacts_index` page, default
`Mailbox/Contacts/Index`.

| Prop | Type | Description |
|---|---|---|
| `backUrl` | `string` | The mailbox's inbox |
| `createUrl` | `string \| null` | "New contact" URL, `null` without `workInMailbox` |
| `folders` | `MailboxFolder[]` | Folder list, see [Folders and message list](folders.md#folder-list) |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `table` | `TableData` | The `ContactsTable`, see [Tables](../tables.md#contactstable) |

## Create and edit

Routes `mailbox.contacts.create` and `mailbox.contacts.edit` render the `vue-mailbox.pages.contacts_edit` page,
default `Mailbox/Contacts/Edit`.

| Prop | Type | Description |
|---|---|---|
| `backUrl` | `string` | The address book |
| `destroyUrl` | `string \| null` | Delete URL, `null` for a new contact |
| `form` | `FormSchema` | The core `ContactForm` |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `title` | `string` | The contact's label, or "New contact" |

The `ContactForm` has the fields `name`, `email`, `organisation` and `notes`. The email address is required and unique
within the mailbox. Before validation the controller trims the address and turns it into lower case.

`store`, `update` and `destroy` redirect to the address book with a `success` flash message.

## From a message

The message view can add the sender to the address book through `mailbox.messages.contact`. An address that is
already in the address book is not added twice. See [Message view](messages.md#add-the-sender-to-the-address-book).
