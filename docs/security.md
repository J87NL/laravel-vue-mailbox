# Security

## Authorization

- Every mailbox route runs behind `config('vue-mailbox.routes.middleware')`, default `web` and `auth`. Keep `auth` in
  that list: the abilities expect a logged-in user.
- Each route checks a core ability with the `can` middleware. See [Routes and authorization](routes.md).
- Folders, messages and contacts are resolved through the mailbox in the URL (scoped bindings). A model of another
  mailbox answers `404`.
- Message actions, moves and new folders only accept ids and folders of the mailbox in the URL.
- The tables check the same abilities in their `authorize()` hooks. `MessagesTable` and `ContactsTable`, the tables
  with bulk actions, also check `workInMailbox` in `authorizeBulkAction()`, so the TableBuilder's signed bulk action
  requests are checked as well. See [Tables](tables.md).
- Controllers pass `null` for URLs the user may not call. Hiding a button is a convenience; the route check is the
  guard.

Two routes work differently:

- `mailbox.settings.index` only needs the route middleware. Its table lists only mailboxes the user can manage.
- `mailbox.inline` checks no ability and does not use the configured middleware. It only answers a valid signed URL.
  Anyone who holds such a URL can load that image until it expires (`vue-mailbox.signed_url_minutes`, default 30).

## Message HTML

- The core package sanitizes the HTML before the controller sends it as the `html` prop.
- `MessageFrame` renders it in an `<iframe srcdoc>` without `allow-scripts`, with a Content Security Policy of
  `default-src 'none'`, and with `referrerpolicy="no-referrer"`.
- Images from other origins are blocked while the core blocks remote images, until the user asks for them with
  `?images=1`. See [Message view](pages/messages.md#remote-images).

## Attachments and inline images

- Attachments download as `application/octet-stream` with `X-Content-Type-Options: nosniff`, and path
  separators and control characters in the filename are replaced by `-`.
- Inline parts are only served for five image types. SVG images get a locked-down policy with `sandbox`. See
  [Inline images](inline-images.md).
- Sent mail never carries a signed inline URL: quoted images are sent as embedded parts and pasted signed URLs are
  removed. To recognize quoted images, the send step accepts any correctly signed inline URL, even if it has expired;
  loading an image through the inline route still needs an unexpired signature.

## Input

- Bulk message actions accept at most 500 ids.
- The address suggestion query is cut to 100 characters.
- Contact email addresses are trimmed and lower-cased before validation.
