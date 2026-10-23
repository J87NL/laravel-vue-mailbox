<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Enums\ImapFlag;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Jobs\FlagRemoteMessagesJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Testing\RecordingFolder;
use J87NL\VueMailbox\Support\InlineUrls;
use Symfony\Component\Mime\Email;

/**
 * @return array{0: Mailbox, 1: MailboxMessage, 2: RecordingFolder}
 */
function newsletter(): array
{
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'body_fetched_at' => null,
        'body_html' => null,
    ]);
    $raw = (new Email)
        ->from('sender@example.org')
        ->to('team@example.com')
        ->subject('Newsletter')
        ->html('<p>Hi</p><img src="cid:logo"><img src="cid:banner"><img src="cid:footer">')
        ->embed('LOGO', 'logo', 'image/png')
        ->embed('BANNER', 'banner', 'image/png')
        ->embed('FOOTER', 'footer', 'image/png')
        ->attach('PDF', 'report.pdf', 'application/pdf')
        ->toString();
    $remote = test()->connector->remote($mailbox)->folder('INBOX');
    $remote->setMessages([
        new FakeMessage(1, [], $raw),
    ]);

    return [
        $mailbox,
        $message,
        $remote,
    ];
}

/**
 * @return array<int, string>
 */
function inlineContentIds(MailboxMessage $message): array
{
    return collect($message->refresh()->attachments)
        ->where('inline', true)
        ->pluck('content_id')
        ->values()
        ->all();
}

it('opens a message, shows its inline images and downloads its attachment with one fetch', function (): void {
    [$mailbox, $message, $remote] = newsletter();
    $user = member($mailbox, AccessLevel::Read);

    $this->actingAs($user)
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('attachments.0.filename', 'report.pdf'));

    $inline = app(InlineUrls::class)->for($message->refresh());
    $images = collect(inlineContentIds($message))
        ->map(fn (string $contentId): string => $this->get($inline($contentId))->assertOk()->streamedContent())
        ->all();
    $index = collect($message->attachments)->search(fn (array $attachment): bool => $attachment['filename'] === 'report.pdf');
    $download = $this->get(route('mailbox.attachments.download', [$mailbox, $message, $index]))->assertOk();

    expect($images)->toBe([
        'LOGO',
        'BANNER',
        'FOOTER',
    ])
        ->and($download->streamedContent())->toBe('PDF')
        ->and($remote->messageFetches)->toBe(1);
});

it('still checks access before serving a cached attachment or inline image', function (): void {
    [$mailbox, $message, $remote] = newsletter();
    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertOk();
    $contentId = inlineContentIds($message)[0];
    $other = mailbox([
        'email' => 'other@example.com',
    ]);

    $this->actingAs(user())
        ->get(route('mailbox.attachments.download', [$mailbox, $message, 0]))
        ->assertForbidden();
    $this->actingAs(member($other, AccessLevel::Manage))
        ->get(route('mailbox.attachments.download', [$other, $message, 0]))
        ->assertNotFound();
    $this->get(route('mailbox.inline', [$message, $contentId]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('mailbox.inline', now()->subMinute(), [$message, $contentId]))->assertForbidden();
    $this->get(sprintf('%s0', app(InlineUrls::class)->for($message)($contentId)))->assertForbidden();

    expect($remote->messageFetches)->toBe(1);
});

it('marks an opened message read before the response and flags it on the server after it', function (): void {
    Bus::fake([FlagRemoteMessagesJob::class]);
    [$mailbox, $message, $remote] = newsletter();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertOk();

    expect($message->refresh()->is_seen)->toBeTrue()
        ->and($remote->operations)->toBe([]);

    Bus::assertDispatchedAfterResponse(FlagRemoteMessagesJob::class, fn (FlagRemoteMessagesJob $job): bool => $job->folderId === $message->mailbox_folder_id
        && $job->uids === [1]
        && $job->flag === ImapFlag::Seen);
});

it('sets the seen flag on the server once the response is sent', function (): void {
    [$mailbox, $message, $remote] = newsletter();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertOk();

    expect($remote->message(1)?->flags())->toContain(ImapFlag::Seen->value);
});
