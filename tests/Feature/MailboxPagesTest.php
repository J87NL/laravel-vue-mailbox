<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Attachment;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use J87NL\Mailbox\Jobs\SyncMailboxJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Testing\RecordingFolder;
use J87NL\VueMailbox\Support\InlineUrls;
use J87NL\VueMailbox\Tables\MessagesTable;
use Symfony\Component\Mime\Email;

beforeEach(function (): void {
    Bus::fake([SyncFolderJob::class]);
});

it('only queues a folder sync when the folder was not synced in the last minute', function (?string $syncedAt, bool $queued): void {
    $mailbox = mailbox();
    $folder = folder($mailbox, attributes: ['synced_at' => is_null($syncedAt) ? null : now()->modify($syncedAt)]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Index')
            ->where('composeUrl', null)
            ->where('settingsUrl', null));

    $queued
        ? Bus::assertDispatched(SyncFolderJob::class)
        : Bus::assertNotDispatched(SyncFolderJob::class);
})->with([
    'never synced' => [null, true],
    'synced long ago' => [
        '-5 minutes',
        true,
    ],
    'synced just now' => [
        '-10 seconds',
        false,
    ],
]);

it('blocks remote images unless the user asks for them', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'body_html' => '<img src="https://tracker.test/pixel.gif"><p style="background:url(https://tracker.test/bg.gif)">Hi</p>',
    ]);
    $user = member($mailbox, AccessLevel::Read);

    $this->actingAs($user)
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Show')
            ->where('remoteImages', false)
            ->where('hasRemoteImages', true)
            ->where('html', fn (string $html): bool => ! str_contains($html, 'tracker.test'))
            ->where('actionsUrl', null));

    $this->actingAs($user)
        ->get(route('mailbox.messages.show', [
            $mailbox,
            $message,
            'images' => 1,
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
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
        'body_html' => null,
        'body_text' => 'Original <text>',
        'from_name' => 'Sender',
        'subject' => 'Question',
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $message->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Compose')
            ->where('mode', 'reply')
            ->where('form.defaults.to', ['sender@example.org'])
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
        ->assertRedirect(route('mailbox.folders.show', [
            $mailbox,
            $message->folder,
        ]))
        ->assertSessionHasNoErrors();

    $sent = $this->connector->transport->sent[0];

    expect($sent->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<question@example.org>')
        ->and(MailboxMessage::query()
            ->find($message->id)->is_answered)->toBeTrue();
});

it('rejects invalid recipients', function (): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>Hi</p>',
            'subject' => 'Hi',
            'to' => 'valid@example.org, not-an-address',
        ])
        ->assertSessionHasErrors('to.1');

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

    expect(MailboxMessage::query()
        ->whereKey($message->id)->exists())->toBeTrue();
});

it('shows an error instead of revoking the last manager', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);
    $access = MailboxAccess::query()
        ->sole();

    $this->actingAs($manager)
        ->from(route('mailbox.settings.edit', $mailbox))
        ->delete(route('mailbox.settings.access.destroy', [$mailbox, $access]))
        ->assertRedirect(route('mailbox.settings.edit', $mailbox))
        ->assertSessionHasErrors('accessor');

    expect(MailboxAccess::query()
        ->count())->toBe(1);
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

    expect(Mailbox::query()
        ->count())->toBe(0);
});

it('sends users without any mailbox away from the mailbox index', function (): void {
    $mailbox = mailbox();
    folder($mailbox);

    $this->actingAs(user())
        ->get(route('mailbox.index'))
        ->assertForbidden();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.index'))
        ->assertRedirect(route('mailbox.folders.show', $mailbox));
});

it('sends a mailbox manager without an open mailbox to the mailbox settings', function (): void {
    config()->set('mailbox.access.admins', [
        'admin@example.com',
    ]);

    $this->actingAs(user(['email' => 'admin@example.com']))
        ->get(route('mailbox.index'))
        ->assertRedirect(route('mailbox.settings.index'));
});

