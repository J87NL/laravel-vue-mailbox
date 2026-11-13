<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Testing\RecordingFolder;

/**
 * @param  array<string, mixed>  $attributes
 */
function threaded(MailboxFolder $folder, int $uid, array $attributes = []): MailboxMessage
{
    return storedMessage($folder, $uid, [
        'message_id' => sprintf('%s-%d@example.org', strtolower($folder->path), $uid),
        'thread_key' => 'root@example.org',
        ...$attributes,
    ]);
}

/**
 * @return array<string, array<int, array<string, mixed>>>
 */
function remoteOperations(Mailbox $mailbox): array
{
    return collect([
        'INBOX',
        'Sent',
        'Archive',
        'Trash',
    ])
        ->mapWithKeys(fn (string $path): array => [
            $path => test()->connector->remote($mailbox)->folder($path)->operations,
        ])
        ->all();
}

function remoteFolders(Mailbox $mailbox): void
{
    foreach ([
        'Sent',
        'Archive',
        'Trash',
    ] as $path) {
        test()->connector->remote($mailbox)->addFolder(new RecordingFolder($path));
    }
}

beforeEach(function (): void {
    Bus::fake();
});

it('moves every message of the conversation outside the trash to the trash', function (): void {
    $mailbox = mailbox(['conversation_view' => true]);
    remoteFolders($mailbox);
    $inbox = folder($mailbox);
    $sent = folder($mailbox, 'Sent', FolderRole::Sent);
    $archive = folder($mailbox, 'Archive', FolderRole::Archive);
    $trash = folder($mailbox, 'Trash', FolderRole::Trash);
    $opened = threaded($inbox, 1);
    threaded($sent, 1);
    threaded($archive, 1);
    $trashed = threaded($trash, 5);
    $unrelated = storedMessage($inbox, 2, ['thread_key' => 'other@example.org']);
    $elsewhere = threaded(folder(mailbox(['conversation_view' => true]), 'INBOX'), 1);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $inbox]));

    expect(remoteOperations($mailbox))->toBe([
        'INBOX' => [
            [
                'target' => 'Trash',
                'type' => 'move',
                'uids' => [1],
            ],
        ],
        'Sent' => [
            [
                'target' => 'Trash',
                'type' => 'move',
                'uids' => [1],
            ],
        ],
        'Archive' => [
            [
                'target' => 'Trash',
                'type' => 'move',
                'uids' => [1],
            ],
        ],
        'Trash' => [],
    ])
        ->and($this->connector->expunged)->toBe([])
        ->and(MailboxMessage::query()
            ->orderBy('id')
            ->pluck('id')
            ->all())->toBe([
                $trashed->id,
                $unrelated->id,
                $elsewhere->id,
            ]);
});

it('permanently deletes only the messages of the conversation in the trash when the opened message is in the trash', function (): void {
    $mailbox = mailbox(['conversation_view' => true]);
    remoteFolders($mailbox);
    $inbox = folder($mailbox);
    $trash = folder($mailbox, 'Trash', FolderRole::Trash);
    $kept = threaded($inbox, 1);
    $opened = threaded($trash, 5);
    threaded($trash, 6);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $trash]));

    expect(remoteOperations($mailbox)['INBOX'])->toBe([])
        ->and(collect(remoteOperations($mailbox)['Trash'])->pluck('uids')->all())->toBe([
            [
                5,
                6,
            ],
        ])
        ->and(MailboxMessage::query()
            ->pluck('id')
            ->all())->toBe([$kept->id]);
});

it('deletes the whole conversation beyond the messages the conversation view lists', function (): void {
    $mailbox = mailbox(['conversation_view' => true]);
    $inbox = folder($mailbox);
    folder($mailbox, 'Trash', FolderRole::Trash);
    $opened = threaded($inbox, 1, ['sent_at' => now()]);

    foreach (range(2, 61) as $uid) {
        threaded($inbox, $uid, ['sent_at' => now()->subMinutes($uid)]);
    }

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertRedirect();

    expect(MailboxMessage::query()
        ->count())->toBe(0);
});

it('leaves the All Mail copy of a conversation message cached in another folder alone', function (): void {
    $mailbox = mailbox(['conversation_view' => true]);
    $inbox = folder($mailbox);
    folder($mailbox, 'Trash', FolderRole::Trash);
    $allMail = folder($mailbox, '[Gmail]/All Mail', FolderRole::Archive, ['flags' => ['\\All']]);
    $opened = threaded($inbox, 1);
    $copy = storedMessage($allMail, 1, [
        'message_id' => $opened->message_id,
        'thread_key' => 'root@example.org',
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertRedirect();

    expect(MailboxMessage::query()
        ->pluck('id')
        ->all())->toBe([$copy->id]);
});

it('deletes only the opened message when the mailbox does not group conversations', function (): void {
    $mailbox = mailbox(['conversation_view' => false]);
    $inbox = folder($mailbox);
    folder($mailbox, 'Trash', FolderRole::Trash);
    $opened = threaded($inbox, 1);
    $sibling = threaded($inbox, 2);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertRedirect();

    expect(MailboxMessage::query()
        ->pluck('id')
        ->all())->toBe([$sibling->id]);
});

it('refuses a conversation delete for anything but one message of the mailbox', function (array $ids): void {
    $mailbox = mailbox(['conversation_view' => true]);
    $inbox = folder($mailbox);
    $messages = [
        'first' => threaded($inbox, 1),
        'second' => threaded($inbox, 2),
        'foreign' => threaded(folder(mailbox(), 'INBOX'), 1),
    ];

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => array_map(fn (string $key): int => $messages[$key]->id, $ids),
        ])
        ->assertStatus(422);

    expect(MailboxMessage::query()
        ->count())->toBe(3);
})->with([
    'two messages' => [[
        'first',
        'second',
    ]],
    'a message of another mailbox' => [['foreign']],
    'a message and one of another mailbox' => [[
        'first',
        'foreign',
    ]],
    'the same message twice' => [[
        'first',
        'first',
    ]],
]);

it('refuses a conversation delete without write access', function (): void {
    $mailbox = mailbox(['conversation_view' => true]);
    $inbox = folder($mailbox);
    $opened = threaded($inbox, 1);
    threaded($inbox, 2);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'delete_conversation',
            'ids' => [$opened->id],
        ])
        ->assertForbidden();

    expect(MailboxMessage::query()
        ->count())->toBe(2);
});
