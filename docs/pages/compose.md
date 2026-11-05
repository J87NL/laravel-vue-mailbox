# Compose, reply and forward

Route `mailbox.compose.create` (`GET {mailbox}/compose`) renders the `vue-mailbox.pages.compose` page, default
`mailbox/Compose`. The form posts to `mailbox.compose.store`. Both need `workInMailbox`.

## Modes

| URL | Mode |
|---|---|
| `compose` | `new` |
| `compose?reply={id}` | `reply` |
| `compose?reply={id}&mode=reply` | `reply` |
| `compose?reply={id}&mode=reply_all` | `reply_all` |
| `compose?reply={id}&mode=forward` | `forward` |

With a source message, `?mode=` must be `reply`, `reply_all` or `forward`; any other value, or a value that is not a string, gives a 404. Only
`reply_all` adds the Cc addresses. The default page uses the mode as a translation key for its title.

`reply` must be the id of a message in the same mailbox. An unknown id opens a new message. When the source message no
longer exists on the server, the user is sent back to its folder with an `error` flash message.

## Props

| Prop | Type | Description |
|---|---|---|
| `backUrl` | `string` | The source message with the list query, or the mailbox's inbox for a new message |
| `folders` | `MailboxFolder[]` | Folder list, see [Folders and message list](folders.md#folder-list) |
| `form` | `FormSchema` | The core `ComposeForm`, prefilled for the mode |
| `mailbox` | `object` | `email`, `id`, `name` of the mailbox |
| `mode` | `string` | `new` without a source message, otherwise `reply`, `reply_all` or `forward` (default `reply`) |
| `templates` | `MailboxTemplate[]` | The mailbox's templates by name: `id`, `name` and `url` (the render route, with `reply` when there is a source message) |

## Templates

With templates, the page shows a "Sjabloon kiezen" select next to Cancel. Choosing one calls its `url`
(`GET {mailbox}/templates/{template}/render`, `mailbox.templates.render`, `workInMailbox`) with the first `to` address
and the current subject as `to` and `subject`, and the core `RenderTemplate` fills the placeholders. The answer is JSON:
`{"subject": string|null, "body": string}`.

- The body goes above what is in the editor. An empty first paragraph (the line above the signature) is replaced.
- The subject is only filled when the subject field is still empty.
- `{voornaam}` and `{naam}` take the name of the original sender when replying to that address, otherwise the name in
  the address book for `to`. Without a name they stay in the text, so the user sees what is left to fill in.
- When the request fails, the page shows "Het sjabloon kon niet worden ingevoegd" (`template_insert_failed`).

Templates are managed in the mailbox settings, see [Message templates](settings.md#message-templates).

## The form

The form is the core [`ComposeForm`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/forms.md#composeform); its
fields and rules are documented there. This package fills it in:

- the address chips (`to`, `cc`, `bcc`) ask `mailbox.addresses` for suggestions;
- `body` uses the WYSIWYG editor set in the core config;
- when forwarding, `attachments` also lists the original attachments, each removable;
- the hidden `reply_to_message` and `forward_message` fields carry the id of the source message.

## Prefilled values

The prefill is built by `J87NL\VueMailbox\Support\ComposeDefaults`.

### Body and signature

Every body starts with an empty paragraph. When the mailbox has a signature, it follows in a
`<div class="mailbox-signature">`. A reply or forward quote comes after the signature.

### Reply

- **To**: when the source was sent by the mailbox itself, the original `to` addresses without the mailbox address.
  Otherwise the source's `Reply-To` addresses, or the sender when there is no `Reply-To`.
- **Cc** (reply all only): the original `to` and `cc` addresses, without the mailbox address, the sender and the
  addresses already in `to`.
- **Subject**: `Re: ` plus the original subject, unless it already starts with `re:` (any case).
- **Body**: "On {date}, {name} wrote:" above the original HTML in a bordered `<blockquote>`.

The mailbox address and the Cc list are compared case-insensitively, and duplicates are removed from them. `Reply-To`
addresses are used as they are, without removing duplicates.

### Forward

- **Subject**: `Fwd: ` plus the original subject, unless it already starts with `fw:` or `fwd:` (any case).
- **Body**: a `<div class="mailbox-quote">` with a "Forwarded message" header block (From, Date, Subject, To and Cc;
  empty lines are left out) and the original HTML.
- **Attachments**: the original attachments that are not inline images, each with a download link and removable.
  When the attachment metadata of the source was pruned, it is fetched again first (from the core raw cache, or from the server).

Dates in the quote use the format `d-m-Y H:i` in the
[display timezone](https://github.com/J87NL/laravel-mailbox/blob/main/docs/configuration.md#display-timezone) of the
core package. The texts come from the core translation file (`quote_wrote`, `quote_forwarded`, `from`, `date`, ...).

Inline images of the original message show in the editor through signed URLs and are sent as embedded parts. See
[Inline images](../inline-images.md).

## Sending

`POST {mailbox}/compose` validates the `ComposeForm` and sends the message through the core
[`SendMessage`](https://github.com/J87NL/laravel-mailbox/blob/main/docs/sending.md) action, with the uploaded files, the
kept forwarded attachments and the reply source. What happens on the SMTP and IMAP side (headers, the copy in Sent, the
audit entry) is documented there.

- A removal key that is not an attachment of the forwarded message fails validation, and nothing is sent.
- When a kept forwarded attachment can no longer be fetched from the server, the form fails with an error on
  `attachments`.
- When the mail server refuses the message (an SMTP error such as `550 5.7.1` for suspected spam), nothing is sent and
  the form fails with an error on `body` that quotes the server's answer (`vue-mailbox::mailbox.send_failed`). The
  composed message stays in the form. Before, the user got a server error page.
- After a reply or forward the user lands on the folder of the original message. After a new message the user lands
  on the inbox. Both carry a `success` flash message. The form posts to `mailbox.compose.store` with the list query of
  the message list, so the redirect keeps its search, filters, sort and page. See
  [Back to the list](messages.md#back-to-the-list).

## Address suggestions

`GET {mailbox}/addresses?q=` (`mailbox.addresses`, `workInMailbox`) returns the recipient suggestions the address chips
use, as JSON:

```json
[
    { "value": "anna@example.org", "label": "Anna <anna@example.org>" }
]
```

The query is cut to 100 characters. The suggestions come from the core `SuggestAddresses` action, at most 10. Its
sources are documented in the core
[recipient suggestions](https://github.com/J87NL/laravel-mailbox/blob/main/docs/contacts.md#recipient-suggestions) docs.
