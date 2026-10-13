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

it('publishes the default pages on request', function (): void {
    $this->artisan('mailbox:install', ['--pages' => true])->assertSuccessful();

    expect(File::exists(resource_path('js/pages/Mailbox/Index.vue')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Mailbox/Contacts/Index.vue')))->toBeTrue();
});
