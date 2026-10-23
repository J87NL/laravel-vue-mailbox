<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use J87NL\VueMailbox\VueMailboxServiceProvider;
use Symfony\Component\Finder\Finder;

beforeEach(function (): void {
    Bus::fake([SyncFolderJob::class]);
});

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

it('fills the page names an app left out with the package defaults', function (): void {
    config()->set('vue-mailbox.pages', [
        'show' => 'Inbox/Message',
    ]);

    $this->app->register(VueMailboxServiceProvider::class, true);

    expect(renderedPages())->toBe([
        'compose' => 'mailbox/Compose',
        'contacts_edit' => 'mailbox/Contacts/Edit',
        'contacts_index' => 'mailbox/Contacts/Index',
        'index' => 'mailbox/Index',
        'settings_edit' => 'mailbox/Settings/Edit',
        'settings_index' => 'mailbox/Settings/Index',
        'show' => 'Inbox/Message',
    ]);
});

it('only imports through aliases from the pages, so a published copy still builds', function (string $path): void {
    expect(File::get($path))->not->toMatch('/(from\s+|import\s*\(?\s*)[\'"]\.{1,2}\//');
})->with(fn (): array => array_values(array_map(
    fn (SplFileInfo $file): string => $file->getPathname(),
    iterator_to_array(Finder::create()->files()->in(__DIR__.'/../../resources/js/pages')->name('*.vue')),
)));

it('fills the empty state page name for an app whose published pages predate it', function (): void {
    config()->set('vue-mailbox.pages', [
        'index' => 'mailbox/Index',
    ]);

    $this->app->register(VueMailboxServiceProvider::class, true);

    expect(config('vue-mailbox.pages.empty'))->toBe('mailbox/Empty');
});
