# Inline images

Inline images are message parts referenced from the HTML with a `cid:` URL. The package serves them through a signed
route, so they show in the message view and in the editor of a reply or forward.

## The inline route

`GET {prefix}/inline/{message}/{cid}` (`mailbox.inline`) streams one inline part of a message.

- It runs with the `web` and `signed` middleware and `ShareMailboxTranslations`, not with the configured route
  middleware. A missing, changed or expired signature answers `403`.
- Only `image/gif`, `image/jpeg`, `image/png`, `image/svg+xml` and `image/webp` are served. Other types, unknown
  content ids and messages that no longer exist on the server answer `404`.
- The image comes from the raw message the core cached when the message was opened (core `cache.raw_hours`, default
  24 hours), so a message with many images costs one IMAP fetch instead of one per image. The signature check above runs
  first, also for a cached message.
- The response is `Content-Disposition: inline`, `Cache-Control: private` with a max age of one hour, and
  `X-Content-Type-Options: nosniff`.
- An SVG image also gets `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, so scripts
  inside it never run.

## InlineUrls

`J87NL\VueMailbox\Support\InlineUrls` makes and reads these URLs. The service provider binds it to the core contract
[`J87NL\Mailbox\Contracts\ResolvesInlineImageUrls`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/extension-points.md#resolvesinlineimageurls),
which replaces the core's default that knows no inline URLs.

| Method | Description |
|---|---|
| `for(MailboxMessage $message): Closure` | Returns a closure that turns a content id into a temporary signed URL, valid for `vue-mailbox.signed_url_minutes` (default 30) |
| `parse(string $url): ?array` | Returns `['content_id' => string, 'message_id' => int]` for a correctly signed inline URL, even if it has expired, otherwise `null` |
| `urlPrefix(): ?string` | The absolute URL prefix of the inline route, with a trailing slash, such as `https://example.com/mailbox/inline/` |

`parse()` checks the signature with `URL::hasCorrectSignature()`, which ignores the expiry. That is why quoted images
in an older reply or forward still embed after the URL has expired. It matches the URL against a copy of the inline
route, so it never changes the route of the current request.

## Message view

The message controller passes `for($message)` to the core HTML renderer, which rewrites `cid:` references to signed
URLs. `MessageFrame` allows images from `imageOrigin`, the scheme, host and port of the current request, so these URLs
load while remote images stay blocked.

## Replies and forwards

The reply and forward quotes also use signed inline URLs, so the images show in the editor. The core marks each image
with a `data-mailbox-cid` attribute. Your WYSIWYG editor must keep that attribute; the default HugeRTE options in the
core config do.

When the message is sent, the core uses `parse()` and `urlPrefix()` (see
[quoting and inline images](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sending.md#quoting-and-inline-images))
to:

- turn images of the quoted message back into embedded parts,
- drop quoted images that were removed or that belong to another message,
- strip signed inline URLs a user pasted into a new message.

Sent mail therefore never carries a signed inline URL.
