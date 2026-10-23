<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\MailboxContact;

it('lets writers manage the address book and readers only look', function (): void {
    $mailbox = mailbox();
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->post(route('mailbox.contacts.store', $mailbox), [
            'email' => 'Anna@Example.org',
            'name' => 'Anna',
            'organisation' => 'Acme',
        ])
        ->assertRedirect(route('mailbox.contacts.index', $mailbox));

    $contact = MailboxContact::query()
        ->sole();

    expect($contact->email)->toBe('anna@example.org');

    $this->actingAs($writer)
        ->post(route('mailbox.contacts.store', $mailbox), [
            'email' => 'ANNA@example.org',
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($writer)
        ->put(route('mailbox.contacts.update', [$mailbox, $contact]), [
            'email' => 'anna@example.org',
            'name' => 'Anna B',
        ])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->name)->toBe('Anna B');

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.contacts.index', $mailbox))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('mailbox/Contacts/Index')
            ->where('createUrl', null)
            ->where('table.rowActions', [])
            ->where('table.bulkActions', []));

    $this->actingAs($writer)
        ->delete(route('mailbox.contacts.destroy', [$mailbox, $contact]))
        ->assertRedirect();

    expect(MailboxContact::query()
        ->count())->toBe(0);
});

it('does not resolve a contact of another mailbox', function (): void {
    $mailbox = mailbox();
    $foreign = MailboxContact::query()
        ->create([
            'email' => 'x@example.org',
            'mailbox_id' => mailbox(['name' => 'Other'])->id,
        ]);

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->get(route('mailbox.contacts.edit', [$mailbox, $foreign]))
        ->assertNotFound();
});

it('adds the sender of a message to the address book once', function (): void {
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1, [
        'from_email' => 'Sender@Example.org',
        'from_name' => 'Sender',
    ]);
    $writer = member($mailbox, AccessLevel::Write);

    $this->actingAs($writer)
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('contactUrl', route('mailbox.messages.contact', [$mailbox, $message])));

    $this->actingAs($writer)->post(route('mailbox.messages.contact', [$mailbox, $message]))->assertRedirect();
    $this->actingAs($writer)->post(route('mailbox.messages.contact', [$mailbox, $message]))->assertRedirect();

    expect(MailboxContact::query()
        ->get([
            'email',
            'name',
        ])
        ->toArray())->toBe([
            [
                'email' => 'sender@example.org',
                'name' => 'Sender',
            ],
        ]);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.messages.show', [$mailbox, $message]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('contactUrl', null));
});

it('rejects arrays in the contact fields', function (string $field): void {
    $mailbox = mailbox();

    $this->actingAs(member($mailbox, AccessLevel::Write))
        ->post(route('mailbox.contacts.store', $mailbox), [
            'email' => 'anna@example.org',
            ...[
                $field => [
                    'x',
                ],
            ],
        ])
        ->assertSessionHasErrors($field);

    expect(MailboxContact::query()
        ->count())->toBe(0);
})->with([
    'name',
    'organisation',
    'notes',
    'email',
]);
