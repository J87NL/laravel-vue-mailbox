<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Inertia\Response;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\MailboxContact;
use J87NL\VueMailbox\Support\InlineUrls;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

function forwardSource(): string
{
    return implode("\r\n", [
        'From: sender@example.org',
        'Subject: Offer',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary=XX',
        '',
        '--XX',
        'Content-Type: text/plain',
        '',
        'See attachments',
        '--XX',
        'Content-Type: application/pdf',
        'Content-Disposition: attachment; filename="offer.pdf"',
        '',
        'PDF-OFFER',
        '--XX',
        'Content-Type: text/plain',
        'Content-Disposition: attachment; filename="terms.txt"',
        '',
        'TERMS',
        '--XX--',
        '',
    ]);
}

it('suggests addresses only to writers, filtered and limited', function (): void {
    $mailbox = mailbox();
    MailboxContact::query()
        ->create([
            'email' => 'anna@example.org',
            'mailbox_id' => $mailbox->id,
            'name' => 'Anna',
        ]);
    storedMessage(folder($mailbox), 1, [
        'from_email' => 'bob@example.org',
        'from_name' => 'Bob',
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->getJson(route('mailbox.addresses', [
            $mailbox,
            'q' => 'an',
        ]))
        ->assertForbidden();

    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->getJson(route('mailbox.addresses', [
            $mailbox,
            'q' => 'ann',
        ]))
        ->assertOk()
        ->assertExactJson([
            [
                'label' => 'Anna <anna@example.org>',
                'value' => 'anna@example.org',
            ],
        ]);

    $this->actingAs($writer)
        ->getJson(route('mailbox.addresses', $mailbox))
        ->assertOk()
        ->assertJsonCount(2);
});

it('offers the original attachments when forwarding and sends the kept ones', function (): void {
    Bus::fake();
    $mailbox = mailbox(['append_sent' => false]);
    $source = storedMessage(folder($mailbox), 1, [
        'attachments' => [
            [
                'content_id' => null,
                'content_type' => 'application/pdf',
                'filename' => 'offer.pdf',
                'inline' => false,
            ],
            [
                'content_id' => null,
                'content_type' => 'text/plain',
                'filename' => 'terms.txt',
                'inline' => false,
            ],
        ],
    ]);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], forwardSource()),
    ]);
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $source->id,
            'mode' => 'forward',
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.defaults.forward_message', $source->id)
            ->where('form.fields', fn (mixed $fields): bool => collect($fields)->firstWhere('name', 'attachments')['currentFiles'][1]['name'] === 'terms.txt'));

    $this->actingAs($writer)
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>See below</p>',
            'forward_message' => $source->id,
            'remove_attachments' => ['1'],
            'subject' => 'Fwd: Offer',
            'to' => ['colleague@example.org'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    /** @var Email $sent */
    $sent = $this->connector->transport->sent[0];

    expect(array_map(fn (DataPart $part): string => (string) $part->getFilename(), $sent->getAttachments()))->toBe(['offer.pdf'])
        ->and($sent->getAttachments()[0]->getBody())->toBe('PDF-OFFER')
        ->and(array_map(fn (Address $address): string => $address->getAddress(), $sent->getTo()))->toBe(['colleague@example.org']);
});

it('rejects removal keys that are not attachments of the forwarded message', function (): void {
    $mailbox = mailbox();
    $source = storedMessage(folder($mailbox), 1, [
        'attachments' => [
            [
                'content_id' => null,
                'content_type' => 'application/pdf',
                'filename' => 'offer.pdf',
                'inline' => false,
            ],
        ],
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>x</p>',
            'forward_message' => $source->id,
            'remove_attachments' => ['7'],
            'subject' => 'Fwd',
            'to' => ['a@example.org'],
        ])
        ->assertSessionHasErrors('remove_attachments.0');

    expect($this->connector->transport->sent)->toBe([]);
});

