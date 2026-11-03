<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\MailboxTemplate;

it('lets a manager create, change and delete a template of the mailbox', function (): void {
    $mailbox = mailbox();
    $manager = member($mailbox, AccessLevel::Manage);

    $this->actingAs($manager)
        ->post(route('mailbox.settings.templates.store', $mailbox), [
            'body' => '<p>Beste {voornaam},</p><script>alert(1)</script>',
            'name' => 'Bevestiging',
            'subject' => '',
        ])
        ->assertRedirect(route('mailbox.settings.edit', $mailbox));

    $template = MailboxTemplate::query()
        ->sole();

    expect($template->only([
        'body',
        'mailbox_id',
        'subject',
    ]))->toBe([
        'body' => '<p>Beste {voornaam},</p>',
        'mailbox_id' => $mailbox->id,
        'subject' => null,
    ]);

    $this->actingAs($manager)
        ->put(route('mailbox.settings.templates.update', [$mailbox, $template]), [
            'body' => '<p>Hoi</p>',
            'name' => 'Kort',
            'subject' => 'Re: {onderwerp}',
        ])
        ->assertRedirect(route('mailbox.settings.edit', $mailbox));

    expect($template->refresh()->only([
        'name',
        'subject',
    ]))->toBe([
        'name' => 'Kort',
        'subject' => 'Re: {onderwerp}',
    ]);

    $this->actingAs($manager)
        ->delete(route('mailbox.settings.templates.destroy', [$mailbox, $template]))
        ->assertRedirect(route('mailbox.settings.edit', $mailbox));

    expect(MailboxTemplate::query()
        ->count())->toBe(0);
});

it('keeps the templates of another mailbox out of reach', function (): void {
    $mailbox = mailbox();
    $foreign = mailbox(['name' => 'Other'])->templates()
        ->create([
            'body' => '<p>Secret</p>',
            'name' => 'Other',
        ]);

    $this->actingAs(member($mailbox, AccessLevel::Manage))
        ->delete(route('mailbox.settings.templates.destroy', [$mailbox, $foreign]))
        ->assertNotFound();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->getJson(route('mailbox.templates.render', [$mailbox, $foreign]))
        ->assertNotFound();

    expect(MailboxTemplate::query()
        ->whereKey($foreign->id)
        ->exists())->toBeTrue();
});

it('offers the templates of the mailbox in compose with a render url for the reply', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'body_html' => '<p>Question</p>',
        'body_loaded_at' => now(),
        'from_email' => 'piet@example.org',
        'from_name' => 'Piet de Vries',
    ]);
    $template = $mailbox->templates()
        ->create([
            'body' => '<p>Beste {voornaam}</p>',
            'name' => 'Antwoord',
        ]);
    mailbox(['name' => 'Other'])->templates()
        ->create([
            'body' => '<p>Other</p>',
            'name' => 'Other',
        ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.compose.create', [$mailbox, 'reply' => $message->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('templates', 1)
            ->where('templates.0.name', 'Antwoord')
            ->where('templates.0.url', route('mailbox.templates.render', [
                'mailbox' => $mailbox,
                'reply' => $message->id,
                'template' => $template,
            ])));
});

it('renders a template with the name of the original sender when replying to them', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'from_email' => 'Piet@Example.org',
        'from_name' => 'Piet de Vries',
    ]);
    $template = $mailbox->templates()
        ->create([
            'body' => '<p>Beste {voornaam}, over {onderwerp}. {mijn_naam}, {mailbox_naam} {onbekend}</p>',
            'name' => 'Antwoord',
            'subject' => 'Re: {onderwerp}',
        ]);
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->getJson(route('mailbox.templates.render', [
            'mailbox' => $mailbox,
            'reply' => $message->id,
            'subject' => 'Offerte',
            'template' => $template,
            'to' => 'piet@example.org',
        ]))
        ->assertOk()
        ->assertExactJson([
            'body' => sprintf('<p>Beste Piet, over Offerte. %s, Team {onbekend}</p>', e($writer->name)),
            'subject' => 'Re: Offerte',
        ]);

    $this->actingAs($writer)
        ->getJson(route('mailbox.templates.render', [
            'mailbox' => $mailbox,
            'reply' => $message->id,
            'template' => $template,
            'to' => 'someone-else@example.org',
        ]))
        ->assertOk()
        ->assertJsonPath('body', sprintf('<p>Beste {voornaam}, over {onderwerp}. %s, Team {onbekend}</p>', e($writer->name)));
});

it('lists the templates and their create link on the settings page of a saved mailbox only', function (): void {
    config()->set('mailbox.access.admins', ['admin@example.com']);
    $mailbox = mailbox();
    $admin = user(['email' => 'admin@example.com']);

    $this->actingAs($admin)
        ->get(route('mailbox.settings.edit', $mailbox))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('templateCreateUrl', route('mailbox.settings.templates.create', $mailbox))
            ->has('templatesTable'));

    $this->actingAs($admin)
        ->get(route('mailbox.settings.create'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('templateCreateUrl', null)
            ->where('templatesTable', null));
});
