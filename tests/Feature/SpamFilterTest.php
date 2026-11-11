<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Enums\SpamStatus;
use J87NL\VueMailbox\Tables\MessagesTable;

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
