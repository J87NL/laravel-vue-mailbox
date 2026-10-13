<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Tables\MessagesTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

it('only lets writers run bulk actions on the message table', function (AccessLevel $level, int $status, int $remaining): void {
    Bus::fake();
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $message = storedMessage($folder, 1);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Hi\r\n\r\nBody"),
    ]);

    $url = SignedTableUrl::make('table.bulk-action', MessagesTable::class, ['folder' => $folder->id], [
        'action' => base64_encode('0'),
    ]);

    $this->actingAs(member($mailbox, $level))
        ->postJson($url, ['ids' => [$message->id]])
        ->assertStatus($status);

    expect(MailboxMessage::query()->count())->toBe($remaining);
})->with([
    'reader' => [AccessLevel::Read, 403, 1],
    'writer' => [AccessLevel::Write, 200, 0],
]);
