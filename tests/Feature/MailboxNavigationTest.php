<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\VueMailbox\Http\Middleware\ShareMailboxNavigation;
use J87NL\VueMailbox\Support\MailboxNavigation;

it('lists the active mailboxes the user may open with the unread count of their inbox', function (): void {
    $sales = mailbox([
        'email' => 'sales@example.com',
        'name' => 'Sales',
    ]);
    $support = mailbox([
        'email' => 'support@example.com',
        'name' => 'Support',
    ]);
    $hidden = mailbox([
        'email' => 'board@example.com',
        'name' => 'Board',
    ]);
    $inactive = mailbox([
        'active' => false,
        'email' => 'old@example.com',
        'name' => 'Archive',
    ]);
    folder($sales, 'INBOX', FolderRole::Inbox, [
        'unseen_count' => 4,
    ]);
    folder($sales, 'Projects', FolderRole::Custom, [
        'unseen_count' => 9,
    ]);
    folder($hidden, 'INBOX', FolderRole::Inbox, [
        'unseen_count' => 2,
    ]);
    $user = member($support, AccessLevel::Read);
    member($sales, AccessLevel::Read)->delete();

    foreach ([
        $sales,
        $inactive,
    ] as $mailbox) {
        $mailbox->accesses()->create([
            'accessor_id' => $user->id,
            'accessor_type' => $user->getMorphClass(),
            'level' => AccessLevel::Read,
        ]);
    }

    $items = app(MailboxNavigation::class)->for($user);

    expect($items)->toHaveCount(2)
        ->and($items[0])->toMatchArray([
            'active_path' => sprintf('/mailbox/%d/', $sales->id),
            'email' => 'sales@example.com',
            'id' => $sales->id,
            'name' => 'Sales',
            'unseen' => 4,
            'url' => route('mailbox.folders.show', $sales),
        ])
        ->and($items[1])->toMatchArray([
            'id' => $support->id,
            'name' => 'Support',
            'unseen' => 0,
        ])
        ->and($hidden->id)->not->toBeIn(array_column($items, 'id'));
});

it('lists nothing for a guest', function (): void {
    mailbox();

    expect(app(MailboxNavigation::class)->for(null))->toBe([]);
});

it('shares the mailbox navigation through its middleware', function (): void {
    $mailbox = mailbox();
    Route::middleware([
        'web',
        ShareMailboxNavigation::class,
    ])->get('host-page', fn () => Inertia::render('Dashboard'));

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get('host-page')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('mailboxNavigation', 1)
            ->where('mailboxNavigation.0.name', 'Team')
            ->where('mailboxNavigation.0.url', route('mailbox.folders.show', $mailbox)));

    auth()->logout();

    $this->get('host-page')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('mailboxNavigation', []));
});

it('offers a mailbox switcher on the folder page unless the host turns it off', function (bool $switcher, int $count): void {
    config()->set('vue-mailbox.mailbox_switcher', $switcher);
    $team = mailbox();
    $sales = mailbox([
        'email' => 'sales@example.com',
        'name' => 'Sales',
    ]);
    $user = member($team, AccessLevel::Read);
    $sales->accesses()->create([
        'accessor_id' => $user->id,
        'accessor_type' => $user->getMorphClass(),
        'level' => AccessLevel::Read,
    ]);

    $this->actingAs($user)
        ->get(route('mailbox.folders.show', [$team, folder($team)]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('mailboxes', $count)
            ->has('folders', 1)
            ->where('vue_mailbox_translations.switch_mailbox', 'Switch mailbox')
            ->where('vue_mailbox_translations.reply', 'Reply'));
})->with([
    'on' => [
        true,
        2,
    ],
    'off' => [
        false,
        0,
    ],
]);

it('needs the same number of queries for one mailbox as for many', function (): void {
    $queriesFor = function (int $mailboxes): int {
        $user = user();

        foreach (range(1, $mailboxes) as $number) {
            $mailbox = mailbox([
                'email' => sprintf('box-%d-%d@example.com', $mailboxes, $number),
                'name' => sprintf('Box %d', $number),
            ]);
            folder($mailbox, 'INBOX', FolderRole::Inbox, [
                'unseen_count' => $number,
            ]);
            $mailbox->accesses()->create([
                'accessor_id' => $user->id,
                'accessor_type' => $user->getMorphClass(),
                'level' => AccessLevel::Read,
            ]);
        }

        app(MailboxAccessResolver::class)->flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(MailboxNavigation::class)->for($user);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queriesFor(1))->toBe($queriesFor(6));
});
