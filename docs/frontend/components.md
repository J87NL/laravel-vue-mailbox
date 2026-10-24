# Components and helpers

Everything below is imported through the `@mailbox` alias, which points to
`vendor/j87nl/laravel-vue-mailbox/resources/js`.

| Import | Holds |
|---|---|
| `@mailbox/components/FolderList.vue` | The folder navigation |
| `@mailbox/components/MessageFrame.vue` | The sandboxed message iframe |
| `@mailbox/components/MessageActions.vue` | Reply, forward and message action buttons of the message view |
| `@mailbox/confirm` | `useMailboxConfirm()`, `MAILBOX_CONFIRM` |
| `@mailbox/translations` | `useMailboxTranslations()`, `MAILBOX_TRANSLATIONS` |
| `@mailbox/types` | `MailboxFolder`, `MailboxSummary`, `MailAddress`, `MailboxAttachment`, `AuditEntry` |
| `@mailbox/ui` | `ui`, the Tailwind class strings of the default pages, and `configureMailboxUi()` to replace them |

## FolderList

Renders `folders` as a list of Inertia links, indented by `depth`, with the unread count when it is above zero.

| Prop | Type | Description |
|---|---|---|
| `folders` | `MailboxFolder[]` | The `folders` prop of the mailbox pages |
| `activeId` | `number` (optional) | Id of the folder to highlight |

```vue
<FolderList :folders="folders" :active-id="folder?.id" />
```

## MessageFrame

Renders message HTML in an `<iframe srcdoc>` that runs no scripts and loads no remote content unless allowed. The frame is at
least 320 pixels tall and at least as tall as the space left in the viewport below it, and grows with its content
(a `ResizeObserver` follows images that load later). With `fill` it takes the height of its flex parent instead.

| Prop | Type | Description |
|---|---|---|
| `html` | `string` | The `html` prop of the message view |
| `imageOrigin` | `string` | The `imageOrigin` prop; images from this origin and `data:` are allowed |
| `remoteImages` | `boolean` | The `remoteImages` prop; `true` allows images from any origin |
| `fill` | `boolean` (optional) | Fill the parent instead of sizing to the content and viewport |

It emits `escape` when Esc is pressed inside the message, so a dialog around it can close.

```vue
<MessageFrame :html="html" :image-origin="imageOrigin" :remote-images="remoteImages" />
```

Pass the props of the message view unchanged. The frame does not sanitize the HTML itself; the server does that before
it sends `html`. See [Message view](../pages/messages.md#the-message-frame).

## MessageActions

The reply, reply all, forward, archive, delete (with a confirmation through `confirm`), mark unread, flag and move
controls, and "Add to address book". It renders only the controls whose URL it gets, inside a `display: contents` wrapper, so they lay out as items of
your toolbar. Every action posts with `preserveState`; flag and unflag return to the
message, the other actions go to the folder.

| Prop | Type | Description |
|---|---|---|
| `actionsUrl` | `string \| null` | The `actionsUrl` prop of the message view |
| `composeUrl` | `string \| null` | The `composeUrl` prop |
| `contactUrl` | `string \| null` | The `contactUrl` prop |
| `folders` | `MailboxFolder[]` | The `folders` prop, for the move select |
| `message` | `{ id: number; is_flagged: boolean }` | The `message` prop |

## Types

```ts
import type { AuditEntry, ConversationEntry, MailAddress, MailboxAttachment, MailboxFolder, MailboxSummary } from '@mailbox/types';
```

| Type | Fields |
|---|---|
| `MailboxFolder` | `id`, `label`, `name`, `path`, `role`, `depth`, `unseen`, `url` |
| `MailboxSummary` | `id`, `name`, `email`, optional `url`, `synced_at`, `unseen` and `active_path` |
| `MailAddress` | `email`, `name` |
| `MailboxAttachment` | `filename` (nullable), `content_type`, `content_id` (nullable), `inline`, `url` |
| `AuditEntry` | `action`, and the nullable `at`, `target`, `user` |
| `ConversationEntry` | `id`, `current`, `folder`, `from`, `url`, and the nullable `subject`, `sent_at` |

`MailboxFolder['role']` is `'inbox' | 'drafts' | 'sent' | 'archive' | 'junk' | 'trash' | 'custom'`.

## UI classes

`ui` holds the class strings the default pages use, with dark mode variants:

| Key | Used for |
|---|---|
| `button` | Secondary buttons and links |
| `primaryButton` | Primary actions (compose, reply) |
| `dangerButton` | Delete buttons |
| `select` | The "Move to" select |
| `panel` | Bordered content panels |
| `toolbar` | A wrapping row of buttons |
| `muted` | Secondary text |

```vue
<script setup lang="ts">
import { ui } from '@mailbox/ui';
</script>

<template>
    <button type="button" :class="ui.primaryButton">Send</button>
</template>
```

Tailwind only generates these classes when it scans the package, see [Installation](../installation.md#5-add-the-tailwind-css-source).

### Your own button and panel styles

The default classes use a neutral gray palette. To make the package pages follow your app's buttons, panels and theme
colours, replace any of the keys with `configureMailboxUi()` once in your app entrypoint, before the app mounts:

```ts
import { configureMailboxUi } from '@mailbox/ui';
import { buttonVariants } from '@/components/ui/button';

configureMailboxUi({
    button: buttonVariants({ variant: 'outline', size: 'sm' }),
    primaryButton: buttonVariants({ size: 'sm' }),
    panel: 'rounded-xl border bg-card p-4 text-card-foreground shadow-xs',
    muted: 'text-sm text-muted-foreground',
});
```

Keys you leave out (or pass as `undefined`) keep their default. The classes you pass live in your own code, so Tailwind finds them there. The
other classes of the pages (folder list, message header, conversation list) stay as they are.

## Confirmations

The default pages never call `window.confirm`. Delete in the message view, the contact form and the mailbox settings
asks through `useMailboxConfirm()` from `@mailbox/confirm`, which injects the function your app provides under the key
`confirm` (`MAILBOX_CONFIRM`). It takes the message and resolves to `true` when the user agrees:

```ts
app.provide('confirm', (message: string): Promise<boolean> => openConfirmDialog(message));
```

Without a provided function every confirmation resolves to `false`, so the delete does nothing. `j87nl/laravel-vue-cms`
uses the same key, so one provide serves both.
