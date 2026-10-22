# Folders and message list

Route `mailbox.folders.show` (`GET {mailbox}/folders/{folder?}`) renders the `vue-mailbox.pages.index` page, default
`mailbox/Index`. It shows the folders of the mailbox and the messages of one folder.

## Opening a mailbox

`mailbox.index` redirects to the first active mailbox the user can open, sorted by name. It answers `403` without the
`accessAnyMailbox` ability and `404` when none of those mailboxes is active.

Without a `{folder}`, the page opens the folder with the inbox role. When the mailbox has no inbox yet, the controller
fetches the folder list from the server once and tries again. If there is still no inbox, it queues a
[`SyncMailboxJob`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sync.md) and renders the page with `folder`
and `table` set to `null` and a `syncing` message, instead of answering `404`.

When the opened folder was not synced in the last 60 seconds, the controller queues a `SyncFolderJob` for it. The page
renders the messages already stored; the table picks up new mail on its next poll.

## Props

| Prop | Type | Description |
|---|---|---|
| `composeUrl` | `string \| null` | Compose URL, `null` without `workInMailbox` |
| `contactsUrl` | `string` | Address book URL |
| `folder` | `object \| null` | `id`, `label`, `name`, `role` of the open folder, `null` while syncing |
| `folderForm` | `FormSchema \| null` | Form to add a folder, `null` without `workInMailbox` |
| `folders` | `MailboxFolder[]` | Selectable folders of the mailbox, see below |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `mailboxes` | `MailboxSummary[]` | Active mailboxes the user can open, sorted by name |
| `settingsUrl` | `string \| null` | Settings URL, `null` without `manageMailbox` |
| `syncing` | `string \| null` | Translated "syncing" notice while there is no folder yet |
| `table` | `TableData \| null` | The message table, `null` while syncing |

`folderForm`, `folders` and `mailboxes` are lazy closures. A partial reload that asks only for `table` does not compute
them, so polling the message list does not query the folder list again.

### Folder list

`folders` holds the selectable folders, sorted by role (inbox, drafts, sent, ...) and then by path. Each item:

| Key | Description |
|---|---|
| `id` | Folder id |
| `label` | Translated role name (Inbox, Sent, ...) or, for a custom folder, its name |
| `name` | Folder name |
| `path` | Full IMAP path |
| `role` | `inbox`, `drafts`, `sent`, `archive`, `junk`, `trash` or `custom` |
| `depth` | Nesting depth, for indenting |
| `unseen` | Number of unread messages |
| `url` | `mailbox.folders.show` URL of the folder |

The message view, compose and address book pages receive the same `folders` prop.

## Message table

The table is a `MessagesTable` (TableBuilder). It polls every `vue-mailbox.poll_seconds` seconds. Render it with
`:only="['table']"` so a poll only reloads the table:

```vue
<TableBuilder :table="table" :only="['table']" />
```

Columns, bulk actions and row styling are described in [Tables](../tables.md#messagestable).

## Adding a folder

`folderForm` posts to `mailbox.folders.store`. It uses the core
[`FolderForm`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/forms.md#folderform): a `name` and an optional
`parent_id`. A parent folder of another mailbox answers `404`. The folder is created on the IMAP server through the core
[`CreateFolder`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/message-actions.md#creating-folders) action,
and the user lands on the new folder.

## Message actions

`POST {mailbox}/messages/actions` (`mailbox.messages.actions`, `workInMailbox`) runs an action on messages of the
mailbox. The message view uses it for its buttons; you can use it from your own pages. Each action calls the matching
core action, see [message actions](https://github.com/J87NL/laravel-mailbox/blob/main/docs/message-actions.md).

| Field | Rules |
|---|---|
| `action` | required, one of `archive`, `delete`, `flag`, `move`, `read`, `unflag`, `unread` |
| `ids` | required array of message ids, at most 500 |
| `folder_id` | required when `action` is `move`, a folder of the same mailbox |

Ids of messages in other mailboxes are ignored. A `folder_id` of another mailbox answers `404`.

After `flag`, `read` and `unflag` the user goes back to the previous page. After the other actions the user lands on
the folder of the first matched message, or on the inbox when none of the ids matched.

```ts
import { router } from '@inertiajs/vue3';

router.post(actionsUrl, { action: 'move', ids: [messageId], folder_id: targetFolderId });
```
