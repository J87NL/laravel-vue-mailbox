# Changelog

## 0.9.4 - 2026-10-30

- `FolderList` shows hover and the active folder with a translucent background, so both stay visible on a gray host page. The active folder no longer lightens on hover.
- `ui.select` keeps room on the right (`pr-8`) for the caret, which ran through the text of the mailbox switcher and the "Move to" select with `@tailwindcss/forms`.
- Docs: `docs/frontend/components.md` describes the folder states and the right padding of `ui.select`.
- No migration and no config publish. Rebuild the frontend (`npm run build`).

## 0.9.3 - 2026-10-29

- `composer.json` allows `j87nl/laravel-mailbox` 0.9 (`^0.5.3||^0.6||^0.7||^0.8||^0.9`). Core 0.9 marks its MCP tools as open world and adds `MailboxMcp::readTools()`; nothing changes in the UI. See `docs/upgrading.md`.
- Docs: `docs/installation.md` names the supported core versions (0.5.3 up to 0.9).
- No migration, no config publish and no frontend rebuild needed.

## 0.9.2 - 2026-10-29

- `composer.json` allows `j87nl/laravel-mailbox` 0.8 (`^0.5.3||^0.6||^0.7||^0.8`). With core 0.8 a user with `Manage` on an inactive mailbox, through their own row or a group, passes `manageAnyMailbox`, sees the mailbox in the `MailboxesTable`, opens `mailbox.settings.edit` and can set it active again through `mailbox.settings.update`, also without `MAILBOX_ADMINS`. The folder, message, compose, refresh and notification routes still refuse them, and `mailbox.index` sends them to the settings when they have no active mailbox. A non-admin creator of an inactive mailbox now reaches its edit page through the `Manage` row `store` grants. No code change in this package was needed.
- Docs: new section `docs/pages/settings.md#inactive-mailboxes`, and `docs/installation.md`, `docs/routes.md`, `docs/tables.md` and `docs/upgrading.md` describe managers of inactive mailboxes.
- No migration, no config publish and no frontend rebuild needed.

## 0.9.1 - 2026-10-28

- Docs: `docs/installation.md` and `docs/navigation.md` state the menu gate as `@canany(['accessAnyMailbox', 'manageAnyMailbox'])`, say that `manageAnyMailbox` comes from this package and not from the core, and what an app without this package gates on. `docs/installation.md` notes that from `j87nl/laravel-mailbox` 0.7 a user filtered out by `access.user_query` passes no mailbox ability, also as admin or in open mode.
- `composer.json` allows `j87nl/laravel-mailbox` 0.7 (`^0.5.3||^0.6||^0.7`), so the core with `access.user_query` for admins and open mode can be installed next to this package.
- No migration, no config publish and no frontend rebuild needed.

## 0.9.0 - 2026-10-28

- `mailbox.index` lets in users who pass `accessAnyMailbox` or `manageAnyMailbox`. A user who passes an app-defined `manageMailboxes` but has no mailbox to open is now redirected to `mailbox.settings.index` instead of getting a 403. The route no longer has the `can:accessAnyMailbox` middleware; `FolderController::index()` checks both abilities. Gate the menu item on both, see `docs/navigation.md#menu-gate`.
- `mailbox:install` adds the `@mailbox` alias to a Vite config whose `resolve.alias` is an array of `{ find, replacement }` entries, and imports `fileURLToPath` from `node:url` when needed. Before, it could only patch the object form. New `J87NL\VueMailbox\Support\ViteAliasArray`.
- `docs/installation.md`: `composer require j87nl/laravel-vue-mailbox:^0.9 -W` and why (an app with older `tranquil-tools/*` locked otherwise silently gets 0.1.x), the alias array, an example for apps on `resolvePageComponent()` with `./Pages`, wrapping the package pages in the app layout (`page.layout ??= Layout`), the `confirm` provide, and the lazy registration of the HugeRTE editor with the plugins imported after it.
- Allows `j87nl/laravel-mailbox` ^0.6 next to ^0.5.3, for its `access.user_query` and per-group `query`.
- No migration, no config publish and no frontend rebuild needed. See `docs/upgrading.md`.

## 0.8.2 - 2026-10-26

