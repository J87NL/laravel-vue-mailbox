# Components and helpers

Everything below is imported through the `@mailbox` alias, which points to
`vendor/j87nl/laravel-vue-mailbox/resources/js`.

| Import | Holds |
|---|---|
| `@mailbox/components/FolderList.vue` | The folder navigation |
| `@mailbox/components/MessageFrame.vue` | The sandboxed message iframe |
| `@mailbox/confirm` | `useMailboxConfirm()`, `MAILBOX_CONFIRM` |
| `@mailbox/translations` | `useMailboxTranslations()`, `MAILBOX_TRANSLATIONS` |
| `@mailbox/types` | `MailboxFolder`, `MailboxSummary`, `MailAddress`, `MailboxAttachment`, `AuditEntry` |
| `@mailbox/ui` | `ui`, the Tailwind class strings of the default pages |

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

Renders message HTML in an `<iframe srcdoc>` that runs no scripts and loads no remote content unless allowed. The frame
resizes to its content after loading, with a minimum height of 200 pixels.

| Prop | Type | Description |
|---|---|---|
| `html` | `string` | The `html` prop of the message view |
| `imageOrigin` | `string` | The `imageOrigin` prop; images from this origin and `data:` are allowed |
| `remoteImages` | `boolean` | The `remoteImages` prop; `true` allows images from any origin |

```vue
<MessageFrame :html="html" :image-origin="imageOrigin" :remote-images="remoteImages" />
```

Pass the props of the message view unchanged. The frame does not sanitize the HTML itself; the server does that before
it sends `html`. See [Message view](../pages/messages.md#the-message-frame).

## Types

```ts
import type { AuditEntry, MailAddress, MailboxAttachment, MailboxFolder, MailboxSummary } from '@mailbox/types';
```

| Type | Fields |
|---|---|
| `MailboxFolder` | `id`, `label`, `name`, `path`, `role`, `depth`, `unseen`, `url` |
| `MailboxSummary` | `id`, `name`, `email`, optional `url` and `synced_at` |
| `MailAddress` | `email`, `name` |
| `MailboxAttachment` | `filename` (nullable), `content_type`, `content_id` (nullable), `inline`, `url` |
| `AuditEntry` | `action`, and the nullable `at`, `target`, `user` |

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

## Confirmations

The default pages never call `window.confirm`. Delete in the message view, the contact form and the mailbox settings
asks through `useMailboxConfirm()` from `@mailbox/confirm`, which injects the function your app provides under the key
`confirm` (`MAILBOX_CONFIRM`). It takes the message and resolves to `true` when the user agrees:

```ts
app.provide('confirm', (message: string): Promise<boolean> => openConfirmDialog(message));
```

Without a provided function every confirmation resolves to `false`, so the delete does nothing. `j87nl/laravel-vue-cms`
uses the same key, so one provide serves both.
