<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\Mailbox\Models\MailboxMessage;

beforeEach(function (): void {
    Bus::fake([SyncFolderJob::class]);
});

it('only queues a folder sync when the folder was not synced in the last minute', function (?string $syncedAt, bool $queued): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, attributes: ['synced_at' => is_null($syncedAt) ? null : now()->modify($syncedAt)]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mailbox/Index')
            ->where('composeUrl', null)
            ->where('settingsUrl', null));

    $queued
        ? Bus::assertDispatched(SyncFolderJob::class)
        : Bus::assertNotDispatched(SyncFolderJob::class);
})->with([
    'never synced' => [null, true],
    'synced long ago' => ['-5 minutes', true],
    'synced just now' => ['-10 seconds', false],
]);

it('blocks remote images unless the user asks for them', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'body_html' => '<img src="https://tracker.test/pixel.gif"><p style="background:url(https://tracker.test/bg.gif)">Hi</p>',
    ]);
    $user = member($mailbox, AccessLevel::Read);

    $this->actingAs($user)
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mailbox/Show')
            ->where('remoteImages', false)
            ->where('hasRemoteImages', true)
            ->where('html', fn (string $html): bool => ! str_contains($html, 'tracker.test'))
            ->where('actionsUrl', null));

    $this->actingAs($user)
        ->get(route('mailbox.messages.show', [$mailbox, $message, 'images' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('remoteImages', true)
            ->where('hasRemoteImages', false)
            ->where('html', fn (string $html): bool => str_contains($html, 'tracker.test/pixel.gif')));
});

it('marks a message read when a writer opens it but not when a reader does', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))->get(route('mailbox.messages.show', [$mailbox, $message]));

    expect($message->refresh()->is_seen)->toBeFalse();

    $this->actingAs(member($mailbox, AccessLevel::Write))->get(route('mailbox.messages.show', [$mailbox, $message]));

    expect($message->refresh()->is_seen)->toBeTrue();
});

it('prefills a reply with recipient, subject and quote', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'body_text' => 'Original <text>',
        'from_name' => 'Sender',
        'subject' => 'Question',
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [$mailbox, 'reply' => $message->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mailbox/Compose')
            ->where('mode', 'reply')
            ->where('form.defaults.to', 'sender@example.org')
            ->where('form.defaults.subject', 'Re: Question')
            ->where('form.defaults.reply_to_message', $message->id)
            ->where('form.defaults.body', fn (string $body): bool => str_contains($body, 'Sender &lt;sender@example.org&gt;') && str_contains($body, 'Original &lt;text&gt;')));
});

it('sends a reply through the compose form', function (): void {
    $mailbox = mailbox(['append_sent' => false]);
    $message = storedMessage(folder($mailbox), 1, ['message_id' => '<question@example.org>']);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Hi\r\n\r\nBody"),
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>Answer</p>',
            'reply_to_message' => $message->id,
            'subject' => 'Re: Question',
            'to' => 'sender@example.org',
        ])
        ->assertRedirect(route('mailbox.folders.show', $mailbox))
        ->assertSessionHasNoErrors();

    $sent = $this->connector->transport->sent[0];

    expect($sent->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<question@example.org>')
        ->and(MailboxMessage::query()->find($message->id)->is_answered)->toBeTrue();
});

it('rejects invalid recipients', function (): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>Hi</p>',
            'subject' => 'Hi',
            'to' => 'valid@example.org, not-an-address',
        ])
        ->assertSessionHasErrors('to');

    expect($this->connector->transport->sent)->toBe([]);
});

it('moves only into folders of the same mailbox', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);
    $foreign = folder(mailbox(['name' => 'Other']), 'Projects', FolderRole::Custom);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.messages.actions', $mailbox), [
            'action' => 'move',
            'folder_id' => $foreign->id,
            'ids' => [$message->id],
        ])
        ->assertNotFound();

    expect(MailboxMessage::query()->whereKey($message->id)->exists())->toBeTrue();
});

it('shows an error instead of revoking the last manager', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);
    $access = MailboxAccess::query()->sole();

    $this->actingAs($manager)
        ->from(route('mailbox.settings.edit', $mailbox))
        ->delete(route('mailbox.settings.access.destroy', [$mailbox, $access]))
        ->assertRedirect(route('mailbox.settings.edit', $mailbox))
        ->assertSessionHasErrors('accessor');

    expect(MailboxAccess::query()->count())->toBe(1);
});

it('turns a failing connection test into form errors when creating a mailbox', function (): void {
    config()->set('mailbox.access.admins', ['admin@example.com']);
    $this->connector->failImap('Authentication failed');

    $this->actingAs(user(['email' => 'admin@example.com']))
        ->post(route('mailbox.settings.store'), [
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
        ])
        ->assertSessionHasErrors('imap_host');

    expect(Mailbox::query()->count())->toBe(0);
});
