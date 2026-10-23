# Translations

The default pages are fully translated. They read their texts from the core translation file `mailbox::mailbox` and
this package's own file `vue-mailbox::mailbox` (for texts that only the UI uses, such as `refresh`, `open_fullscreen`,
`close`, `conversation`, `switch_mailbox`, `has_attachments` and `message_frame_title`). Both ship in Dutch (`nl`) and English (`en`). The
locale is the app locale.

## The shared prop

`J87NL\VueMailbox\Http\Middleware\ShareMailboxTranslations` shares the whole `mailbox::mailbox` file, merged with `vue-mailbox::mailbox`, as the Inertia prop
`vue_mailbox_translations` (`ShareMailboxTranslations::PROP`). The package adds this middleware to its own routes only,
so other pages of your app do not carry the prop.

A page outside the mailbox routes that needs the texts adds the middleware to its route:

```php
use J87NL\VueMailbox\Http\Middleware\ShareMailboxTranslations;

Route::get('dashboard', DashboardController::class)
    ->middleware(ShareMailboxTranslations::class);
```

## In Vue

`useMailboxTranslations()` from `@mailbox/translations` reads the prop. It wraps `useTranslations()` of the
TableBuilder with the `mailbox` group.

```vue
<script setup lang="ts">
import { useMailboxTranslations } from '@mailbox/translations';

const { t } = useMailboxTranslations();
</script>

<template>
    <p>{{ t('sent_from', { email: 'team@example.com' }) }}</p>
</template>
```

`t(key, replacements)` replaces `:name` placeholders. A missing key returns its last dot segment. The module also
exports the prop name as `MAILBOX_TRANSLATIONS`.

## Texts of this package

The empty state of `mailbox.index` uses this package's own file `vue-mailbox::mailbox` (`no_mailbox_title`,
`no_mailbox_hint`, nl and en). The controller translates them and passes them as the `title` and `hint` props, so they
are not part of the shared prop. Override them in `lang/vendor/vue-mailbox/{locale}/mailbox.php`, or publish them with
the `vue-mailbox-translations` tag.

## Changing texts

The texts belong to `j87nl/laravel-mailbox`. Override them the Laravel way for package translations, in
`lang/vendor/mailbox/{locale}/mailbox.php`, or publish them with the `mailbox-translations` tag, see the core
[installation](https://github.com/J87NL/laravel-mailbox/blob/main/docs/installation.md#publish-config-and-migrations)
docs. The PHP side (flash messages, table labels, form labels) and the Vue pages then use the same text.
