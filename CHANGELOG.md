# Changelog

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
