# Message view

Route `mailbox.messages.show` (`GET {mailbox}/messages/{message}`) renders the `vue-mailbox.pages.show` page, default
`mailbox/Show`.

## Loading the body

The controller loads the message body from the server through the core package when it is not cached, see
[reading a message](https://github.com/J87NL/laravel-mailbox/blob/main/docs/message-actions.md#reading-a-message). When
the message no longer exists on the server (moved or deleted in another mail client), the controller forgets the stored
message and redirects to its folder with an `error` flash message.

When a user with `workInMailbox` opens an unread message, it is marked as read. A reader with only `accessMailbox`
leaves the read state alone.

## Props

| Prop | Type | Description |
|---|---|---|
| `actionsUrl` | `string \| null` | `mailbox.messages.actions` URL, `null` without `workInMailbox` |
| `attachments` | `MailboxAttachment[]` | Downloadable attachments, see below |
| `backUrl` | `string` | URL of the message's folder |
| `composeUrl` | `string \| null` | Compose URL, `null` without `workInMailbox` |
| `contactUrl` | `string \| null` | "Add to address book" URL, `null` without `workInMailbox` or without a sender address |
| `conversation` | `ConversationEntry[]` | The messages of the conversation, oldest first, see below; empty when the conversation view is off or the message is alone |
| `folders` | `MailboxFolder[]` | Folder list, see [Folders and message list](folders.md#folder-list) |
| `hasRemoteImages` | `boolean` | The HTML references remote images that are blocked |
| `history` | `AuditEntry[]` | The last 20 audit entries with the message's `Message-ID` in this mailbox, newest first |
| `html` | `string` | The sanitized message HTML, rendered by the core package |
| `imageOrigin` | `string` | Scheme, host and port of the current request, for the image policy of the frame |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `message` | `object` | `id`, `subject`, `from_email`, `from_name`, `to`, `cc`, `sent_at`, `is_flagged`, `is_answered` |
| `remoteImages` | `boolean` | Remote images are allowed in this render |

`to` and `cc` are lists of `{ email, name }`. A flagged message shows a flag before its subject, labelled with the
`flagged` translation.

## Remote images

Whether remote images are blocked is set in the core config, see
[HTML rendering](https://github.com/J87NL/laravel-mailbox/blob/main/docs/html.md#remote-images). While they are blocked
and the message has any, `hasRemoteImages` is `true` and the default page shows a notice with a link to `?images=1`.
That query parameter renders the message once with remote images allowed.

## The message frame

The default page renders `html` with the `MessageFrame` component. It writes the HTML into an `<iframe srcdoc>` with:

- `sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"`, so scripts never run
- a Content Security Policy of `default-src 'none'`, inline styles, `data:` fonts and images from `data:` and
  `imageOrigin` (inline images), or from anywhere when `remoteImages` is `true`
- `referrerpolicy="no-referrer"` and `<base target="_blank">`, so links open in a new tab

The frame is at least as tall as the space left in the viewport below it, and grows with its content, also when images
load later. See [Components and helpers](../frontend/components.md#messageframe).

## Full screen

The Full screen button opens the message in a full-screen dialog that the package renders itself (a `role="dialog"`
overlay teleported to `<body>`, `z-40`), so it does not depend on a dialog component of the host. The reply, forward,
archive, delete, flag, move and address book actions stay available in it. Esc (also inside the message) and the Close
button close it, and focus returns to the button. While it is open, every other element directly under `<body>` is
`inert`, so keyboard focus and assistive technology stay inside the dialog; elements added later, such as the host's
confirm dialog, are not affected. Flag and unflag keep the dialog open (`preserveState`); mark unread, archive,
delete and move go to the folder. The host's confirm dialog, which the delete action asks through
`confirm`, needs a higher z-index than `40` to show on top; the dialogs of shadcn-vue and reka-ui use `z-50`.

## Conversation

When the mailbox has the conversation view on (core setting `conversation_view`), `conversation` lists up to 50
messages of the mailbox with the same conversation key, across folders, oldest first. For a longer conversation it is
a window around the open message, so the open message is always in it. Each entry has `id`, `subject`,
`from` (name or address), `folder` (translated role or name), `sent_at` (ISO 8601), `url` and `current`. The default
page shows them above the message, with the open message marked. See the core
[conversation docs](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sync.md#conversations).

## Attachments

Each item of `attachments` is the stored attachment metadata (`filename`, `content_type`, `content_id`, `inline`)
plus a `url` to `mailbox.attachments.download`. Inline parts with a content id are left out: they show in the message
itself through [Inline images](../inline-images.md).

`GET {mailbox}/messages/{message}/attachments/{index}` streams the attachment as a download:

- `Content-Type: application/octet-stream`, `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`
- path separators and control characters in the filename are replaced by `-`; an empty name becomes `attachment`
- an unknown index, or a message that no longer exists on the server, answers `404`

## History

Entries come from the core [audit trail](https://github.com/J87NL/laravel-mailbox/blob/main/docs/audit-trail.md),
matched on the `Message-ID` across the whole mailbox, so they follow the message through moves. A message without a
`Message-ID` has an empty history.

Each `history` entry has `action` (an
[audit action](https://github.com/J87NL/laravel-mailbox/blob/main/docs/audit-trail.md#actions) value), `at` (ISO 8601),
`target` (a folder path, for moves) and `user` (the user's `name`, or `null` for the system). The default page shows
them with the `audit_*` translations.

## Toolbar

The default page offers, depending on the URLs it gets:

- Reply, Reply all and Forward, linking to `composeUrl` with `?reply={id}&mode=reply|reply_all|forward`. See
  [Compose, reply and forward](compose.md).
- Archive, Delete (with a confirmation), Mark unread, Flag or Unflag, and a "Move to" select, all posted to
  `actionsUrl`. See [Message actions](folders.md#message-actions).
- Add to address book, posted to `contactUrl`.
- Full screen, see above.
- The toggle for browser notifications of this mailbox, see [Browser notifications](../notifications.md).

The actions are the `MessageActions` component, used both in the toolbar and in the full-screen dialog.

### Extra actions of the host

The page has an `actions` slot, rendered after the package actions in the toolbar and again in the full-screen
dialog, so host actions stay available there too. A host wraps the page and fills the slot:

```vue
<MailboxShow v-bind="props">
    <template #actions>
        <Link :href="todoUrl" class="...">Todo from mail</Link>
    </template>
</MailboxShow>
```

## Add the sender to the address book

`POST {mailbox}/messages/{message}/contact` (`mailbox.messages.contact`) adds the sender's address and name to the
address book of the mailbox through the core `AddContact` action. It answers `404` when the message has no sender
address and redirects back with a `success` flash message. See [Address book](contacts.md).