it('shows an empty state instead of a 404 to a user without a mailbox who manages none', function (): void {
    Gate::define('accessAnyMailbox', fn (): bool => true);

    mailbox(['active' => false]);

    $this->actingAs(user())
        ->get(route('mailbox.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Empty')
            ->where('title', 'No mailbox has been set up for you yet')
            ->where('hint', 'Ask an administrator to give you access to a mailbox.'));
});

it('shows the empty state in Dutch', function (): void {
    Gate::define('accessAnyMailbox', fn (): bool => true);
    app()->setLocale('nl');

    $this->actingAs(user())
        ->get(route('mailbox.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('title', 'Er is nog geen mailbox voor je ingesteld'));
});

it('renders the empty state with the configured page name', function (): void {
    Gate::define('accessAnyMailbox', fn (): bool => true);
    config()->set('vue-mailbox.pages.empty', 'Mailbox/Empty');

    $this->actingAs(user())
        ->get(route('mailbox.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Mailbox/Empty'));
});

it('returns to the folder when the message vanished on the server', function (): void {
    Exceptions::fake();
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $message = storedMessage($folder, 1, ['body_fetched_at' => null]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $folder]))
        ->assertSessionHas('error', __('mailbox::mailbox.message_gone'));

    expect(MailboxMessage::query()
        ->whereKey($message->id)
        ->exists())->toBeFalse();
    Exceptions::assertNothingReported();
    Bus::assertDispatched(SyncFolderJob::class, fn (SyncFolderJob $job): bool => $job->folderId === $folder->id);
});

it('answers 404 for attachments of a vanished message', function (): void {
    Exceptions::fake();
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.attachments.download', [
            $mailbox,
            $message,
            0,
        ]))
        ->assertNotFound();

    Exceptions::assertNothingReported();
});

it('strips path separators from attachment filenames', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);
    $raw = implode("\r\n", [
        'From: sender@example.org',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary=XX',
        '',
        '--XX',
        'Content-Type: text/plain',
        '',
        'See attachment',
        '--XX',
        'Content-Type: application/pdf',
        'Content-Disposition: attachment; filename="../evil/report.pdf"',
        '',
        'PDF',
        '--XX--',
        '',
    ]);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], $raw),
    ]);

    $response = $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.attachments.download', [
            $mailbox,
            $message,
            0,
        ]))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('evil-report.pdf')
        ->not->toContain('/')
        ->not->toContain('\\')
        ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream');
});

it('replies to the Reply-To address and leaves it out of the copies', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'cc' => [['email' => 'colleague@example.org', 'name' => '']],
        'reply_to' => [['email' => 'support@example.org', 'name' => 'Support']],
        'to' => [
            [
                'email' => 'team@example.com',
                'name' => '',
            ],
            [
                'email' => 'support@example.org',
                'name' => '',
            ],
        ],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $message->id,
            'mode' => 'reply_all',
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.defaults.to', ['support@example.org'])
            ->where('form.defaults.cc', ['colleague@example.org']));
});

it('passes the app origin for the image policy of the message frame', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('imageOrigin', 'http://localhost'));
});

it('labels access rows with the accessor names', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);
    $manager->update(['name' => 'Ada Manager']);

    $this->actingAs($manager)
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('accessTable', fn (mixed $table): bool => str_contains((string) json_encode($table), 'User: Ada Manager')));
});

it('fetches the folder list on the first visit instead of answering 404', function (): void {
    Bus::fake([SyncFolderJob::class, SyncMailboxJob::class]);
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', $mailbox))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.role', 'inbox')
            ->where('syncing', null));

    Bus::assertDispatched(SyncFolderJob::class);
});

it('shows a synchronising state when the folder list cannot be fetched yet', function (): void {
    Bus::fake([SyncFolderJob::class, SyncMailboxJob::class]);
    $mailbox = mailbox();
    $this->connector->failImap('Connection refused');

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', $mailbox))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Index')
            ->where('folder', null)
            ->where('table', null)
            ->where('syncing', fn (string $message): bool => $message !== ''));

    Bus::assertDispatched(SyncMailboxJob::class);
});

