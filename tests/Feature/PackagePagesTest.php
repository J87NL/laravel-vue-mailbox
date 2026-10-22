<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use Symfony\Component\Finder\Finder;

beforeEach(function (): void {
    Bus::fake([SyncFolderJob::class]);
});

/**
 * @return array<string, string>
 */
function renderedPages(): array
{
    $mailbox = mailbox();
    $message = storedMessage(folder($mailbox), 1);
    $user = member($mailbox, AccessLevel::Manage);

    $routes = [
        'compose' => route('mailbox.compose.create', $mailbox),
        'contacts_edit' => route('mailbox.contacts.create', $mailbox),
        'contacts_index' => route('mailbox.contacts.index', $mailbox),
        'index' => route('mailbox.folders.show', $mailbox),
        'settings_edit' => route('mailbox.settings.edit', $mailbox),
        'settings_index' => route('mailbox.settings.index'),
        'show' => route('mailbox.messages.show', [$mailbox, $message]),
    ];

    $components = [];

    foreach ($routes as $key => $url) {
        test()->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($key, &$components): AssertableInertia {
                $components[$key] = $page->toArray()['component'];

                return $page;
            });
    }

    return $components;
}

it('renders the package-owned pages by default', function (): void {
    expect(renderedPages())->toBe([
        'compose' => 'mailbox/Compose',
        'contacts_edit' => 'mailbox/Contacts/Edit',
        'contacts_index' => 'mailbox/Contacts/Index',
        'index' => 'mailbox/Index',
        'settings_edit' => 'mailbox/Settings/Edit',
        'settings_index' => 'mailbox/Settings/Index',
        'show' => 'mailbox/Show',
    ]);
});

it('finds every default page in the package under its name without the mailbox prefix', function (string $name): void {
    expect($name)->toStartWith('mailbox/')
        ->and(File::exists(sprintf('%s/../../resources/js/pages/%s.vue', __DIR__, substr($name, strlen('mailbox/')))))->toBeTrue();
})->with(fn (): array => array_values((require __DIR__.'/../../config/vue-mailbox.php')['pages']));

it('keeps rendering the pages of an app that copied and renamed them', function (): void {
    config()->set('vue-mailbox.pages', [
        'compose' => 'Mailbox/Compose',
        'contacts_edit' => 'Mailbox/Contacts/Edit',
        'contacts_index' => 'Mailbox/Contacts/Index',
        'index' => 'Mailbox/Index',
        'settings_edit' => 'settings/mailboxes/Edit',
        'settings_index' => 'settings/mailboxes/Index',
        'show' => 'Mailbox/Show',
    ]);

    expect(renderedPages())->toBe([
        'compose' => 'Mailbox/Compose',
        'contacts_edit' => 'Mailbox/Contacts/Edit',
        'contacts_index' => 'Mailbox/Contacts/Index',
        'index' => 'Mailbox/Index',
        'settings_edit' => 'settings/mailboxes/Edit',
        'settings_index' => 'settings/mailboxes/Index',
        'show' => 'Mailbox/Show',
    ]);
});

it('only imports through aliases from the pages, so a published copy still builds', function (string $path): void {
    expect(File::get($path))->not->toMatch('/from\s+[\'"]\.{1,2}\//');
})->with(fn (): array => array_values(array_map(
    fn (SplFileInfo $file): string => $file->getPathname(),
    iterator_to_array(Finder::create()->files()->in(__DIR__.'/../../resources/js/pages')->name('*.vue')),
)));