- Folders that the core does not sync (Gmail's All Mail, Important and Starred, `mailbox.sync.skip_special_use` in `j87nl/laravel-mailbox` 0.5.3) are marked. Every entry of the `folders` prop and the `folder` prop have a new boolean `syncs`, `false` when `MailboxFolder::skipsSync()` is true. The folder page has a new prop `syncNotice`: the translated explanation for such a folder, `null` otherwise.
- `FolderList` shows these folders muted with a "Niet gesynct" badge and a tooltip with the explanation. They stay clickable. The default `Index` page shows the explanation above the list.
- New translations `not_synced` and `not_synced_hint` in `vue-mailbox::mailbox` (nl and en).
- Requires `j87nl/laravel-mailbox` ^0.5.3. Rebuild the frontend after updating. See `docs/upgrading.md`.

## 0.8.1 - 2026-10-26

- Browser notifications are switched per mailbox. The toggle on the folder page and the message view now switches the open mailbox only ("Meldingen aanzetten voor deze mailbox" / "Meldingen uitzetten voor deze mailbox", nl and en). `NotificationToggle` and `useMailboxNotifications()` take a `mailboxId`; without it they switch all mailboxes at once as before.
- The choice is stored per browser as a map of mailbox ids in `localStorage` (`vue-mailbox.notifications`). The value `on` of 0.8.0 means all mailboxes the user may read and becomes that map on the first poll, so existing users keep their notifications. The tab does not poll when no mailbox has notifications on.
- `mailbox.notifications` takes optional `mailboxes[]` ids and only returns messages of those mailboxes the user may read; ids of other mailboxes are ignored, ids that are not whole numbers answer `422`. The answer has a new `mailboxes` key with the ids it used.
- Copied `Index` or `Show` pages pass `:mailbox-id="mailbox.id"` to the toggle. See `docs/upgrading.md`.

## 0.8.0 - 2026-10-26

- Browser notifications for new mail while a page of the app is open (no service worker, no web push). The folder page and the message view have a toggle, the new `NotificationToggle` component ("Meldingen aanzetten" / "Meldingen uitzetten", disabled with "Meldingen geblokkeerd door browser" when the user denied the permission). It asks the browser for permission only on a click, never on load, and remembers the choice per browser in `localStorage` (`vue-mailbox.notifications`). See `docs/notifications.md`.
- While notifications are on, the open tab polls the new route `GET notifications?after=<cursor>` (`mailbox.notifications`) every `vue-mailbox.poll_seconds` seconds. It needs `accessAnyMailbox`, is throttled to 60 requests per minute per user (`VueMailboxServiceProvider::NOTIFICATIONS_LIMITER`) and returns the unread INBOX messages newer than the cursor and sent in the last 24 hours (or undated) of the active mailboxes the user passes `accessMailbox` for, skipping messages sent from those mailbox addresses or the user's own address: `count`, the newest ten as `messages` (`MailboxNotifications::LIMIT`) and the next `cursor`. The first poll only fetches the cursor, so messages that were already there get no notification.
- One notification per new message (sender, subject and mailbox name), or one "N nieuwe berichten" notification when more than three arrive in one poll. Clicking focuses the tab and opens the message. A message in the folder on screen is skipped while the tab is visible and focused.
- The logic is the composable `useMailboxNotifications()` in `@mailbox/notifications`, so a host can put the toggle in its own layout. `ShareMailboxTranslations` and `ShareMailboxNavigation` share the new prop `vueMailboxNotifications` (`url`, `poll_seconds`, translated `labels`), `null` without `accessAnyMailbox`.
- New `MailboxNavigation::visible($user)`: the active mailboxes the user may open, as models.
- New translations `new_messages` and `notifications_*` in `vue-mailbox::mailbox` (nl and en).
- Rebuild the frontend after updating. See `docs/upgrading.md`.

## 0.7.0 - 2026-10-24

- The access form on the mailbox settings page no longer sends every user and group to the browser. Its `accessor` select searches the new route `GET settings/{mailbox}/accessors?q=<term>` (`mailbox.settings.accessors`) from two characters on, which returns at most 20 matching users (by name or e-mail) and groups (by label) as `[{value, label}]`. The route needs `manageMailbox`, like the rest of the access section, and is throttled to 60 requests per minute per user (`ACCESSOR_SEARCH_LIMITER`).
- Granting access validates the accessor with the core `ExistingAccessor` rule instead of comparing it with the full list.
- Requires `j87nl/laravel-mailbox` ^0.5.0 and `tranquil-tools/laravel-vue-form-builder` ^1.13. Rebuild the frontend after updating. See `docs/upgrading.md`.

## 0.6.0 - 2026-10-24

This release needs the core migration of `j87nl/laravel-mailbox` 0.4.0 and drops the attachment column. After deploying, run `php artisan mailbox:threads` once.

- Requires `j87nl/laravel-mailbox` ^0.4.0 (publish and run its new migration `add_conversations_to_mailbox_tables`, then run `php artisan mailbox:threads` once).
- New `J87NL\VueMailbox\Support\MailboxNavigation::for($user)`: the active mailboxes the user may open, sorted by name, with `id`, `name`, `email`, `unseen` (inbox unread count), `url` and `active_path`, for a host menu with one item per mailbox. The new `ShareMailboxNavigation` middleware shares it as the Inertia prop `mailboxNavigation`. See `docs/navigation.md`.
- The folder page shows only the folders of the open mailbox. The stacked list of all mailboxes is replaced by a compact mailbox switcher at the top, shown when the user has more than one mailbox; the new config key `mailbox_switcher` (default `true`) turns it off for hosts that list the mailboxes in their own menu. The `mailboxes` prop items now carry `unseen` and `active_path`.
- The message list has no attachment column any more: a paperclip icon (with a translated accessible label) follows the subject of a message with attachments.
- Conversation view: when a mailbox has the core `conversation_view` setting on, the message list shows one row per conversation (latest message, message count, bold while any message is unread), a bulk action on that row runs on the whole conversation in the folder, and the message view gets a `conversation` prop with up to 50 messages of the conversation across folders, around the open message. While a search or filter is active the list shows the matching messages instead of conversations, and bulk actions run on the selected messages only.
- The reading pane uses the height left in the viewport and keeps growing with its content when images load. A Full screen button opens the message in a package-owned full-screen dialog with the message actions; Esc and Close close it, the rest of the page is `inert` while it is open, and flag and unflag keep it open. The actions are the new `MessageActions` component. The page has an `actions` slot for host actions, rendered in the toolbar and in the full-screen dialog. The frame's title is translated (`message_frame_title`).
- Refresh button on the folder page: `POST {mailbox}/folders/{folder}/refresh` (`mailbox.folders.refresh`, `accessMailbox`, 12 a minute per user through the package's own rate limiter `vue-mailbox-refresh`) queues a sync of the folder even when it synced less than a minute ago. The table poll now also reloads the folder list, so unread counts follow.
- The shared `vue_mailbox_translations` prop also holds this package's own `vue-mailbox::mailbox` keys (`close`, `conversation`, `has_attachments`, `message_frame_title`, `open_fullscreen`, `refresh`, `refreshing`, `switch_mailbox`).
- The compose body sanitises the mailbox signature again with the core `MessageHtml::sanitizeSignature()`, so a signature stored outside `SaveMailbox` cannot bring active content into the editor.
- Opening a message whose remote copy is gone (moved or relabelled in another client) is no longer reported as an error: `MessageBodyLoader` writes an info line to the log, forgets the row and returns to the folder with the "no longer on the server" message.
- New `configureMailboxUi()` in `@mailbox/ui`: an app replaces the button, select, panel and text classes of the default pages with its own (for example its button variants and theme colours) once in its entrypoint. `ui` is now typed as `MailboxUi`. Without a call nothing changes.
- The message view shows a flag before the subject of a flagged message (new translation `flagged`, nl and en).
- Creating a mailbox grants the creator `Manage` on it when they could not manage it otherwise, for apps that let non-admins pass `manageMailboxes`. Before, such a creator was sent to the edit page of the new mailbox and got a 403. Admins (`MAILBOX_ADMINS`) get no row, as before. When the grant fails, the new mailbox is deleted again; the connection test and the queued sync run outside any database transaction. The core still lets only admins manage an inactive mailbox, so a non-admin who creates an inactive mailbox gets a 403 on its edit page unless the app defines `manageMailbox` itself.
- `mailbox.settings.destroy` now needs `manageMailbox` on the mailbox as well as `manageMailboxes`, so an app that lets non-admins pass `manageMailboxes` no longer lets them delete a mailbox they have no access to. Admins (`MAILBOX_ADMINS`) manage every mailbox, so nothing changes for them.
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
