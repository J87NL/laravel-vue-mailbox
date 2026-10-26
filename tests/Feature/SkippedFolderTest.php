<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;

it('marks a folder with the all special use as not synced and a normal folder as synced', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $allMail = folder($mailbox, '[Gmail]/All Mail', FolderRole::Custom, [
        'flags' => ['\\HasNoChildren', '\\All'],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $inbox]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folders', fn ($folders): bool => collect($folders)->pluck('syncs', 'id')->all() === [
                $inbox->id => true,
                $allMail->id => false,
            ]));
});

it('shows the explanation on the page of a folder that is not synced and not on a synced one', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $allMail = folder($mailbox, '[Gmail]/All Mail', FolderRole::Custom, [
        'flags' => ['\\All'],
    ]);
    $user = member($mailbox, AccessLevel::Read);

    $this->actingAs($user)
        ->get(route('mailbox.folders.show', [$mailbox, $allMail]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.syncs', false)
            ->where('syncNotice', __('vue-mailbox::mailbox.not_synced_hint')));

    $this->actingAs($user)
        ->get(route('mailbox.folders.show', [$mailbox, $inbox]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.syncs', true)
            ->where('syncNotice', null));
});
