<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Support\Facades\Bus;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\VueMailbox\Tests\Support\User;

const LEVELS = [
    'guest' => 0,
    'none' => 1,
    'read' => 2,
    'write' => 3,
    'manage' => 4,
    'admin' => 5,
];

/**
 * @return array{0: string, 1: string}
 */
function endpoint(string $name, Mailbox $mailbox): array
{
    $folder = $mailbox->folders()->firstOrFail();
    $message = $mailbox->messages()->firstOrFail();
    $access = MailboxAccess::query()
        ->where('mailbox_id', $mailbox->id)->firstOrFail();

    return match ($name) {
        'folders.show' => ['get', route('mailbox.folders.show', [$mailbox, $folder])],
        'messages.show' => ['get', route('mailbox.messages.show', [$mailbox, $message])],
        'attachments.download' => ['get', route('mailbox.attachments.download', [$mailbox, $message, 0])],
        'folders.store' => ['post', route('mailbox.folders.store', $mailbox)],
        'messages.actions' => ['post', route('mailbox.messages.actions', $mailbox)],
        'compose.create' => ['get', route('mailbox.compose.create', $mailbox)],
        'compose.store' => ['post', route('mailbox.compose.store', $mailbox)],
        'settings.edit' => ['get', route('mailbox.settings.edit', $mailbox)],
        'settings.update' => ['put', route('mailbox.settings.update', $mailbox)],
        'settings.access.store' => ['post', route('mailbox.settings.access.store', $mailbox)],
        'settings.access.destroy' => ['delete', route('mailbox.settings.access.destroy', [$mailbox, $access])],
        'settings.create' => ['get', route('mailbox.settings.create')],
        'settings.store' => ['post', route('mailbox.settings.store')],
        'settings.destroy' => ['delete', route('mailbox.settings.destroy', $mailbox)],
    };
}

function actor(string $level, Mailbox $mailbox): ?User
{
    return match ($level) {
        'guest' => null,
        'none' => user(),
        'admin' => user(['email' => 'admin@example.com']),
        default => member($mailbox, AccessLevel::from($level)),
    };
}

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function routeMatrix(): array
{
    $routes = [
        'folders.show' => 'read',
        'messages.show' => 'read',
        'attachments.download' => 'read',
        'folders.store' => 'write',
        'messages.actions' => 'write',
        'compose.create' => 'write',
        'compose.store' => 'write',
        'settings.edit' => 'manage',
        'settings.update' => 'manage',
        'settings.access.store' => 'manage',
        'settings.access.destroy' => 'manage',
        'settings.create' => 'admin',
        'settings.store' => 'admin',
        'settings.destroy' => 'admin',
    ];

    $cases = [];

    foreach ($routes as $route => $minimum) {
        foreach (array_keys(LEVELS) as $level) {
            $cases[sprintf('%s as %s', $route, $level)] = [$route, $minimum, $level];
        }
    }

    return $cases;
}

it('allows each route group only from its minimum level', function (string $route, string $minimum, string $level): void {
    Bus::fake();
    config()->set('mailbox.access.admins', ['admin@example.com']);

    $mailbox = mailbox();
    storedMessage(folder($mailbox), 1);
    member($mailbox, AccessLevel::Manage);
    $this->connector->remote($mailbox)->folder('INBOX')->setMessages([
        new FakeMessage(1, [], "From: a@example.org\r\nSubject: Hi\r\n\r\nBody"),
    ]);

    $user = actor($level, $mailbox);
    [$method, $url] = endpoint($route, $mailbox);

    $response = is_null($user)
        ? $this->{$method}($url)
        : $this->actingAs($user)->{$method}($url);

    if ($level === 'guest') {
        $response->assertRedirect(route('login'));

        return;
    }

    if (LEVELS[$level] < LEVELS[$minimum]) {
        $response->assertForbidden();

        return;
    }

    expect($response->status())->not->toBeIn([401, 403, 500]);
})->with(routeMatrix());

it('does not resolve a message through another mailbox', function (): void {
    $mine = mailbox();
    $other = mailbox(['name' => 'Other']);
    $message = storedMessage(folder($other), 1);

    $this->actingAs(member($mine, AccessLevel::Manage))
        ->get(route('mailbox.messages.show', [$mine, $message]))
        ->assertNotFound();
});
