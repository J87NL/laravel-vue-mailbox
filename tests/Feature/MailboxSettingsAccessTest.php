<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Actions\GrantAccess;
use J87NL\Mailbox\Actions\TestConnection;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use J87NL\Mailbox\Jobs\SyncMailboxJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\VueMailbox\Tests\Support\User;
use Mockery\MockInterface;

it('gives the creator manage access to a new mailbox they could not open otherwise', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    Gate::define('manageMailboxes', fn (User $user): bool => $user->email === 'office-manager@example.com');
    $creator = user(['email' => 'office-manager@example.com']);

    $response = $this->actingAs($creator)
        ->post(route('mailbox.settings.store'), newMailboxForm());

    $mailbox = Mailbox::query()
        ->sole();

    $response->assertRedirect(route('mailbox.settings.edit', $mailbox));

    expect(MailboxAccess::query()
        ->where('mailbox_id', $mailbox->id)
        ->where('accessor_type', $creator->getMorphClass())
        ->where('accessor_id', $creator->id)
        ->first()?->level)->toBe(AccessLevel::Manage);

    $this->actingAs($creator)
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertOk();
});

it('adds no access row for a creator who already manages every mailbox', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    config()->set('mailbox.access.admins', ['admin@example.com']);

    $this->actingAs(user(['email' => 'admin@example.com']))
        ->post(route('mailbox.settings.store'), newMailboxForm())
        ->assertRedirect();

    expect(Mailbox::query()
        ->count())->toBe(1)
        ->and(MailboxAccess::query()
            ->count())->toBe(0);
});

it('creates no mailbox when granting the creator access fails', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    Gate::define('manageMailboxes', fn (User $user): bool => true);
    $this->mock(GrantAccess::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('Access table unavailable'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs(user(['email' => 'office-manager@example.com']))
        ->post(route('mailbox.settings.store'), newMailboxForm()))
        ->toThrow(RuntimeException::class);

    expect(Mailbox::query()
        ->count())->toBe(0);
});

it('tests the connection of a new mailbox outside a database transaction', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    Gate::define('manageMailboxes', fn (User $user): bool => true);
    $levels = [];
    $this->mock(TestConnection::class, function (MockInterface $mock) use (&$levels): void {
        $mock->shouldReceive('handle')
            ->andReturnUsing(function () use (&$levels): array {
                $levels[] = DB::transactionLevel();

                return [];
            });
        $mock->shouldReceive('passes')
            ->andReturnTrue();
    });
    $baseline = DB::transactionLevel();

    $this->actingAs(user(['email' => 'office-manager@example.com']))
        ->post(route('mailbox.settings.store'), newMailboxForm())
        ->assertRedirect();

    expect($levels)->toBe([$baseline]);
});

it('lets only a user who also manages the mailbox delete it', function (): void {
    Gate::define('manageMailboxes', fn (User $user): bool => true);
    $mailbox = mailbox();
    $outsider = user();

    $this->actingAs($outsider)
        ->delete(route('mailbox.settings.destroy', $mailbox))
        ->assertForbidden();

    expect(Mailbox::query()
        ->whereKey($mailbox->id)
        ->exists())->toBeTrue();

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->delete(route('mailbox.settings.destroy', $mailbox))
        ->assertRedirect(route('mailbox.settings.index'));

    expect(Mailbox::query()
        ->whereKey($mailbox->id)
        ->exists())->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function newMailboxForm(): array
{
    return [
        'active' => '1',
        'append_sent' => '1',
        'authentication' => 'plain',
        'email' => 'office@example.com',
        'imap_encryption' => 'ssl',
        'imap_host' => 'imap.example.com',
        'imap_password' => 'secret',
        'imap_port' => 993,
        'imap_username' => 'office@example.com',
        'name' => 'Office',
        'smtp_host' => 'smtp.example.com',
        'smtp_password' => 'secret',
        'smtp_port' => 587,
        'smtp_username' => 'office@example.com',
    ];
}

it('sends the access form without the list of users', function (): void {
    $mailbox = mailbox();
    user(['name' => 'Secret Colleague']);

    $response = $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertOk();

    $accessor = collect($response->inertiaProps('accessForm.fields'))->firstWhere('name', 'accessor');

    expect($accessor)->not->toHaveKey('options')
        ->and($accessor['searchUrl'])->toBe(route('mailbox.settings.accessors', $mailbox))
        ->and($accessor['minSearchLength'])->toBe(2)
        ->and($response->getContent())->not->toContain('Secret Colleague');
});

it('searches users and groups for a manager of the mailbox', function (): void {
    $mailbox = mailbox();
    $jane = user(['email' => 'jane@example.com', 'name' => 'Jane Doe']);
    user(['email' => 'carl@example.com', 'name' => 'Carl']);

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => 'JANE']))
        ->assertOk()
        ->assertExactJson([
            [
                'label' => 'User: Jane Doe',
                'value' => sprintf('%s:%s', $jane->getMorphClass(), $jane->id),
            ],
        ]);
});

