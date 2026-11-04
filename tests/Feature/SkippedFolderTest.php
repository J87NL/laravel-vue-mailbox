<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;

it('marks a skipped special-use folder as not synced and a normal folder as synced', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $important = folder($mailbox, '[Gmail]/Important', FolderRole::Custom, [
        'flags' => ['\\HasNoChildren', '\\Important'],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $inbox]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folders', fn ($folders): bool => collect($folders)->pluck('syncs', 'id')->all() === [
                $inbox->id => true,
                $important->id => false,
            ]));
});

it('shows the explanation on the page of a folder that is not synced and not on a synced one', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $important = folder($mailbox, '[Gmail]/Important', FolderRole::Custom, [
        'flags' => ['\\Important'],
    ]);
    $user = member($mailbox, AccessLevel::Read);

    $this->actingAs($user)
        ->get(route('mailbox.folders.show', [$mailbox, $important]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.syncs', false)
            ->where('syncNotice', __('vue-mailbox::mailbox.not_synced_hint')));

    $this->actingAs($user)
        ->get(route('mailbox.folders.show', [$mailbox, $inbox]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.syncs', true)
            ->where('syncNotice', null));
});

it('marks Gmail All Mail as synced', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $allMail = folder($mailbox, '[Gmail]/All Mail', FolderRole::Archive, [
        'flags' => ['\\HasNoChildren', '\\All'],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $allMail]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.syncs', true)
            ->where('syncNotice', null));
});
