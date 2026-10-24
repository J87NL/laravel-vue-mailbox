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

it('lists every matching message instead of conversations while searching', function (): void {
    [$inbox, $messages] = conversationFolder(true);
    $messages['root']->update([
        'subject' => 'Invoice 42',
    ]);
    $mailbox = $inbox->mailbox;

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [
            $mailbox,
            $inbox,
            'messages_filter' => [
                'global' => 'Invoice',
            ],
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('table.data', 1)
            ->where('table.data.0.id', $messages['root']->id));
});

it('lists a window of the conversation that always holds the open message', function (): void {
    $mailbox = mailbox([
        'conversation_view' => true,
    ]);
    $inbox = folder($mailbox);
    $messages = collect(range(1, 60))
        ->map(fn (int $uid): MailboxMessage => storedMessage($inbox, $uid, [
            'sent_at' => now()->subHours(100 - $uid),
            'thread_key' => 'long@example.org',
        ]));
    $other = folder(mailbox([
        'name' => 'Other',
    ]));
    storedMessage($other, 1, [
        'sent_at' => now()->subHours(150),
        'thread_key' => 'long@example.org',
    ]);
    $reader = member($mailbox, AccessLevel::Read);

    foreach ([0, 30, 59] as $index) {
        $open = $messages[$index];

        $this->actingAs($reader)
            ->get(route('mailbox.messages.show', [$mailbox, $open]))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->has('conversation', 50)
                ->where('conversation', fn ($entries): bool => collect($entries)->where('current', true)->pluck('id')->all() === [$open->id]
                    && collect($entries)->every(fn (array $entry): bool => str_contains($entry['url'], sprintf('/mailbox/%d/', $mailbox->id)))));
    }
});

it('runs a bulk action from search results on the selected message only', function (): void {
    Bus::fake();
    [$inbox, $messages] = conversationFolder(true);
    $messages['root']->update([
        'subject' => 'Invoice 42',
    ]);
    $mailbox = $inbox->mailbox;
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Invoice 42\r\n\r\nBody"),
        new FakeMessage(2, [], "Subject: Re: Question\r\n\r\nBody"),
    ]);
    $writer = member($mailbox, AccessLevel::Write);

    $deleteUrl = $this->actingAs($writer)
        ->get(route('mailbox.folders.show', [
            $mailbox,
            $inbox,
            'messages_filter' => [
                'global' => 'Invoice',
            ],
        ]))
        ->viewData('page')['props']['table']['bulkActions'][1]['url'] ?? null;

    expect($deleteUrl)->toBeString();

    $this->actingAs($writer)
        ->postJson($deleteUrl, [
            'ids' => [$messages['root']->id],
        ])
        ->assertOk();

    expect(MailboxMessage::query()
        ->whereKey([$messages['root']->id, $messages['follow_up']->id])
        ->pluck('id')
        ->all())->toBe([$messages['follow_up']->id]);
});

it('finds the open message in a very long conversation', function (): void {
    $mailbox = mailbox([
        'conversation_view' => true,
    ]);
    $inbox = folder($mailbox);
    MailboxMessage::query()->insert(collect(range(1, 1100))
        ->map(fn (int $uid): array => [
            'body_fetched_at' => now(),
            'body_html' => '<p>Hello</p>',
            'created_at' => now(),
            'mailbox_folder_id' => $inbox->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => sprintf('long-%d@example.org', $uid),
            'sent_at' => now()->subMinutes(2000 - $uid),
            'subject' => 'Long',
            'thread_key' => 'long@example.org',
            'uid' => $uid,
            'updated_at' => now(),
        ])
        ->all());
    $open = MailboxMessage::query()
        ->where('uid', 1050)
        ->firstOrFail();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $open]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('conversation', 50)
            ->where('conversation.25.id', $open->id)
            ->where('conversation.25.current', true)
            ->where('conversation.0.id', $open->id - 25)
            ->where('conversation.49.id', $open->id + 24));
});
