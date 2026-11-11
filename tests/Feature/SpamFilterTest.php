<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Enums\SpamAllowlistSource;
use J87NL\Mailbox\Enums\SpamFilterMode;
use J87NL\Mailbox\Enums\SpamStatus;
use J87NL\Mailbox\Models\MailboxSpamAllowlistEntry;
use J87NL\VueMailbox\Tables\MessagesTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

it('shows the spam badge with score and reason in the list and on the message page', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $suggested = storedMessage($inbox, 1, [
        'spam_reason' => 'Looks like a phishing mail',
        'spam_score' => 0.873,
        'spam_status' => SpamStatus::Suggested,
    ]);
    storedMessage($inbox, 2, [
        'spam_score' => 0.2,
        'spam_status' => SpamStatus::Released,
    ]);
    $moved = storedMessage(folder($mailbox, 'Spam', FolderRole::Junk), 3, [
        'spam_score' => 0.97,
        'spam_status' => SpamStatus::Moved,
    ]);
    $reader = member($mailbox, AccessLevel::Read);
    $this->actingAs($reader);

    $subjects = collect(MessagesTable::build($inbox)->toArray()['data'])
        ->mapWithKeys(fn (array $row): array => [$row['uid'] => (string) $row['subject']])
        ->all();

    expect($subjects[1])->toContain('Possibly spam')
        ->toContain('title="Spam score 0.87: Looks like a phishing mail"')
        ->and($subjects[2])->toBe('Message 2');

    $this->get(route('mailbox.messages.show', [$mailbox, $suggested]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('spam', [
                'detail' => 'Spam score 0.87: Looks like a phishing mail',
                'label' => 'Possibly spam',
                'status' => 'suggested',
            ]));

    $this->get(route('mailbox.messages.show', [$mailbox, $moved]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('spam.label', 'Moved to spam by the filter')
            ->where('spam.detail', 'Spam score 0.97'));
});

it('shows no spam badge on a released message', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'spam_score' => 0.9,
        'spam_status' => SpamStatus::Released,
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('spam', null));
});

it('releases a suggested inbox message with not spam and allowlists its sender for the user', function (): void {
    $mailbox = mailbox(['spam_filter_mode' => SpamFilterMode::Suggest]);
    $inbox = folder($mailbox);
    $suggested = storedMessage($inbox, 1, [
        'from_email' => 'Friend@Example.org',
        'spam_status' => SpamStatus::Suggested,
    ]);
    $plain = storedMessage($inbox, 2);
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->get(route('mailbox.messages.show', [$mailbox, $suggested]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('spamSuggested', true));

    $this->actingAs($writer)
        ->get(route('mailbox.messages.show', [$mailbox, $plain]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('spamSuggested', false));

    $this->actingAs($writer)
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'not_junk',
            'ids' => [$plain->id],
        ])
        ->assertStatus(422);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'not_junk',
            'ids' => [$suggested->id],
        ])
        ->assertForbidden();

    $this->actingAs($writer)
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'not_junk',
            'ids' => [$suggested->id],
        ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $inbox]));

    expect($suggested->refresh()->spam_status)->toBe(SpamStatus::Released)
        ->and($suggested->mailbox_folder_id)->toBe($inbox->id)
        ->and(MailboxSpamAllowlistEntry::query()
            ->get(['mailbox_id', 'pattern', 'source'])
            ->map(fn (MailboxSpamAllowlistEntry $entry): array => [$entry->mailbox_id, $entry->pattern, $entry->source])
            ->all())->toBe([
                [
                    $mailbox->id,
                    'friend@example.org',
                    SpamAllowlistSource::NotJunk,
                ],
            ]);
});

it('offers no not spam for a suggested message once the spam filter is off', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, ['spam_status' => SpamStatus::Suggested]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'not_junk',
            'ids' => [$message->id],
        ])
        ->assertStatus(422);
});

it('only releases the suggested messages of a not spam bulk action', function (): void {
    $mailbox = mailbox(['spam_filter_mode' => SpamFilterMode::Suggest]);
    $inbox = folder($mailbox);
    $suggested = storedMessage($inbox, 1, [
        'from_email' => 'friend@example.org',
        'spam_status' => SpamStatus::Suggested,
    ]);
    $plain = storedMessage($inbox, 2, ['from_email' => 'stranger@example.org']);
    $this->actingAs(member($mailbox, AccessLevel::Write));

    $bulkActions = collect(MessagesTable::build($inbox)->toArray()['bulkActions'] ?? [])->pluck('label')->all();
    $url = SignedTableUrl::make('table.bulk-action', MessagesTable::class, ['folder' => $inbox->id, 'grouped' => true], [
        'action' => base64_encode((string) array_search('Not spam', $bulkActions, true)),
    ]);

    $this->postJson($url, ['ids' => [$suggested->id, $plain->id]])
        ->assertOk();

    expect($suggested->refresh()->spam_status)->toBe(SpamStatus::Released)
        ->and($plain->refresh()->spam_status)->toBeNull()
        ->and(MailboxSpamAllowlistEntry::query()
            ->pluck('pattern')
            ->all())->toBe(['friend@example.org']);
});

it('filters the message list on the messages the spam filter suggested', function (): void {
    $mailbox = mailbox(['spam_filter_mode' => SpamFilterMode::Suggest]);
    $inbox = folder($mailbox);
    $suggested = storedMessage($inbox, 1, ['spam_status' => SpamStatus::Suggested]);
    storedMessage($inbox, 2, ['spam_status' => SpamStatus::Released]);
    storedMessage($inbox, 3);
    $reader = member($mailbox, AccessLevel::Read);

    $this->actingAs($reader)
        ->get(route('mailbox.folders.show', [
            $mailbox,
            $inbox,
            'messages_filter' => [
                'spam_status' => 'suggested',
            ],
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('table.data', 1)
            ->where('table.data.0.id', $suggested->id));

    $off = mailbox(['name' => 'Off']);
    $this->actingAs(member($off, AccessLevel::Read));

    expect(MessagesTable::build($inbox)->toArray()['filters']->pluck('key')->all())->toBe(['spam_status'])
        ->and(MessagesTable::build(folder($off))->toArray()['filters']->all())->toBe([]);
});
