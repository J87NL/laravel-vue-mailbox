<?php

declare(strict_types=1);

namespace J87NL\VueMailbox;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Contracts\ResolvesInlineImageUrls;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\VueMailbox\Commands\InstallCommand;
use J87NL\VueMailbox\Support\InlineUrls;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VueMailboxServiceProvider extends PackageServiceProvider
{
    public const REFRESH_LIMITER = 'vue-mailbox-refresh';

    public const ACCESSOR_SEARCH_LIMITER = 'vue-mailbox-accessor-search';

    public const NOTIFICATIONS_LIMITER = 'vue-mailbox-notifications';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('vue-mailbox')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasTranslations()
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
        RateLimiter::for(self::REFRESH_LIMITER, fn (Request $request): Limit => Limit::perMinute(12)
            ->by(sprintf('%s|%s', self::REFRESH_LIMITER, $request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for(self::ACCESSOR_SEARCH_LIMITER, fn (Request $request): Limit => Limit::perMinute(60)
            ->by(sprintf('%s|%s', self::ACCESSOR_SEARCH_LIMITER, $request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for(self::NOTIFICATIONS_LIMITER, fn (Request $request): Limit => Limit::perMinute(60)
            ->by(sprintf('%s|%s', self::NOTIFICATIONS_LIMITER, $request->user()?->getAuthIdentifier() ?? $request->ip())));

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
