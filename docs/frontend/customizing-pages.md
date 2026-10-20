# Customizing pages

The package ships seven Inertia pages in `resources/js/pages/Mailbox`:

| Page | Docs |
|---|---|
| `Mailbox/Index` | [Folders and message list](../pages/folders.md) |
| `Mailbox/Show` | [Message view](../pages/messages.md) |
| `Mailbox/Compose` | [Compose, reply and forward](../pages/compose.md) |
| `Mailbox/Contacts/Index` | [Address book](../pages/contacts.md) |
| `Mailbox/Contacts/Edit` | [Address book](../pages/contacts.md) |
| `Mailbox/Settings/Index` | [Mailbox settings](../pages/settings.md) |
| `Mailbox/Settings/Edit` | [Mailbox settings](../pages/settings.md) |

The pages set no Inertia layout and do not show flash messages. The controllers flash `success` and `error` messages
to the session; show them in your own layout if you want them.

## Publish the default pages

```bash
php artisan mailbox:install --pages
```

Or with only the publish step:

```bash
php artisan vendor:publish --tag="vue-mailbox-pages"
```

Both copy the pages to `resources/js/pages/Mailbox`. Edit them there. The published pages keep importing the shared
components and helpers from `@mailbox/...`, so the `@mailbox` alias stays required.

## Use your own page names

Each controller renders the page named in `config('vue-mailbox.pages')`. Point a key at another component to replace a
single page:

```php
// config/vue-mailbox.php
'pages' => [
    'show' => 'Inbox/Message',
    // ...
],
```

Your page receives the same props as the default page. They are listed on each page's docs.

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
