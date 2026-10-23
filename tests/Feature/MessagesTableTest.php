<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Enums\FolderRole;
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

    expect(MailboxMessage::query()
        ->count())->toBe($remaining);
})->with([
    'reader' => [
        AccessLevel::Read,
        403,
        1,
    ],
    'writer' => [
        AccessLevel::Write,
        200,
        0,
    ],
]);

it('moves selected messages to a chosen folder of the same mailbox', function (): void {
    Bus::fake();
    $mailbox = mailbox();
    $folder = folder($mailbox);
    $target = folder($mailbox, 'Projects', FolderRole::Custom);
    $foreign = folder(mailbox(['name' => 'Other']), 'Elsewhere', FolderRole::Custom);
    $message = storedMessage($folder, 1);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "Subject: Hi\r\n\r\nBody"),
    ]);
    $writer = member($mailbox, AccessLevel::Write);

    $url = SignedTableUrl::make('table.bulk-action', MessagesTable::class, ['folder' => $folder->id], [
        'action' => base64_encode('4'),
    ]);

    foreach ([
        $foreign->id,
        $folder->id,
        'nonsense',
    ] as $invalid) {
        $this->actingAs($writer)
            ->postJson($url, [
                'choice' => (string) $invalid,
                'ids' => [$message->id],
            ])
            ->assertStatus(422);
    }

    $this->actingAs($writer)
        ->postJson($url, [
            'choice' => (string) $target->id,
            'ids' => [$message->id],
        ])
        ->assertOk();

    expect(MailboxMessage::query()
        ->count())->toBe(0)
        ->and($this->connector->remote($mailbox)->folder('INBOX')->operations)->toBe([
            [
                'target' => 'Projects',
                'type' => 'move',
                'uids' => [1],
            ],
        ]);
});

it('marks unread rows bold and polls for new mail', function (): void {
    $this->actingAs(user());
    $folder = folder(mailbox());
    storedMessage($folder, 1, ['is_seen' => false]);
    storedMessage($folder, 2, ['is_seen' => true]);

    $table = MessagesTable::build($folder)->toArray();
    $classes = collect($table['data'])->mapWithKeys(fn (array $row): array => [$row['uid'] => $row['__class'] ?? null])->all();

    expect($table['poll'])->toBe(30)
        ->and($classes)->toBe([
            2 => null,
            1 => 'font-semibold',
        ]);
});

it('queries the folders of the mailbox once per page view', function (): void {
    $mailbox = mailbox();
    $folder = folder($mailbox);
    folder($mailbox, 'Projects', FolderRole::Custom);
    $writer = member($mailbox, AccessLevel::Write);
    $queries = 0;

    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        if (str_contains($query->sql, 'from "mailbox_folders"') && ! str_contains($query->sql, '"id" = ?')) {
            $queries++;
        }
    });

    $this->actingAs($writer)
        ->get(route('mailbox.folders.show', [
            $mailbox,
            $folder,
        ]))
        ->assertOk();

    expect($queries)->toBe(1);
});

it('marks messages with attachments with a paperclip in the subject instead of a column', function (): void {
    $this->actingAs(user());
    $folder = folder(mailbox());
    storedMessage($folder, 1, [
        'has_attachments' => true,
        'subject' => 'Invoice <b>May</b>',
    ]);
    storedMessage($folder, 2, [
        'has_attachments' => false,
        'subject' => 'Plain',
    ]);

    $table = MessagesTable::build($folder)->toArray();
    $subjects = collect($table['data'])->mapWithKeys(fn (array $row): array => [$row['uid'] => (string) $row['subject']])->all();

    expect(collect($table['columns'])->pluck('key')->all())->not->toContain('has_attachments')
        ->and($subjects[1])->toStartWith('Invoice &lt;b&gt;May&lt;/b&gt;')
        ->and($subjects[1])->toContain('<svg')
        ->and($subjects[1])->toContain('aria-label="Has attachments"')
        ->and($subjects[2])->toBe('Plain');
});
