# Changelog

## [Unreleased]

- `mailbox.settings.index` needs the new ability `manageAnyMailbox` (`manageMailboxes`, or `manageMailbox` on at least one mailbox). It is only defined when the host has not defined it, and follows the host's own `manageMailboxes` and `manageMailbox`. Gate the host's settings menu link on it. `MailboxesTable::authorize()` checks the same ability.
- `InlineUrls::urlPrefix()` ends with a slash again, so it no longer matches URLs that merely start with the same path.
- Compose with a source message refuses an unknown `?mode=` with a 404 instead of treating it as a reply.
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
