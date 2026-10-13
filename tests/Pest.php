<?php

declare(strict_types=1);

use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Tests\Support\User;
use J87NL\VueMailbox\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $attributes
 */
function mailbox(array $attributes = []): Mailbox
{
    return Mailbox::query()
        ->create([
            'active' => true,
            'email' => 'team@example.com',
            'imap_host' => 'imap.example.com',
            'imap_password' => 'imap-secret',
            'imap_username' => 'team@example.com',
            'name' => 'Team',
            'smtp_host' => 'smtp.example.com',
            'smtp_password' => 'smtp-secret',
            'smtp_username' => 'team@example.com',
            ...$attributes,
        ])->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function folder(Mailbox $mailbox, string $path = 'INBOX', FolderRole $role = FolderRole::Inbox, array $attributes = []): MailboxFolder
{
    return MailboxFolder::query()
        ->create([
            'mailbox_id' => $mailbox->id,
            'name' => $path,
            'path' => $path,
            'role' => $role,
            'selectable' => true,
            'synced_at' => now(),
            ...$attributes,
        ])->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function storedMessage(MailboxFolder $folder, int $uid, array $attributes = []): MailboxMessage
{
    return MailboxMessage::query()
        ->create([
            'body_fetched_at' => now(),
            'body_html' => '<p>Hello</p>',
            'from_email' => 'sender@example.org',
            'mailbox_folder_id' => $folder->id,
            'mailbox_id' => $folder->mailbox_id,
            'message_id' => sprintf('message-%d@example.org', $uid),
            'subject' => sprintf('Message %d', $uid),
            'uid' => $uid,
            ...$attributes,
        ])->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function user(array $attributes = []): User
{
    return User::query()
        ->create([
            'email' => sprintf('user-%s@example.com', uniqid()),
            'name' => 'Test User',
            ...$attributes,
        ]);
}

function member(Mailbox $mailbox, AccessLevel $level): User
{
    $user = user();

    MailboxAccess::query()
        ->create([
            'accessor_id' => $user->id,
            'accessor_type' => $user->getMorphClass(),
            'level' => $level,
            'mailbox_id' => $mailbox->id,
        ]);

    return $user;
}
