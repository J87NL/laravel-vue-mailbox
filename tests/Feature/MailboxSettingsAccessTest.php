<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
