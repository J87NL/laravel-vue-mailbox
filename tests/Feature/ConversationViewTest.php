<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Tables\MessagesTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

/**
 * @return array{0: MailboxFolder, 1: array<string, MailboxMessage>}
 */
function conversationFolder(bool $conversationView): array
{
    $mailbox = mailbox([
        'conversation_view' => $conversationView,
    ]);
    $inbox = folder($mailbox);
    $sent = folder($mailbox, 'Sent', FolderRole::Sent);

    $messages = [
        'root' => storedMessage($inbox, 1, [
            'is_seen' => false,
            'sent_at' => now()->subDays(3),
            'subject' => 'Question',
            'thread_key' => 'root@example.org',
        ]),
        'answer' => storedMessage($sent, 1, [
            'is_seen' => true,
            'sent_at' => now()->subDays(2),
            'subject' => 'Re: Question',
            'thread_key' => 'root@example.org',
        ]),
        'follow_up' => storedMessage($inbox, 2, [
            'is_seen' => true,
            'sent_at' => now()->subDay(),
            'subject' => 'Re: Question',
            'thread_key' => 'root@example.org',
        ]),
        'other' => storedMessage($inbox, 3, [
            'is_seen' => true,
            'sent_at' => now()->subDays(5),
            'subject' => 'Other',
            'thread_key' => 'other@example.org',
        ]),
        'unthreaded' => storedMessage($inbox, 4, [
            'is_seen' => true,
            'sent_at' => now()->subDays(6),
            'subject' => 'Old',
            'thread_key' => null,
        ]),
    ];

    return [
        $inbox,
        $messages,
    ];
}

/**
 * @return array<int, array{class: ?string, subject: string}>
 */
function tableRows(MailboxFolder $folder): array
{
    return collect(MessagesTable::build($folder->load('mailbox'))->toArray()['data'])
        ->mapWithKeys(fn (array $row): array => [
            $row['id'] => [
                'class' => $row['__class'] ?? null,
                'subject' => (string) $row['subject'],
            ],
        ])
        ->all();
}

it('shows one row per conversation with the latest message, its size and its unread state', function (): void {
    [$inbox, $messages] = conversationFolder(true);
    $this->actingAs(user());

    expect(tableRows($inbox))->toBe([
        $messages['follow_up']->id => [
            'class' => 'font-semibold',
            'subject' => 'Re: Question (2)',
        ],
        $messages['other']->id => [
            'class' => null,
            'subject' => 'Other',
        ],
        $messages['unthreaded']->id => [
            'class' => null,
            'subject' => 'Old',
        ],
    ]);
});

it('lists every message when the conversation view is off', function (): void {
    [$inbox, $messages] = conversationFolder(false);
    $this->actingAs(user());

    expect(array_keys(tableRows($inbox)))->toBe([
        $messages['follow_up']->id,
        $messages['root']->id,
        $messages['other']->id,
        $messages['unthreaded']->id,
    ]);
});

it('applies a bulk action to the whole conversation in the folder', function (): void {
    Bus::fake();
    [$inbox, $messages] = conversationFolder(true);
    $this->connector->remote($inbox->mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Question\r\n\r\nBody"),
        new FakeMessage(2, [], "Subject: Re: Question\r\n\r\nBody"),
    ]);

    $url = SignedTableUrl::make('table.bulk-action', MessagesTable::class, ['folder' => $inbox->id], [
        'action' => base64_encode('1'),
    ]);

    $this->actingAs(member($inbox->mailbox, AccessLevel::Write))
        ->postJson($url, [
            'ids' => [$messages['follow_up']->id],
        ])
        ->assertOk();

    expect(MailboxMessage::query()
        ->orderBy('id')
        ->pluck('id')
        ->all())->toBe([
            $messages['answer']->id,
            $messages['other']->id,
            $messages['unthreaded']->id,
        ]);
});

it('lists the messages of the conversation on the message page', function (): void {
    [$inbox, $messages] = conversationFolder(true);
    $mailbox = $inbox->mailbox;

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $messages['follow_up']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('conversation', 3)
            ->where('conversation.0.id', $messages['root']->id)
            ->where('conversation.0.current', false)
            ->where('conversation.1.folder', 'Sent')
            ->where('conversation.2.current', true)
            ->where('conversation.0.url', route('mailbox.messages.show', [$mailbox, $messages['root']])));

    $this->get(route('mailbox.messages.show', [$mailbox, $messages['other']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('conversation', []));
});

it('lists no conversation on the message page when the view is off', function (): void {
    [$inbox, $messages] = conversationFolder(false);

    $this->actingAs(member($inbox->mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$inbox->mailbox, $messages['follow_up']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('conversation', []));
});
