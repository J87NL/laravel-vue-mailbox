<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;

it('carries the search, filter, sort and page of the message list to the message and back', function (): void {
    Bus::fake();
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $message = storedMessage($folder, 1);
    $listQuery = [
        'messages_filter' => ['search' => 'invoice'],
        'messages_page' => '2',
        'messages_sort' => 'sent_at',
    ];

    $this->actingAs(member($mailbox, AccessLevel::Write));

    $this->get(route('mailbox.messages.show', [$mailbox, $message, ...$listQuery, 'images' => '1', 'other' => 'x']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('backUrl', route('mailbox.folders.show', [$mailbox, $folder, ...$listQuery]))
            ->where('composeUrl', route('mailbox.compose.create', [$mailbox, ...$listQuery]))
            ->where('actionsUrl', route('mailbox.messages.actions', [$mailbox, ...$listQuery])));

    $this->get(route('mailbox.compose.create', [$mailbox, ...$listQuery, 'reply' => $message->id, 'mode' => 'reply']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('backUrl', route('mailbox.messages.show', [$mailbox, $message, ...$listQuery]))
            ->where('form.action', route('mailbox.compose.store', [$mailbox, ...$listQuery])));

    $this->post(route('mailbox.compose.store', [$mailbox, ...$listQuery]), [
        'body' => '<p>Thanks</p>',
        'reply_to_message' => $message->id,
        'subject' => 'Re: Hello',
        'to' => ['sender@example.org'],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $folder, ...$listQuery]));

    $this->post(route('mailbox.messages.actions', [$mailbox, ...$listQuery]), [
        'action' => 'archive',
        'ids' => [$message->id],
    ])
        ->assertRedirect(route('mailbox.folders.show', [$mailbox, $folder, ...$listQuery]));
});

it('links each row of the message list with the current list query', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $message = storedMessage($folder, 1);

    $this->actingAs(member($mailbox, AccessLevel::Read))
        ->get(route('mailbox.folders.show', [
            $mailbox,
            $folder,
            'messages_sort' => '-sent_at',
            'unrelated' => 'x',
        ]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('table.rowLinks.0', route('mailbox.messages.show', [
                $mailbox,
                $message,
                'messages_sort' => '-sent_at',
            ])));
});
