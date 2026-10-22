# Customizing pages

The package ships seven Inertia pages in its `resources/js/pages`. By default the app loads them from the package (see
[Installation](../installation.md#3-load-the-pages-from-the-package)), so package updates change them too.

| Page name (default) | File in the package | Docs |
|---|---|---|
| `mailbox/Index` | `pages/Index.vue` | [Folders and message list](../pages/folders.md) |
| `mailbox/Show` | `pages/Show.vue` | [Message view](../pages/messages.md) |
| `mailbox/Compose` | `pages/Compose.vue` | [Compose, reply and forward](../pages/compose.md) |
| `mailbox/Contacts/Index` | `pages/Contacts/Index.vue` | [Address book](../pages/contacts.md) |
| `mailbox/Contacts/Edit` | `pages/Contacts/Edit.vue` | [Address book](../pages/contacts.md) |
| `mailbox/Settings/Index` | `pages/Settings/Index.vue` | [Mailbox settings](../pages/settings.md) |
| `mailbox/Settings/Edit` | `pages/Settings/Edit.vue` | [Mailbox settings](../pages/settings.md) |

The pages set no Inertia layout and do not show flash messages. The controllers flash `success` and `error` messages
to the session; show them in your own layout if you want them.

There are three ways to change a page. Pick the smallest one that does the job.

## Replace one page

Each controller renders the page named in `config('vue-mailbox.pages')`. Point a key at a page of your own and keep the
other keys on the package pages:

```php
// config/vue-mailbox.php
'pages' => [
    'compose' => 'mailbox/Compose',
    'contacts_edit' => 'mailbox/Contacts/Edit',
    'contacts_index' => 'mailbox/Contacts/Index',
    'index' => 'mailbox/Index',
    'settings_edit' => 'mailbox/Settings/Edit',
    'settings_index' => 'mailbox/Settings/Index',
    'show' => 'Inbox/Message',
],
```

Keep all seven keys: your `pages` array replaces the package's `pages` array as a whole. `Inbox/Message` is then
`resources/js/pages/Inbox/Message.vue` in your app. Your page receives the same props as the
default page; they are listed on each page's docs. Start from a copy of the package file if you like.

## Copy all pages

```bash
php artisan mailbox:install --pages
```

This copies the pages to `resources/js/pages/Mailbox` (`Mailbox/Index.vue`, `Mailbox/Contacts/Index.vue`, ...) and
changes every default name in `config/vue-mailbox.php` to its copy, so `mailbox/Index` becomes `Mailbox/Index`. Names
you changed yourself are left alone, and copies that already exist are not overwritten. Your app's resolver finds the
copies like any other app page.

With only the publish step, the config keeps pointing at the package pages until you change the names yourself:

```bash
php artisan vendor:publish --tag="vue-mailbox-pages"
```

Copied pages no longer follow package updates. Compare them with the package pages after an update, see
[Upgrading](../upgrading.md). The copies keep importing the shared components and helpers from `@mailbox/...`, so the
`@mailbox` alias stays required.

## Build from the shared parts

The default pages are built from parts you can import in your own pages:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TableBuilder from '@table-builder/components/TableBuilder.vue';
import type { TableData } from '@table-builder/types/table-builder';
import FolderList from '@mailbox/components/FolderList.vue';
import { useMailboxTranslations } from '@mailbox/translations';
import type { MailboxFolder, MailboxSummary } from '@mailbox/types';

defineProps<{
    folder: { id: number; label: string; name: string; role: string } | null;
    folders: MailboxFolder[];
    mailbox: MailboxSummary;
    table: TableData | null;
}>();

const { t } = useMailboxTranslations();
</script>

<template>
    <div class="flex gap-4">
        <Head :title="mailbox.name" />
        <FolderList :folders="folders" :active-id="folder?.id" />
        <TableBuilder v-if="table" :table="table" :only="['table']" />
        <p v-else>{{ t('syncing') }}</p>
    </div>
</template>
```

See [Components and helpers](components.md) and [Translations](translations.md).
