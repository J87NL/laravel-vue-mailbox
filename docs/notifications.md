# Browser notifications

While a page of the app is open, the package can show a browser notification for every new unread message in the
inboxes the user turned notifications on for. It uses the browser's `Notification` API from the open tab; there is no service worker and
no web push, so nothing arrives while every tab of the app is closed.

## Turning them on

Notifications are switched per mailbox. The folder page and the message view show a toggle button next to the other
toolbar buttons (`@mailbox/components/NotificationToggle.vue` with the `mailbox-id` of the open mailbox), which
switches notifications for that mailbox only:

| State | Button | Meaning |
|---|---|---|
| Off | "Meldingen aanzetten voor deze mailbox" / "Turn on notifications for this mailbox" | The default. Clicking asks the browser for permission (`Notification.requestPermission()`) when it was not given yet, and turns them on for this mailbox when granted |
| On | "Meldingen uitzetten voor deze mailbox" / "Turn off notifications for this mailbox" | Clicking turns them off for this mailbox again, the browser permission stays |
| Blocked | "Meldingen geblokkeerd door browser", disabled | The user denied the permission; only the browser's site settings can undo that. The `title` explains it |

The page never asks for permission on load. The choice is remembered per browser and per mailbox in `localStorage`
under `vue-mailbox.notifications`, as a JSON map of mailbox ids, such as `{"3":true,"5":true}`. The value `on` of 0.8.0
(or of a toggle without a mailbox, see below) means every mailbox the user may read: the first poll turns it into the
map of those mailboxes, so existing users keep their notifications. A mailbox switched off before that first poll is
stored as an exception (`{"*":true,"3":false}`). A mailbox the user gets access to later starts with notifications
off. When storage is not available (private mode, blocked site data) the toggle
still works for the open page. The button is hidden when the browser has no `Notification` API or when the shared prop
below is `null`.

## What gets a notification

Every `vue-mailbox.poll_seconds` seconds (default 30, at least 10) the open tab asks `mailbox.notifications` for the
messages that arrived since its previous poll, with the ids of the mailboxes that have notifications on. When no
mailbox has them on, the tab does not poll at all.

- only messages in the INBOX of an active mailbox that has notifications on and that the user passes `accessMailbox`
  for;
- only unread messages, sent in the last 24 hours (`MailboxNotifications::MAX_AGE_HOURS`) or without a date, so a
  mailbox that imports its inbox for the first time or after a changed `UIDVALIDITY` does not announce old mail (mail with a sending date older than 24 hours that arrives late gets no notification
  either);
- not messages sent from one of those mailbox addresses or from the user's own e-mail address;
- not the messages that were already there when notifications started: the first poll only fetches the cursor.

A new message gets its own notification: the sender as title, the subject and mailbox name as body. When more than
three arrive in one poll, one notification says "N nieuwe berichten" and lists the mailboxes. Clicking a notification
focuses the tab and opens the message (or, for the summary, the inbox of the newest message).

A message in the folder the user has open is skipped while the tab is visible and has focus: the table already shows
it. On any other page, in another folder, or with the tab in the background, the message gets a notification.

The poll keeps running while the user moves between mailbox pages. It stops when no page with the toggle (or another
caller of `useMailboxNotifications()`) is open. After a full reload, or after turning notifications off and on, the
first poll only fetches the cursor again: mail that arrived in between gets no notification.

The cursor is best effort. Two syncs that commit out of id order can, rarely, make a message skip its notification;
it still shows in the message list.

## Endpoint

`GET {prefix}/notifications?after=<cursor>&mailboxes[]=<id>` (`mailbox.notifications`) needs `accessAnyMailbox` and is throttled to 60
requests per minute per user (`VueMailboxServiceProvider::NOTIFICATIONS_LIMITER`, `vue-mailbox-notifications`). It
answers JSON:

```json
{
    "count": 1,
    "cursor": 4812,
    "mailboxes": [
        3
    ],
    "messages": [
        {
            "folder_id": 12,
            "folder_url": "https://example.com/mailbox/3/folders/12",
            "from_email": "customer@example.org",
            "from_name": "Customer",
            "id": 4812,
            "mailbox_id": 3,
            "mailbox_name": "Support",
            "sent_at": "2026-10-26T09:12:00+00:00",
            "subject": "Question",
            "url": "https://example.com/mailbox/3/messages/4812"
        }
    ]
}
```

`mailboxes[]` (optional) limits the answer to those mailbox ids. Ids the user may not read, or that do
not exist, are ignored without an error; an id that is not a whole number answers `422`. Without `mailboxes[]` every
mailbox the user may read counts. `mailboxes` in the answer lists the ids that were used: the requested ones the user
may read, or all of them.

`cursor` is the highest message id at the time of the request; the next poll sends it as `after`. Without `after` the
answer holds only the cursor. `count` is the number of matching messages, `messages` the newest ten of them
(`MailboxNotifications::LIMIT`), newest first. Each message is checked against the mailboxes the user may read, so a
message of another mailbox never appears.

## Shared prop

The mailbox routes (through `ShareMailboxTranslations`) and host pages with the `ShareMailboxNavigation` middleware
share `vueMailboxNotifications`: `url`, `poll_seconds` and the translated `labels` of the toggle and the notifications.
It is `null` for a guest and for a user without `accessAnyMailbox`, which hides the toggle.

## In your own layout

The default pages already include the toggle, so hosts that use them need nothing. To keep notifications running on
other pages of the app, put the toggle (or the composable) in the layout of those pages and add the
`ShareMailboxNavigation` middleware to their routes:

```vue
<script setup lang="ts">
import NotificationToggle from '@mailbox/components/NotificationToggle.vue'
</script>

<template>
    <NotificationToggle />
</template>
```

Without `mailbox-id` the toggle switches all mailboxes at once ("Meldingen aanzetten" / "Meldingen uitzetten"): on
means every mailbox the user may read, off turns every mailbox off. Pass `:mailbox-id` to switch one mailbox.

`useMailboxNotifications({ folderId, mailboxId })` from `@mailbox/notifications` returns `state` (`unavailable`, `blocked`, `on`,
`off`), `label`, `hint` and `toggle()` for a button of your own. `folderId` and `mailboxId` are functions that return the
id of the folder and of the mailbox on screen, or `null`. Several callers on one page share one poll.