it('returns nothing for a search term shorter than two characters', function (): void {
    $mailbox = mailbox();
    user(['name' => 'Jane Doe']);

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => ' j ']))
        ->assertOk()
        ->assertExactJson([]);
});

it('returns at most twenty matches', function (): void {
    $mailbox = mailbox();

    foreach (range(1, 25) as $number) {
        user(['email' => "member{$number}@example.com", 'name' => "Member {$number}"]);
    }

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => 'member']))
        ->assertOk()
        ->assertJsonCount(20);
});

it('refuses the search to users who do not manage the mailbox', function (): void {
    $mailbox = mailbox();
    member(mailbox(['name' => 'Other']), AccessLevel::Manage);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => 'test']))
        ->assertForbidden();
});

it('throttles the search', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($manager)
            ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => 'te']))
            ->assertOk();
    }

    $this->actingAs($manager)
        ->getJson(route('mailbox.settings.accessors', [$mailbox, 'q' => 'te']))
        ->assertTooManyRequests();
});

it('grants a searched accessor and rejects one that does not exist', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);
    $colleague = user();

    $this->actingAs($manager)
        ->from(route('mailbox.settings.edit', $mailbox))
        ->post(route('mailbox.settings.access.store', $mailbox), [
            'accessor' => sprintf('%s:999', $colleague->getMorphClass()),
            'level' => 'read',
        ])
        ->assertSessionHasErrors('accessor');

    $this->actingAs($manager)
        ->from(route('mailbox.settings.edit', $mailbox))
        ->post(route('mailbox.settings.access.store', $mailbox), [
            'accessor' => sprintf('%s:%s', $colleague->getMorphClass(), $colleague->id),
            'level' => 'read',
        ])
        ->assertSessionHasNoErrors();

    expect(MailboxAccess::query()
        ->where('accessor_id', $colleague->id)
        ->sole()->level)->toBe(AccessLevel::Read);
});

it('changes the level of existing access through the form', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);
    $colleague = member($mailbox, AccessLevel::Read);

    $this->actingAs($manager)
        ->from(route('mailbox.settings.edit', $mailbox))
        ->post(route('mailbox.settings.access.store', $mailbox), [
            'accessor' => sprintf('%s:%s', $colleague->getMorphClass(), $colleague->id),
            'level' => 'write',
        ])
        ->assertSessionHasNoErrors();

    expect(MailboxAccess::query()
        ->where('accessor_id', $colleague->id)
        ->sole()->level)->toBe(AccessLevel::Write);
});

it('lists an inactive mailbox for its manager and lets them reactivate it', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    $mailbox = mailbox(['active' => false]);
    $manager = member($mailbox, AccessLevel::Manage);

    $this->actingAs($manager)
        ->get(route('mailbox.settings.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('table.data', 1)
            ->where('table.data.0.id', $mailbox->id)
            ->where('table.data.0.active', false));

    $this->actingAs($manager)
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertOk();

    $this->actingAs($manager)
        ->put(route('mailbox.settings.update', $mailbox), [
            ...newMailboxForm(),
            'email' => $mailbox->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($mailbox->refresh()->active)->toBeTrue();
});

it('keeps the manager of an inactive mailbox out of its folders and its sync', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    $mailbox = mailbox(['active' => false]);
    $folder = folder($mailbox);
    $manager = member($mailbox, AccessLevel::Manage);

    $this->actingAs($manager)
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('mailbox.folders.refresh', [$mailbox, $folder]))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('mailbox.index'))
        ->assertRedirect(route('mailbox.settings.index'));

    Bus::assertNothingDispatched();
});

it('lets the creator of an inactive mailbox open its settings', function (): void {
    Bus::fake([SyncMailboxJob::class, SyncFolderJob::class]);
    Gate::define('manageMailboxes', fn (User $user): bool => $user->email === 'office-manager@example.com');
    $creator = user(['email' => 'office-manager@example.com']);

    $this->actingAs($creator)
        ->post(route('mailbox.settings.store'), [
            ...newMailboxForm(),
            'active' => '0',
        ]);

    $mailbox = Mailbox::query()
        ->sole();

    expect($mailbox->active)->toBeFalse();

    $this->actingAs($creator)
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertOk();

    $this->actingAs($creator)
        ->get(route('mailbox.folders.show', $mailbox))
        ->assertForbidden();
});
