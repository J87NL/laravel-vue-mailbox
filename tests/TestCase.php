<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\ServiceProvider as InertiaServiceProvider;
use J87NL\Mailbox\MailboxServiceProvider;
use J87NL\Mailbox\Testing\FakeConnector;
use J87NL\VueMailbox\Tests\Support\User;
use J87NL\VueMailbox\VueMailboxServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TranquilTools\FormBuilder\FormBuilderServiceProvider;
use TranquilTools\TableBuilder\TableBuilderServiceProvider;
use TranquilTools\VueTableBuilder\VueTableBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    public FakeConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');

        $this->connector = FakeConnector::install();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            FormBuilderServiceProvider::class,
            TableBuilderServiceProvider::class,
            VueTableBuilderServiceProvider::class,
            MailboxServiceProvider::class,
            VueMailboxServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('mailbox.user_model', User::class);
        $app['config']->set('view.paths', [
            __DIR__.'/resources/views',
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::get('login', fn (): string => 'login')->name('login');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        (include __DIR__.'/../vendor/j87nl/laravel-mailbox/database/migrations/create_mailbox_tables.php.stub')->up();
        (include __DIR__.'/../vendor/j87nl/laravel-mailbox/database/migrations/add_signature_to_mailboxes_table.php.stub')->up();
        (include __DIR__.'/../vendor/j87nl/laravel-mailbox/database/migrations/create_mailbox_contacts_table.php.stub')->up();
        (include __DIR__.'/../vendor/j87nl/laravel-mailbox/database/migrations/add_sent_at_index_to_mailbox_messages_table.php.stub')->up();
        (include __DIR__.'/../vendor/j87nl/laravel-mailbox/database/migrations/add_conversations_to_mailbox_tables.php.stub')->up();
    }
}
