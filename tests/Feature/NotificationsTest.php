<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Http\Middleware\ShareMailboxNavigation;
use J87NL\VueMailbox\Support\MailboxNotifications;

it('answers the current cursor without messages on the first poll', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->getJson(route('mailbox.notifications'))
        ->assertOk()
        ->assertExactJson([
            'count' => 0,
            'cursor' => $message->id,
            'mailboxes' => [
                $mailbox->id,
            ],
            'messages' => [],
        ]);
});

it('returns only new unread inbox messages of mailboxes the user may read', function (): void {
    $team = mailbox();
    $hidden = mailbox([
        'email' => 'board@example.com',
        'name' => 'Board',
    ]);
    $inactive = mailbox([
        'active' => false,
        'email' => 'old@example.com',
        'name' => 'Old',
    ]);
    $inbox = folder($team);
    $projects = folder($team, 'Projects', FolderRole::Custom);
    $hiddenInbox = folder($hidden);
    $inactiveInbox = folder($inactive);
    $user = member($team, AccessLevel::Read);
    $user->forceFill(['email' => 'Me@Example.com'])->save();
    $inactive->accesses()->create([
        'accessor_id' => $user->id,
        'accessor_type' => $user->getMorphClass(),
        'level' => AccessLevel::Read,
    ]);
    $before = storedMessage($inbox, 1);

    $new = storedMessage($inbox, 2, [
        'from_name' => 'Customer',
        'subject' => 'Question',
    ]);
    storedMessage($inbox, 3, [
        'is_seen' => true,
    ]);
    storedMessage($inbox, 4, [
        'from_email' => 'TEAM@example.com',
    ]);
    storedMessage($inbox, 5, [
        'from_email' => 'me@example.com',
    ]);
    storedMessage($inbox, 9, [
        'sent_at' => now()->subDays(2),
    ]);
    storedMessage($projects, 6);
    storedMessage($hiddenInbox, 7);
    $last = storedMessage($inactiveInbox, 8);

    $this->actingAs($user)
        ->getJson(route('mailbox.notifications', ['after' => $before->id]))
        ->assertOk()
        ->assertExactJson([
            'count' => 1,
            'cursor' => $last->id,
            'mailboxes' => [
                $team->id,
            ],
            'messages' => [
                [
                    'folder_id' => $inbox->id,
                    'folder_url' => route('mailbox.folders.show', [$team, $inbox]),
                    'from_email' => 'sender@example.org',
                    'from_name' => 'Customer',
                    'id' => $new->id,
                    'mailbox_id' => $team->id,
                    'mailbox_name' => 'Team',
                    'sent_at' => null,
                    'subject' => 'Question',
                    'url' => route('mailbox.messages.show', [$team, $new]),
                ],
            ],
        ]);

    $this->getJson(route('mailbox.notifications', ['after' => $last->id]))
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('messages', []);
});

it('only returns messages of the requested mailboxes the user may read', function (): void {
    $team = mailbox();
    $sales = mailbox([
        'email' => 'sales@example.com',
        'name' => 'Sales',
    ]);
    $hidden = mailbox([
        'email' => 'board@example.com',
        'name' => 'Board',
    ]);
    $teamInbox = folder($team);
    $salesInbox = folder($sales);
    $hiddenInbox = folder($hidden);
    $user = member($team, AccessLevel::Read);
    $sales->accesses()->create([
        'accessor_id' => $user->id,
        'accessor_type' => $user->getMorphClass(),
        'level' => AccessLevel::Read,
    ]);
    $before = storedMessage($teamInbox, 1);
    storedMessage($teamInbox, 2);
    $wanted = storedMessage($salesInbox, 3);
    storedMessage($hiddenInbox, 4);

    $this->actingAs($user)
        ->getJson(route('mailbox.notifications', [
            'after' => $before->id,
            'mailboxes' => [
                $sales->id,
                $hidden->id,
                999,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('mailboxes', [
            $sales->id,
        ])
        ->assertJsonPath('messages.0.id', $wanted->id);

    $this->getJson(route('mailbox.notifications', [
        'after' => $before->id,
        'mailboxes' => [
            $hidden->id,
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('mailboxes', [])
        ->assertJsonPath('messages', []);

    $this->getJson(route('mailbox.notifications', [
        'after' => $before->id,
    ]))
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('mailboxes', [
            $sales->id,
            $team->id,
        ]);
});

it('rejects mailbox ids that are not numbers', function (): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->getJson(route('mailbox.notifications', [
            'after' => 0,
            'mailboxes' => [
                'abc',
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mailboxes.0');
});

it('counts every new message but returns at most the newest ten', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $before = storedMessage($inbox, 1);

    foreach (range(2, 13) as $uid) {
        storedMessage($inbox, $uid);
    }

    $response = $this->actingAs(member($mailbox, AccessLevel::Read))
        ->getJson(route('mailbox.notifications', ['after' => $before->id]))
        ->assertOk()
        ->assertJsonPath('count', 12)
        ->assertJsonCount(MailboxNotifications::LIMIT, 'messages');

    expect($response->json('messages.0.id'))->toBe(MailboxMessage::query()->max('id'))
        ->and($response->json('cursor'))->toBe(MailboxMessage::query()->max('id'));
});

it('refuses users without access to any mailbox and guests', function (): void {
    mailbox();

    $this->actingAs(user())
        ->getJson(route('mailbox.notifications', ['after' => 0]))
        ->assertForbidden();

    auth()->logout();

    $this->getJson(route('mailbox.notifications'))
        ->assertUnauthorized();
});

it('rejects a cursor that is not a number', function (): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->getJson(route('mailbox.notifications', ['after' => 'abc']))
        ->assertUnprocessable();
});

it('shares the notification endpoint only with users who may read a mailbox', function (): void {
    $mailbox = mailbox();
    Route::middleware([
        'web',
        ShareMailboxNavigation::class,
    ])->get('host-page', fn () => Inertia::render('Dashboard'));

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get('host-page')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('vueMailboxNotifications.url', route('mailbox.notifications'))
            ->where('vueMailboxNotifications.poll_seconds', 30)
            ->where('vueMailboxNotifications.labels.enable', 'Turn on notifications'));

    $this->actingAs(user())
        ->get('host-page')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('vueMailboxNotifications', null));

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, folder($mailbox)]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('vueMailboxNotifications.url', route('mailbox.notifications')));
});
