# Changelog

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
