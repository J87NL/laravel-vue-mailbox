<?php

declare(strict_types=1);

namespace J87NL\VueMailbox;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VueMailboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vue-mailbox')
            ->hasConfigFile()
            ->hasRoute('web');
    }

    public function packageBooted(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../resources/js/pages/Mailbox' => resource_path('js/pages/Mailbox'),
        ], 'vue-mailbox-pages');
    }
}
