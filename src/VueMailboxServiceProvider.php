<?php

declare(strict_types=1);

namespace J87NL\VueMailbox;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Contracts\ResolvesInlineImageUrls;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\VueMailbox\Commands\InstallCommand;
use J87NL\VueMailbox\Support\InlineUrls;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VueMailboxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vue-mailbox')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(ResolvesInlineImageUrls::class, InlineUrls::class);

        $this->mergePageDefaults();
    }

    /**
     * @return array<string, string>
     */
    public static function defaultPages(): array
    {
        return (require __DIR__.'/../config/vue-mailbox.php')['pages'];
    }

    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            self::defineAbilities();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../resources/js/pages' => resource_path('js/pages/Mailbox'),
        ], 'vue-mailbox-pages');
    }

    private function mergePageDefaults(): void
    {
        $config = $this->app->make('config');

        $config->set('vue-mailbox.pages', [
            ...self::defaultPages(),
            ...$config->get('vue-mailbox.pages', []),
        ]);
    }

    public static function defineAbilities(): void
    {
        if (Gate::has('manageAnyMailbox')) {
            return;
        }

        Gate::define('manageAnyMailbox', function (?Authenticatable $user): bool {
            if (is_null($user)) {
                return false;
            }

            if (Gate::forUser($user)->allows('manageMailboxes')) {
                return true;
            }

            $managesOne = false;

            Mailbox::query()
                ->chunkById(50, function (Collection $mailboxes) use ($user, &$managesOne): bool {
                    app(MailboxAccessResolver::class)->preload($user, $mailboxes->modelKeys());

                    $managesOne = $mailboxes->contains(fn (Mailbox $mailbox): bool => Gate::forUser($user)->allows('manageMailbox', $mailbox));

                    return ! $managesOne;
                });

            return $managesOne;
        });
    }
}
