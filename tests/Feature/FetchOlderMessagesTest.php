<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Jobs\FetchOlderMessagesJob;
use J87NL\Mailbox\Jobs\SyncFolderJob;

beforeEach(function (): void {
    Bus::fake([
        FetchOlderMessagesJob::class,
        SyncFolderJob::class,
    ]);
});

it('queues fetching older messages and returns to the folder', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->post(route('mailbox.folders.older', [$mailbox, $folder]))
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $folder]));

    Bus::assertDispatched(FetchOlderMessagesJob::class, fn (FetchOlderMessagesJob $job): bool => $job->folderId === $folder->id);
});

it('refuses fetching older messages of a mailbox the user may not open or of a folder of another mailbox', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $foreign = folder(mailbox(['name' => 'Other']), 'Elsewhere', FolderRole::Custom);
    $member = member($mailbox, AccessLevel::Read);

    $this->actingAs(user())
        ->post(route('mailbox.folders.older', [$mailbox, $folder]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('mailbox.folders.older', [$mailbox, $foreign]))
        ->assertNotFound();

    Bus::assertNotDispatched(FetchOlderMessagesJob::class);
});

it('offers older messages only when the server holds more than the folder stored', function (int $messageCount, bool $offered): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, attributes: ['message_count' => $messageCount]);
    storedMessage($folder, 1);
    storedMessage($folder, 2);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('olderMessagesUrl', $offered ? route('mailbox.folders.older', [$mailbox, $folder]) : null));
})->with([
    'more on the server' => [3, true],
    'all stored' => [2, false],
    'not counted yet' => [0, false],
]);

it('does not offer older messages in a folder that is not synced', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, 'All Mail', FolderRole::Custom, [
        'flags' => ['\\All'],
        'message_count' => 10,
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('olderMessagesUrl', null));
});

it('stops offering older messages once the folder is exhausted', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, attributes: [
        'message_count' => 10,
        'older_exhausted' => true,
    ]);
    storedMessage($folder, 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('olderMessagesUrl', null));
});

it('refuses fetching older messages of a folder that does not offer them', function (array $attributes): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, 'Elsewhere', FolderRole::Custom, $attributes);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->post(route('mailbox.folders.older', [$mailbox, $folder]))
        ->assertNotFound();

    Bus::assertNotDispatched(FetchOlderMessagesJob::class);
})->with([
    'not selectable' => [['selectable' => false]],
    'not synced' => [['flags' => ['\\All']]],
    'exhausted' => [['older_exhausted' => true]],
]);
