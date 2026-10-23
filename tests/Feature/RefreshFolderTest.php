<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Jobs\SyncFolderJob;

beforeEach(function (): void {
    Bus::fake([SyncFolderJob::class]);
});

it('syncs a folder on request even when it was synced a moment ago and returns to it', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, attributes: [
        'synced_at' => now()->subSeconds(5),
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->post(route('mailbox.folders.refresh', [$mailbox, $folder]))
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $folder]));

    Bus::assertDispatched(SyncFolderJob::class, fn (SyncFolderJob $job): bool => $job->folderId === $folder->id);
});

it('refuses a refresh of a mailbox the user may not open or of a folder of another mailbox', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $other = mailbox([
        'name' => 'Other',
    ]);
    $foreign = folder($other, 'Elsewhere', FolderRole::Custom);
    $outsider = user();
    $member = member($mailbox, AccessLevel::Read);

    $this->actingAs($outsider)
        ->post(route('mailbox.folders.refresh', [$mailbox, $folder]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('mailbox.folders.refresh', [$mailbox, $foreign]))
        ->assertNotFound();

    Bus::assertNotDispatched(SyncFolderJob::class);
});

it('gives the folder page the refresh url', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('refreshUrl', route('mailbox.folders.refresh', [$mailbox, $folder])));
});
