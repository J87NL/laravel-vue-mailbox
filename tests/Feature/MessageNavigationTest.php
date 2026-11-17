<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;

/**
 * @param  array<string, mixed>  $mailboxAttributes
 * @return array{0: Mailbox, 1: MailboxFolder, 2: array<string, MailboxMessage>}
 */
function navigableInbox(array $mailboxAttributes = []): array
{
    $mailbox = mailbox($mailboxAttributes);
    $inbox = folder($mailbox);

    return [
        $mailbox,
        $inbox,
        [
            'newest' => storedMessage($inbox, 1, [
                'sent_at' => now()->subHour(),
                'subject' => 'Invoice March',
            ]),
            'middle' => storedMessage($inbox, 2, [
                'sent_at' => now()->subDay(),
                'subject' => 'Lunch',
            ]),
            'oldest' => storedMessage($inbox, 3, [
                'sent_at' => now()->subDays(2),
                'subject' => 'Invoice February',
            ]),
            'undated' => storedMessage($inbox, 4, [
                'sent_at' => null,
                'subject' => 'No date',
            ]),
        ],
    ];
}

/**
 * @param  array<string, mixed>  $listQuery
 * @return array{next: ?string, previous: ?string}
 */
function neighbourUrls(Mailbox $mailbox, MailboxMessage $message, array $listQuery = []): array
{
    $urls = [];

    test()->get(route('mailbox.messages.show', [$mailbox, $message, ...$listQuery]))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$urls): AssertableInertia {
            $urls = [
                'next' => $page->toArray()['props']['nextUrl'],
                'previous' => $page->toArray()['props']['previousUrl'],
            ];

            return $page;
        });

    return $urls;
}

/**
 * @param  array<string, mixed>  $listQuery
 */
function messageUrl(Mailbox $mailbox, MailboxMessage $message, array $listQuery = []): string
{
    return route('mailbox.messages.show', [$mailbox, $message, ...$listQuery]);
}

beforeEach(function (): void {
    Bus::fake();
});

it('links the previous and next message in the order of the message list', function (): void {
    [$mailbox, , $messages] = navigableInbox();

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $messages['newest']))->toBe([
        'next' => messageUrl($mailbox, $messages['middle']),
        'previous' => null,
    ])
        ->and(neighbourUrls($mailbox, $messages['middle']))->toBe([
            'next' => messageUrl($mailbox, $messages['oldest']),
            'previous' => messageUrl($mailbox, $messages['newest']),
        ])
        ->and(neighbourUrls($mailbox, $messages['oldest']))->toBe([
            'next' => messageUrl($mailbox, $messages['undated']),
            'previous' => messageUrl($mailbox, $messages['middle']),
        ])
        ->and(neighbourUrls($mailbox, $messages['undated']))->toBe([
            'next' => null,
            'previous' => messageUrl($mailbox, $messages['oldest']),
        ]);
});

it('follows an ascending sort of the message list', function (): void {
    [$mailbox, , $messages] = navigableInbox();
    $listQuery = ['messages_sort' => 'sent_at'];

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $messages['undated'], $listQuery))->toBe([
        'next' => messageUrl($mailbox, $messages['oldest'], $listQuery),
        'previous' => null,
    ])
        ->and(neighbourUrls($mailbox, $messages['middle'], $listQuery))->toBe([
            'next' => messageUrl($mailbox, $messages['newest'], $listQuery),
            'previous' => messageUrl($mailbox, $messages['oldest'], $listQuery),
        ]);
});

it('only steps through the messages that match the search of the list', function (): void {
    [$mailbox, , $messages] = navigableInbox();
    $listQuery = ['messages_filter' => ['global' => 'invoice']];

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $messages['newest'], $listQuery))->toBe([
        'next' => messageUrl($mailbox, $messages['oldest'], $listQuery),
        'previous' => null,
    ])
        ->and(neighbourUrls($mailbox, $messages['oldest'], $listQuery))->toBe([
            'next' => null,
            'previous' => messageUrl($mailbox, $messages['newest'], $listQuery),
        ]);
});

it('orders messages with the same date by id', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $sentAt = now()->subHour();
    $first = storedMessage($inbox, 1, ['sent_at' => $sentAt]);
    $second = storedMessage($inbox, 2, ['sent_at' => $sentAt]);

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $second))->toBe([
        'next' => messageUrl($mailbox, $first),
        'previous' => null,
    ]);
});

it('steps from conversation to conversation in the conversation view', function (): void {
    [$mailbox, $inbox, $messages] = navigableInbox(['conversation_view' => true]);
    $earlierInThread = storedMessage($inbox, 5, [
        'sent_at' => now()->subDays(3),
        'thread_key' => 'thread@example.org',
    ]);
    $latestInThread = storedMessage($inbox, 6, [
        'sent_at' => now()->subMinutes(30),
        'thread_key' => 'thread@example.org',
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $earlierInThread))->toBe([
        'next' => messageUrl($mailbox, $messages['newest']),
        'previous' => null,
    ])
        ->and(neighbourUrls($mailbox, $messages['oldest']))->toBe([
            'next' => messageUrl($mailbox, $messages['undated']),
            'previous' => messageUrl($mailbox, $messages['middle']),
        ])
        ->and(neighbourUrls($mailbox, $messages['newest']))->toBe([
            'next' => messageUrl($mailbox, $messages['middle']),
            'previous' => messageUrl($mailbox, $latestInThread),
        ]);
});

it('stays within the folder of the message', function (): void {
    [$mailbox, , $messages] = navigableInbox();
    storedMessage(folder($mailbox, 'Other'), 1, ['sent_at' => now()]);

    $this->actingAs(member($mailbox, AccessLevel::Read));

    expect(neighbourUrls($mailbox, $messages['newest'])['previous'])->toBeNull();
});
