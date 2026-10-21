<?php

declare(strict_types=1);

use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Auth\Access\Gate as AccessGate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\MailboxServiceProvider;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use J87NL\VueMailbox\Tables\MailboxesTable;
use J87NL\VueMailbox\Tests\Support\User;
use J87NL\VueMailbox\VueMailboxServiceProvider;

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
    $contact = $mailbox->contacts()
        ->firstOrCreate([
            'email' => 'contact@example.org',
        ]);
    $access = MailboxAccess::query()
        ->where('mailbox_id', $mailbox->id)->firstOrFail();

    return match ($name) {
        'folders.show' => [
            'get',
            route('mailbox.folders.show', [$mailbox, $folder]),
        ],
        'messages.show' => [
            'get',
            route('mailbox.messages.show', [$mailbox, $message]),
        ],
        'attachments.download' => [
            'get',
            route('mailbox.attachments.download', [$mailbox, $message, 0]),
        ],
        'folders.store' => [
            'post',
            route('mailbox.folders.store', $mailbox),
        ],
        'messages.actions' => [
            'post',
            route('mailbox.messages.actions', $mailbox),
        ],
        'compose.create' => [
            'get',
            route('mailbox.compose.create', $mailbox),
        ],
        'compose.store' => [
            'post',
            route('mailbox.compose.store', $mailbox),
        ],
        'addresses' => [
            'get',
            route('mailbox.addresses', $mailbox),
        ],
        'contacts.index' => [
            'get',
            route('mailbox.contacts.index', $mailbox),
        ],
        'contacts.create' => [
            'get',
            route('mailbox.contacts.create', $mailbox),
        ],
        'contacts.store' => [
            'post',
            route('mailbox.contacts.store', $mailbox),
        ],
        'contacts.edit' => [
            'get',
            route('mailbox.contacts.edit', [$mailbox, $contact]),
        ],
        'contacts.update' => [
            'put',
            route('mailbox.contacts.update', [$mailbox, $contact]),
        ],
        'contacts.destroy' => [
            'delete',
            route('mailbox.contacts.destroy', [$mailbox, $contact]),
        ],
        'messages.contact' => [
            'post',
            route('mailbox.messages.contact', [$mailbox, $message]),
        ],
        'settings.index' => [
            'get',
            route('mailbox.settings.index'),
        ],
        'settings.edit' => [
            'get',
            route('mailbox.settings.edit', $mailbox),
        ],
        'settings.update' => [
            'put',
            route('mailbox.settings.update', $mailbox),
        ],
        'settings.access.store' => [
            'post',
            route('mailbox.settings.access.store', $mailbox),
        ],
        'settings.access.destroy' => [
            'delete',
            route('mailbox.settings.access.destroy', [$mailbox, $access]),
        ],
        'settings.create' => [
            'get',
            route('mailbox.settings.create'),
        ],
        'settings.store' => [
            'post',
            route('mailbox.settings.store'),
        ],
        'settings.destroy' => [
            'delete',
            route('mailbox.settings.destroy', $mailbox),
        ],
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
        'addresses' => 'write',
        'contacts.index' => 'read',
        'contacts.create' => 'write',
        'contacts.store' => 'write',
        'contacts.edit' => 'write',
        'contacts.update' => 'write',
        'contacts.destroy' => 'write',
        'messages.contact' => 'write',
        'settings.index' => 'manage',
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

    expect($response->status())->not->toBeIn([
        401,
        403,
        500,
    ]);
})->with(routeMatrix());

it('does not resolve a message through another mailbox', function (): void {
    $mine = mailbox();
    $other = mailbox(['name' => 'Other']);
    $message = storedMessage(folder($other), 1);

    $this->actingAs(member($mine, AccessLevel::Manage))
        ->get(route('mailbox.messages.show', [$mine, $message]))
        ->assertNotFound();
});

it('lets a host gate its settings link on managing any mailbox', function (): void {
    config()->set('mailbox.access.admins', ['admin@example.com']);
    $mailbox = mailbox();

    expect(Gate::forUser(member($mailbox, AccessLevel::Write))->allows('manageAnyMailbox'))->toBeFalse()
        ->and(Gate::forUser(member($mailbox, AccessLevel::Manage))->allows('manageAnyMailbox'))->toBeTrue()
        ->and(Gate::forUser(user(['email' => 'admin@example.com']))->allows('manageAnyMailbox'))->toBeTrue()
        ->and(Gate::forUser(user())->allows('manageAnyMailbox'))->toBeFalse();
});

it('only authorizes the mailboxes table for users who manage a mailbox', function (): void {
    $mailbox = mailbox();
    $request = fn (?User $user): Request => tap(Request::create('/'), fn (Request $request) => $request->setUserResolver(fn (): ?User => $user));

    expect((new MailboxesTable)->authorize($request(null)))->toBeFalse()
        ->and((new MailboxesTable)->authorize($request(member($mailbox, AccessLevel::Write))))->toBeFalse()
        ->and((new MailboxesTable)->authorize($request(member($mailbox, AccessLevel::Manage))))->toBeTrue();
});

it('follows a host definition of manageMailbox', function (): void {
    mailbox();
    Gate::define('manageMailbox', fn (User $user, Mailbox $mailbox): bool => true);

    expect(Gate::forUser(user())->allows('manageAnyMailbox'))->toBeTrue();
});

it('leaves a host definition of manageAnyMailbox alone', function (): void {
    $this->app->instance(GateContract::class, new AccessGate($this->app, fn (): ?User => null));
    Gate::clearResolvedInstance(GateContract::class);
    Gate::define('manageAnyMailbox', fn (User $user): bool => $user->email === 'host@example.com');

    VueMailboxServiceProvider::defineAbilities();

    expect(Gate::forUser(user(['email' => 'host@example.com']))->allows('manageAnyMailbox'))->toBeTrue()
        ->and(Gate::forUser(member(mailbox(), AccessLevel::Manage))->allows('manageAnyMailbox'))->toBeFalse();
});

it('defines manageAnyMailbox when the host has not', function (): void {
    $this->app->instance(GateContract::class, new AccessGate($this->app, fn (): ?User => null));
    Gate::clearResolvedInstance(GateContract::class);
    MailboxServiceProvider::defineAbilities();

    VueMailboxServiceProvider::defineAbilities();

    expect(Gate::forUser(member(mailbox(), AccessLevel::Manage))->allows('manageAnyMailbox'))->toBeTrue();
});
