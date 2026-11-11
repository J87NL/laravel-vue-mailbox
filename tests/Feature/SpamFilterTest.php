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

it('lets only a manager of the mailbox add and remove trusted senders', function (): void {
    $mailbox = mailbox();
    $other = mailbox(['name' => 'Other']);
    $foreign = MailboxSpamAllowlistEntry::query()
        ->create([
            'mailbox_id' => $other->id,
            'pattern' => 'foreign@example.org',
            'source' => SpamAllowlistSource::Manual,
        ]);
    $global = MailboxSpamAllowlistEntry::query()
        ->create([
            'mailbox_id' => null,
            'pattern' => 'partner.example',
            'source' => SpamAllowlistSource::Default,
        ]);
    $writer = member($mailbox, AccessLevel::Write);
    $manager = member($mailbox, AccessLevel::Manage);

    $this->actingAs($writer)
        ->post(route('mailbox.settings.spam-allowlist.store', $mailbox), ['pattern' => 'boss@example.org'])
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('mailbox.settings.spam-allowlist.store', $mailbox), ['pattern' => '@'])
        ->assertSessionHasErrors('pattern');

    $this->actingAs($manager)
        ->post(route('mailbox.settings.spam-allowlist.store', $mailbox), ['pattern' => ' @Example.ORG '])
        ->assertSessionHasNoErrors();

    $entry = MailboxSpamAllowlistEntry::query()
        ->where('mailbox_id', $mailbox->id)
        ->sole();

    expect($entry->pattern)->toBe('example.org')
        ->and($entry->source)->toBe(SpamAllowlistSource::Manual);

    $this->actingAs($writer)
        ->delete(route('mailbox.settings.spam-allowlist.destroy', [$mailbox, $entry->id]))
        ->assertForbidden();

    foreach ([
        $foreign,
        $global,
    ] as $outOfReach) {
        $this->actingAs($manager)
            ->delete(route('mailbox.settings.spam-allowlist.destroy', [$mailbox, $outOfReach->id]))
            ->assertNotFound();
    }

    $this->actingAs($manager)
        ->delete(route('mailbox.settings.spam-allowlist.destroy', [$mailbox, $entry->id]))
        ->assertRedirect();

    expect(MailboxSpamAllowlistEntry::query()
        ->orderBy('id')
        ->pluck('pattern')
        ->all())->toBe([
            'foreign@example.org',
            'partner.example',
        ]);
});

it('lists the trusted senders of the mailbox and the global ones read-only on the settings page', function (): void {
    $mailbox = mailbox();
    $own = MailboxSpamAllowlistEntry::query()
        ->create([
            'mailbox_id' => $mailbox->id,
            'pattern' => 'boss@example.org',
            'source' => SpamAllowlistSource::NotJunk,
        ]);
    MailboxSpamAllowlistEntry::query()
        ->create([
            'mailbox_id' => null,
            'pattern' => 'partner.example',
            'source' => SpamAllowlistSource::Default,
        ]);
    MailboxSpamAllowlistEntry::query()
        ->create([
            'mailbox_id' => mailbox(['name' => 'Other'])->id,
            'pattern' => 'foreign@example.org',
            'source' => SpamAllowlistSource::Manual,
        ]);

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.fields', fn (mixed $fields): bool => str_contains((string) json_encode($fields), '"name":"spam_filter_mode"'))
            ->where('spamAllowlistForm.action', route('mailbox.settings.spam-allowlist.store', $mailbox))
            ->has('spamAllowlistTable.data', 2)
            ->where('spamAllowlistTable.data.0.pattern', 'boss@example.org')
            ->where('spamAllowlistTable.rowActions.0.0.url', route('mailbox.settings.spam-allowlist.destroy', [$mailbox, $own->id]))
            ->where('spamAllowlistTable.data.1.pattern', 'partner.example')
            ->where('spamAllowlistTable.data.1.mailbox_id', 'Every mailbox')
            ->where('spamAllowlistTable.rowActions.1', []));
});
