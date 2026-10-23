# Changelog

## [Unreleased]

- The mailbox settings pages drop their `p-4` padding when a layout provides `pagePadding` as `false` (`useMailboxPagePadding()` from `@mailbox/padding`), so a host can render them inside a settings layout that pads its content itself. Without a provider nothing changes.

## 0.5.3 - 2026-10-23

- Bulk actions and row actions in the message list, the contacts list, the mailbox settings list and the access table ask through the app's `confirm` function (the same one the message view uses) instead of the browser's `window.confirm`.

## 0.5.2 - 2026-10-23

- Requires `j87nl/laravel-mailbox` ^0.3.4.
- Opening a message marks it read with the core's `MarkMessages::readInBackground()`: the row, the unread count and the audit trail change right away, the `\Seen` flag is set on the server after the response. Opening no longer waits for that IMAP round trip.
- Inline images and attachment downloads are served from the raw message the core caches on the first fetch, so a message with many inline images costs one IMAP fetch instead of one per image. The routes keep their signature and access checks.

## 0.5.1 - 2026-10-23

- `mailbox.index` no longer answers `404` when the user can open no active mailbox. A user with `manageAnyMailbox` is redirected to `mailbox.settings.index`; anyone else gets the new empty state page `vue-mailbox.pages.empty` (default `mailbox/Empty`, props `title` and `hint`): "No mailbox has been set up for you yet", ask an administrator.
- The package ships its own translation file `vue-mailbox::mailbox` (nl and en) for the empty state, publishable with the `vue-mailbox-translations` tag.
- The default pages ask for delete confirmations through `useMailboxConfirm()` from `@mailbox/confirm`, which injects the host's `confirm` function (the same key as `j87nl/laravel-vue-cms`), instead of `window.confirm`. Without a provided function a delete does nothing.
- Upgrade: an app with copied pages and no `mailbox/` resolver branch points the new `empty` page name at a copy. See `docs/upgrading.md`.

## 0.5.0 - 2026-10-23

- **Breaking:** the Vue pages stay in the package by default (like `j87nl/laravel-vue-cms`). The default page names in `vue-mailbox.pages.*` start with `mailbox/` (`mailbox/Index`, `mailbox/Show`, `mailbox/Compose`, `mailbox/Contacts/Index`, `mailbox/Contacts/Edit`, `mailbox/Settings/Index`, `mailbox/Settings/Edit`), and the files moved inside the package from `resources/js/pages/Mailbox/*` to `resources/js/pages/*`. The host's Inertia resolver loads names that start with `mailbox/` from `@mailbox/pages` (also in `ssr.ts`). The components, `types.ts`, `translations.ts` and `ui.ts` did not move.
- **Upgrade:** compatibility runs only through the config. An app whose published `config/vue-mailbox.php` names its own copies for every key (such as Trefpunt) needs no app change. These apps break at runtime ("page not found") until they act: an app with copies in `resources/js/pages/Mailbox` but no published config or no `pages` in it, and an app that loaded `Mailbox/*` from the vendor directory as the 0.4 docs suggested. They point the names at their copies, or switch to `mailbox/` and add the resolver branch. See `docs/upgrading.md`.
- A `pages` array in the published config is merged with the defaults per key, so a key left out keeps its default instead of rendering `null` (which threw).
- `mailbox:install --pages` still copies the pages to `resources/js/pages/Mailbox` with the same file names as before, and now also changes every default page name in `config/vue-mailbox.php` (single or double quoted) to its copy (`mailbox/Index` becomes `Mailbox/Index`). Names the app changed itself are left alone.
- `mailbox:install` warns when `resources/js/pages/Mailbox/Index.vue` exists while the `index` page name starts with `mailbox/`, so unused copies do not go unnoticed, and when a `Mailbox/...` page name has no file in `resources/js/pages`, so an app that loaded the pages from the vendor directory hears about it. Without `--pages` it reminds you to add the resolver branch.
- The docs describe the resolver (typed as `import.meta.glob<{ default: DefineComponent }>`), apps on the `@inertiajs/vite` plugin without a resolver of their own, SSR, the Tailwind source `vendor/j87nl/laravel-vue-mailbox/resources/js` (the whole folder, `ui.ts` included) and an upgrade page.

