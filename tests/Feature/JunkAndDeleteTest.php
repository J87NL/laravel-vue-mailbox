<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\AuditAction;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\MailboxAuditEntry;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Testing\RecordingFolder;

it('marks a message as spam and back from the message actions', function (): void {
    Bus::fake();
    $mailbox = mailbox();
    $inbox = folder($mailbox);
    $junk = folder($mailbox, 'Spam', FolderRole::Junk);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Hi\r\n\r\nBody"),
    ]);
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'junk',
            'ids' => [storedMessage($inbox, 1)->id],
        ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $inbox]));

    $this->connector->remote($mailbox)->addFolder(new RecordingFolder('Spam'));
    $this->connector->remote($mailbox)->folder('Spam')->setMessages([
        new FakeMessage(9, ['$Junk'], "Subject: Hi\r\n\r\nBody"),
    ]);

    $this->actingAs($writer)
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'not_junk',
            'ids' => [storedMessage($junk, 9)->id],
        ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $junk]));

    expect(MailboxAuditEntry::query()
        ->orderBy('id')
        ->pluck('action')
        ->all())->toBe([
            AuditAction::MarkedJunk,
            AuditAction::MarkedNotJunk,
        ])
        ->and(MailboxMessage::query()
            ->count())->toBe(0);
});

it('refuses spam actions on folders the buttons are not offered in', function (FolderRole $role, string $action): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox, ucfirst($role->value), $role), 1);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => $action,
            'ids' => [$message->id],
        ])
        ->assertStatus(422);

    expect(MailboxMessage::query()
        ->whereKey($message->id)
        ->exists())->toBeTrue();
})->with([
    'junk from sent' => [
        FolderRole::Sent,
        'junk',
    ],
    'not junk outside the junk folder' => [
        FolderRole::Archive,
        'not_junk',
    ],
]);

it('offers mark as spam outside the junk folder and not spam inside it as bulk actions', function (FolderRole $role, ?string $expected): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, $role === FolderRole::Inbox ? 'INBOX' : ucfirst($role->value), $role);

    $labels = collect($this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->viewData('page')['props']['table']['bulkActions'])
        ->pluck('label');

    expect($labels->contains(__('mailbox::mailbox.mark_junk')))->toBe($expected === 'junk')
        ->and($labels->contains(__('mailbox::mailbox.mark_not_junk')))->toBe($expected === 'not_junk');
})->with([
    'inbox' => [
        FolderRole::Inbox,
        'junk',
    ],
    'junk' => [
        FolderRole::Junk,
        'not_junk',
    ],
    'sent' => [
        FolderRole::Sent,
        null,
    ],
]);