it('puts the mailbox signature above the quote', function (string $mode): void {
    $mailbox = mailbox(['signature' => '<p>Regards, Team</p>']);
    $source = storedMessage(folder($mailbox), 1, [
        'body_html' => '<p>Original text</p>',
    ]);
    $parameters = $mode === 'new' ? [$mailbox] : [
        $mailbox,
        'reply' => $source->id,
        'mode' => $mode,
    ];

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', $parameters))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('form.defaults.body', function (string $body) use ($mode): bool {
                $signature = strpos($body, '<div class="mailbox-signature"><p>Regards, Team</p></div>');

                if ($mode === 'new') {
                    return $signature === strlen('<p></p>');
                }

                return $signature !== false && $signature < (int) strpos($body, 'Original text');
            }));
})->with([
    'new',
    'reply',
    'forward',
]);

it('shares the mailbox translations with every page', function (): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [$mailbox, folder($mailbox)]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('vue_mailbox_translations.reply', 'Reply')
            ->where('vue_mailbox_translations.contacts', 'Address book'));
});

it('does not share the mailbox translations outside the mailbox routes', function (): void {
    Route::middleware('web')->get('elsewhere', fn (): Response => Inertia\Inertia::render('Elsewhere'));

    $this->actingAs(user())
        ->get('elsewhere')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Elsewhere')
            ->missing('vue_mailbox_translations'));
});

it('reloads pruned attachment metadata before forwarding the originals', function (): void {
    Bus::fake();
    $mailbox = mailbox([
        'append_sent' => false,
    ]);
    $source = storedMessage(folder($mailbox), 1, [
        'attachments' => null,
        'body_fetched_at' => null,
        'body_html' => null,
        'has_attachments' => true,
    ]);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], forwardSource()),
    ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => '<p>See below</p>',
            'forward_message' => $source->id,
            'remove_attachments' => [
                '0',
            ],
            'subject' => 'Fwd: Offer',
            'to' => [
                'colleague@example.org',
            ],
        ])
        ->assertSessionHasNoErrors();

    $sent = $this->connector->transport->sent[0];

    expect(array_map(fn (DataPart $part): string => (string) $part->getFilename(), $sent->getAttachments()))->toBe([
        'terms.txt',
    ]);
});

it('forwards quoted inline images as embedded parts and drops removed or foreign ones', function (): void {
    Bus::fake();
    $mailbox = mailbox([
        'append_sent' => false,
    ]);
    $inbox = folder($mailbox);
    $raw = (new Email)
        ->from('Sender <sender@example.org>')
        ->to('team@example.com')
        ->subject('Newsletter')
        ->html('<p>News</p><img src="cid:logo"><img src="cid:banner">')
        ->embed('LOGO', 'logo', 'image/png')
        ->embed('BANNER', 'banner', 'image/png')
        ->toString();
    $remote = new FakeMessage(1, [], $raw);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        $remote,
    ]);
    $source = storedMessage($inbox, 1, [
        'body_html' => $remote->html(),
    ]);
    $other = storedMessage($inbox, 2);
    $writer = member($mailbox, AccessLevel::Write);
    $body = '';

    $this->actingAs($writer)
        ->get(route('mailbox.compose.create', [
            $mailbox,
            'reply' => $source->id,
            'mode' => 'forward',
        ]))
        ->assertInertia(function (AssertableInertia $page) use (&$body): AssertableInertia {
            $body = (string) $page->toArray()['props']['form']['defaults']['body'];

            return $page;
        });

    preg_match_all('/<img[^>]*>/', $body, $images);
    $foreign = app(InlineUrls::class)->for($other)('banner');

    expect($body)->toContain('Forwarded message')
        ->toContain('data-mailbox-cid=')
        ->and($images[0])->toHaveCount(2);

    $this->actingAs($writer)
        ->post(route('mailbox.compose.store', $mailbox), [
            'body' => implode('', [
                '<p>FYI</p>',
                $images[0][0],
                sprintf('<img src="%s">', e($foreign)),
            ]),
            'forward_message' => $source->id,
            'subject' => 'Fwd: Newsletter',
            'to' => [
                'colleague@example.org',
            ],
        ])
        ->assertSessionHasNoErrors();

    $sent = $this->connector->transport->sent[0];
    $inline = array_values(array_filter($sent->getAttachments(), fn (DataPart $part): bool => $part->getDisposition() === 'inline'));

    expect($inline)->toHaveCount(1)
        ->and($inline[0]->getBody())->toBe('LOGO')
        ->and((string) $sent->getHtmlBody())->toContain(sprintf('cid:%s', $inline[0]->getName()))
        ->not->toContain('/mailbox/inline/');
});