it('replies to the original recipients when the message was sent by the mailbox itself', function (string $mode, array $to, array $cc): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox, 'Sent', FolderRole::Sent), 1, [
        'cc' => [
            [
                'email' => 'copy@example.org',
                'name' => '',
            ],
            [
                'email' => 'TEAM@example.com',
                'name' => '',
            ],
        ],
        'from_email' => 'Team@Example.com',
        'to' => [
            [
                'email' => 'customer@example.org',
                'name' => '',
            ],
            [
                'email' => 'second@example.org',
                'name' => '',
            ],
            [
                'email' => 'Customer@Example.org',
                'name' => '',
            ],
        ],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $message->id,
            'mode' => $mode,
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.defaults.to', $to)
            ->where('form.defaults.cc', $cc));
})->with([
    'reply' => [
        'reply',
        [
            'customer@example.org',
            'second@example.org',
        ],
        [],
    ],
    'reply all' => [
        'reply_all',
        [
            'customer@example.org',
            'second@example.org',
        ],
        ['copy@example.org'],
    ],
]);

it('formats dates for display in the display timezone', function (): void {
    config()->set('app.timezone', 'UTC');
    config()->set('mailbox.display_timezone', 'Europe/Amsterdam');
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $message = storedMessage($folder, 1, [
        'body_text' => 'Hi',
        'sent_at' => Carbon::parse('2026-10-01 22:30:00', 'UTC'),
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $message->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.defaults.body', fn (string $body): bool => str_contains($body, '02-10-2026 00:30')));

    expect((string) json_encode(MessagesTable::build($folder)))->toContain('02-10-2026');
});

it('labels folders by role and keeps custom folder names', function (): void {
    $mailbox = mailbox();
    $inbox = folder($mailbox, 'INBOX');
    folder($mailbox, 'INBOX.Sent Items', FolderRole::Sent);
    folder($mailbox, 'Projecten', FolderRole::Custom);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, $inbox]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('folder.label', 'Inbox')
            ->where('folders', fn (mixed $folders): bool => collect($folders)->pluck('label')->all() === [
                'Inbox',
                'Sent',
                'Projecten',
            ]));
});

it('serves inline svg images locked down and other inline types plainly', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);
    $raw = (new Email)
        ->from('sender@example.org')
        ->to('team@example.com')
        ->html('<img src="cid:icon"><img src="cid:logo">')
        ->embed('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'icon', 'image/svg+xml')
        ->embed('PNG', 'logo', 'image/png')
        ->toString();
    $remote = new FakeMessage(1, [], $raw);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        $remote,
    ]);
    $contentIds = array_map(
        fn (Attachment $attachment): string => trim((string) $attachment->contentId(), '<>'),
        $remote->attachments(),
    );
    $inline = app(InlineUrls::class)->for($message);

    $svg = $this->get($inline($contentIds[0]))->assertOk();

    expect($svg->headers->get('Content-Type'))->toStartWith('image/svg+xml')
        ->and($svg->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->and($svg->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $png = $this->get($inline($contentIds[1]))->assertOk();

    expect($png->headers->get('Content-Type'))->toStartWith('image/png')
        ->and($png->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('returns to the folder of the source message after replying', function (): void {
    $mailbox = mailbox([
        'append_sent' => false,
    ]);
    folder($mailbox);
    $archive = folder($mailbox, 'Archive', FolderRole::Archive);
    $source = storedMessage($archive, 1, [
        'body_html' => null,
    ]);
    $this->connector->remote($mailbox)->addFolder(new RecordingFolder('Archive'));

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>Answer</p>',
            'reply_to_message' => $source->id,
            'subject' => 'Re: x',
            'to' => [
                'sender@example.org',
            ],
        ])
        ->assertRedirect(route('mailbox.folders.show', [
            $mailbox,
            $archive,
        ]))
        ->assertSessionHas('success');
});
