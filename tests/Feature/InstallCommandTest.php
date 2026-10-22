<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::put(base_path('vite.config.ts'), implode("\n", [
        "import { defineConfig } from 'vite';",
        '',
        'export default defineConfig({',
        '    plugins: [],',
        '});',
        '',
    ]));
    File::put(base_path('tsconfig.json'), implode("\n", [
        '{',
        '    "compilerOptions": {',
        '        "paths": {',
        '            "@/*": ["./resources/js/*"]',
        '        }',
        '    }',
        '}',
        '',
    ]));
});

afterEach(function (): void {
    File::delete([
        base_path('vite.config.ts'),
        base_path('tsconfig.json'),
        config_path('mailbox.php'),
        config_path('vue-mailbox.php'),
        ...File::glob(database_path('migrations/*mailbox*.php')),
    ]);
    File::deleteDirectory(resource_path('js/pages/Mailbox'));
});

it('adds the @mailbox alias and tsconfig paths once', function (): void {
    $this->artisan('mailbox:install')->assertSuccessful();
    $this->artisan('mailbox:install')->assertSuccessful();

    $vite = File::get(base_path('vite.config.ts'));
    $tsConfig = File::get(base_path('tsconfig.json'));

    expect(substr_count($vite, "'@mailbox'"))->toBe(1)
        ->and($vite)->toContain('./vendor/j87nl/laravel-vue-mailbox/resources/js')
        ->and($tsConfig)->toContain('"@mailbox/*"')
        ->and($tsConfig)->toContain('./vendor/j87nl/laravel-vue-mailbox/resources/js/*')
        ->and(File::exists(resource_path('js/pages/Mailbox/Index.vue')))->toBeFalse()
        ->and(File::exists(config_path('vue-mailbox.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*create_mailbox_contacts_table.php')))->toHaveCount(1);
});

it('keeps the package-owned page names without --pages', function (): void {
    $this->artisan('mailbox:install')->assertSuccessful();

    expect((require config_path('vue-mailbox.php'))['pages'])->toBe([
        'compose' => 'mailbox/Compose',
        'contacts_edit' => 'mailbox/Contacts/Edit',
        'contacts_index' => 'mailbox/Contacts/Index',
        'index' => 'mailbox/Index',
        'settings_edit' => 'mailbox/Settings/Edit',
        'settings_index' => 'mailbox/Settings/Index',
        'show' => 'mailbox/Show',
    ]);
});

it('publishes the default pages on request and points the config at the copies', function (): void {
    $this->artisan('mailbox:install', ['--pages' => true])->assertSuccessful();
    $this->artisan('mailbox:install', ['--pages' => true])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/Mailbox/Index.vue')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Mailbox/Contacts/Index.vue')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Mailbox/Settings/Edit.vue')))->toBeTrue()
        ->and((require config_path('vue-mailbox.php'))['pages'])->toBe([
            'compose' => 'Mailbox/Compose',
            'contacts_edit' => 'Mailbox/Contacts/Edit',
            'contacts_index' => 'Mailbox/Contacts/Index',
            'index' => 'Mailbox/Index',
            'settings_edit' => 'Mailbox/Settings/Edit',
            'settings_index' => 'Mailbox/Settings/Index',
            'show' => 'Mailbox/Show',
        ]);
});

it('leaves page names that the app chose itself alone when publishing the pages', function (): void {
    File::put(config_path('vue-mailbox.php'), implode("\n", [
        '<?php',
        '',
        'return [',
        "    'pages' => [",
        "        'index' => 'Mailbox/Index',",
        "        'settings_index' => 'settings/mailboxes/Index',",
        "        'show' => 'mailbox/Show',",
        "        'compose' => 'Inbox/mailbox/Compose',",
        '    ],',
        '];',
        '',
    ]));

    $this->artisan('mailbox:install', ['--pages' => true])->assertSuccessful();

    expect((require config_path('vue-mailbox.php'))['pages'])->toBe([
        'index' => 'Mailbox/Index',
        'settings_index' => 'settings/mailboxes/Index',
        'show' => 'Mailbox/Show',
        'compose' => 'Inbox/mailbox/Compose',
    ]);
});
