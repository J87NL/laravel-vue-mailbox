# Folders and message list

Route `mailbox.folders.show` (`GET {mailbox}/folders/{folder?}`) renders the `vue-mailbox.pages.index` page, default
`mailbox/Index`. It shows the folders of the mailbox and the messages of one folder.

## Opening a mailbox

`mailbox.index` redirects to the first active mailbox the user can open, sorted by name. It answers `403` without the
`accessAnyMailbox` ability. When the user can open no active mailbox:

- a user with `manageAnyMailbox` is redirected to `mailbox.settings.index`, to set one up;
- anyone else gets the `vue-mailbox.pages.empty` page, default `mailbox/Empty`, instead of a `404`. It shows "No mailbox
  has been set up for you yet" and asks the user to contact an administrator.

### Empty state props

| Prop | Type | Description |
|---|---|---|
| `hint` | `string` | Translated `vue-mailbox::mailbox.no_mailbox_hint` |
| `title` | `string` | Translated `vue-mailbox::mailbox.no_mailbox_title` |

Without a `{folder}`, the page opens the folder with the inbox role. When the mailbox has no inbox yet, the controller
fetches the folder list from the server once and tries again. If there is still no inbox, it queues a
[`SyncMailboxJob`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sync.md) and renders the page with `folder`
and `table` set to `null` and a `syncing` message, instead of answering `404`.

When the opened folder was not synced in the last 60 seconds, the controller queues a `SyncFolderJob` for it. The page
renders the messages already stored; the table picks up new mail on its next poll.

## Folders that are not synced

The core does not sync folders with the special use `\All`, `\Important` or `\Flagged` (Gmail's All Mail, Important and
Starred, `mailbox.sync.skip_special_use`, see the core's
[Skipped folders](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sync.md#skipped-folders)). They stay in the
list and can be opened, but only show messages cached before, and their unread count is 0.

- Every entry of `folders` and the `folder` prop has `syncs`: `false` for such a folder.
- `syncNotice` is the translated `vue-mailbox::mailbox.not_synced_hint` for such a folder, `null` otherwise. The default
  page shows it above the list.
- `FolderList` mutes the folder, adds a "Niet gesynct" badge (`not_synced`) and shows the hint as tooltip.

## Refreshing

The folder view refreshes itself: the message table reloads every `vue-mailbox.poll_seconds` seconds while the tab is
visible (the TableBuilder pauses while the tab is hidden), and every reload of the page queues a folder sync when the
folder was not synced in the last 60 seconds. The default page reloads `table` and `folders` on each poll, so the unread
counts in the folder list follow.

The Refresh button posts to `mailbox.folders.refresh` (`POST {mailbox}/folders/{folder}/refresh`, `accessMailbox`,
at most 12 a minute per user through the package's own rate limiter `vue-mailbox-refresh`,
`VueMailboxServiceProvider::REFRESH_LIMITER`, so it shares no counter with other throttled routes). A host
changes the limit by defining the limiter again in a service provider's `boot()`, which runs after the package's:

```php
RateLimiter::for('vue-mailbox-refresh', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()->id));
``` It queues a `SyncFolderJob` for the folder whether or not it was synced recently and
returns to the folder. With a `sync` queue the new mail shows right away; with a queue worker it shows on the next poll.
The core job is unique per folder for 120 seconds, so repeated clicks do not stack syncs.

## Older messages

The core sync only goes back `mailbox.sync.initial_days` (90) days on the first sync of a folder. When the server holds
more messages in the open folder than are stored (the core's `message_count`, filled by the sync from the folder
status, against the stored rows) and the core has not marked the folder `older_exhausted`, `olderMessagesUrl` holds the `mailbox.folders.older` URL and the default page shows
an "Oudere berichten ophalen" button (`fetch_older`, `ui.button`) in the toolbar. A folder that is not synced never
offers it.

The button posts to `mailbox.folders.older` (`POST {mailbox}/folders/{folder}/older`, `accessMailbox`, the same
`vue-mailbox-refresh` limiter as Refresh, so both count towards one limit). It queues the core
`FetchOlderMessagesJob` for the folder and returns to the folder. The job stores the next `mailbox.sync.older_batch_size`
(100) older messages, newest first, without browser notifications, and waits for a running sync of the folder. See
the core's [Older messages](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sync.md#older-messages).

A folder that is not selectable, not synced or `older_exhausted` answers the route with `404` and queues nothing.

While the job runs the button reads "Bezig met ophalen…" (`fetching_older`) and is disabled. After a successful post
the page reloads `table`, `folders` and `olderMessagesUrl` every five seconds, at most four times, and stops as soon as
the table total or `olderMessagesUrl` changes. A failed post, or opening another folder, ends the pending state; the button disappears once everything is stored or the core
marked the folder `older_exhausted` (nothing older on the server, also when only messages flagged `\Deleted` are left),
and otherwise fetches the next batch on the next click.

## Notifications

The toolbar has a toggle for browser notifications on new mail in this mailbox while the app is open. A new message in the folder on
screen gets no notification while the tab is visible and has focus. See [Browser notifications](../notifications.md).

## Props

| Prop | Type | Description |
|---|---|---|
| `composeUrl` | `string \| null` | Compose URL, `null` without `workInMailbox` |
| `contactsUrl` | `string` | Address book URL |
| `folder` | `object \| null` | `id`, `label`, `name`, `role`, `syncs` of the open folder, `null` while syncing |
| `folderForm` | `FormSchema \| null` | Form to add a folder, `null` without `workInMailbox` |
| `folders` | `MailboxFolder[]` | Selectable folders of the mailbox, see below |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `mailboxes` | `MailboxSummary[]` | Active mailboxes the user can open, sorted by name, with the unread count of their inbox (`unseen`); empty when `vue-mailbox.mailbox_switcher` is `false` |
| `olderMessagesUrl` | `string \| null` | `mailbox.folders.older` URL when the server holds older messages of the open folder than are stored and the folder is not `older_exhausted`, otherwise `null`, see [Older messages](#older-messages) |
| `refreshUrl` | `string \| null` | `mailbox.folders.refresh` URL of the open folder, `null` while syncing |
| `settingsUrl` | `string \| null` | Settings URL, `null` without `manageMailbox` |
| `syncNotice` | `string \| null` | Translated explanation for a folder that is not synced, otherwise `null` |
| `syncing` | `string \| null` | Translated "syncing" notice while there is no folder yet |
| `table` | `TableData \| null` | The message table, `null` while syncing |

`folderForm`, `folders`, `mailboxes` and `olderMessagesUrl` are lazy closures. A partial reload that asks only for
`table` does not compute them, so polling the message list does not query the folder list again.

### Mailbox switcher

The page shows only the folders of the open mailbox. When `mailboxes` holds more than one mailbox, the default page
shows a compact select at the top to switch to another mailbox. A host that lists every mailbox in its own menu (see
[Navigation](../navigation.md)) turns the switcher off with `vue-mailbox.mailbox_switcher` set to `false`.

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
| `syncs` | `false` when the core does not sync the folder |
| `unseen` | Number of unread messages |
| `url` | `mailbox.folders.show` URL of the folder |

The message view, compose and address book pages receive the same `folders` prop.

## Message table

The table is a `MessagesTable` (TableBuilder). It polls every `vue-mailbox.poll_seconds` seconds. Render it with
`:only="['table']"` so a poll only reloads the table:

```vue
<TableBuilder :table="table" :only="['table', 'folders']" />
```

With the conversation view of the mailbox on, the table shows one row per conversation. See
[Tables](../tables.md#conversations).

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