## 0.4.0 - 2026-10-21

- **Breaking:** `mailbox.settings.index` needs the new ability `manageAnyMailbox` (`manageMailboxes`, or `manageMailbox` on at least one mailbox). It is only defined when the host has not defined it, and follows the host's own `manageMailboxes` and `manageMailbox`. Gate the host's settings menu link on it. `MailboxesTable::authorize()` checks the same ability. Upgrade: users who manage no mailbox now get a 403 instead of an empty list, so show the host's settings link only with `@can('manageAnyMailbox')`. A host that already shows it only for `manageMailboxes` (such as Trefpunt) needs no change.
- `InlineUrls::urlPrefix()` ends with a slash again, so it no longer matches URLs that merely start with the same path.
- Compose with a source message refuses an unknown `?mode=` with a 404 instead of treating it as a reply; a non-string `mode` (such as `mode[]=`) also gives a 404 instead of a 500.
- The README says the host's Inertia page resolver must find the `Mailbox/*` pages.

## 0.3.1 - 2026-10-19

- Requires `j87nl/laravel-mailbox` ^0.3.2, which no longer allows vulnerable versions of `directorytree/imapengine`, `symfony/html-sanitizer` and `symfony/mailer`.

## 0.3.0 - 2026-10-14

- Reply, reply all and forward quote the original HTML like Gmail: "On … wrote:" above a bordered blockquote, or a "Forwarded message" header block with From, Date, Subject, To and Cc. Inline images show in the editor and are sent as embedded parts.
- `InlineUrls` signs and parses the inline image URLs and implements the core `ResolvesInlineImageUrls`.
- Inline SVG images are served with `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox` and `nosniff`.
- After sending a reply or forward the app returns to the folder of the original message.
- Requires `j87nl/laravel-mailbox` ^0.3.
- `InlineUrls` parses with a copy of the inline route, so it never changes the route the app is handling, and reports the inline URL prefix so sent mail never carries a signed inline URL.

## 0.2.0 - 2026-10-13

- Requires `j87nl/laravel-mailbox` ^0.2, `tranquil-tools/laravel-vue-form-builder` ^1.10 and `tranquil-tools/laravel-vue-table-builder` ^1.9.
- Message list polls every 30 s (`vue-mailbox.poll_seconds`), marks unread rows bold on the server and has a "Move to folder" bulk action.
- Recipient chips with suggestions from the new `mailbox.addresses` endpoint.
- Forward with the original attachments, each removable.
- Address book pages and routes (`mailbox.contacts.*`) and "Add to address book" on the message view (`mailbox.messages.contact`).
- Signature in new messages, replies and forwards, above the quote.
- Default pages are fully translated through the `vue_mailbox_translations` prop, shared only on the mailbox routes (`ShareMailboxTranslations` middleware).
- Forwarding reloads pruned attachment metadata before offering the originals.
- One folders query per page view, also while the message list polls.
- `mailbox:install` publishes config and migrations and adds the `@mailbox` alias and tsconfig paths (`--pages` copies the pages).
- Page contract changes: see the README table (`contactsUrl`, `contactUrl`, `:only="['table']"`, no `row-class`, recipients as lists).

## 0.1.2 - 2026-10-13

- Show a "being synchronised" state instead of a 404 before the first sync.
- Reply to the original recipients when replying to a message the mailbox sent itself.
- Format list dates and the reply quote in the display timezone.
- Show translated labels for inbox, drafts, sent, archive, junk and trash.
- Replace the host-specific `btn` classes with self-contained Tailwind utility classes.
- Require j87nl/laravel-mailbox ^0.1.2.
- Document the Tailwind source path and the local development setup; add `.gitattributes`.
- Only fetch the folder list on demand when the mailbox has no inbox yet.
- Compare own-sender reply recipients case-insensitively.

## 0.1.0 - 2026-10-13

- First release.
