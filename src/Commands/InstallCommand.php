<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use J87NL\VueMailbox\Support\ViteAliasArray;
use J87NL\VueMailbox\VueMailboxServiceProvider;
use TranquilTools\VueFormBuilder\Support\Installer\InstallsFrontendAliases;

class InstallCommand extends Command
{
    use InstallsFrontendAliases;

    private const PAGE_PREFIX = 'mailbox/';

    private const SOURCE = './vendor/j87nl/laravel-vue-mailbox/resources/js';

    protected $signature = 'mailbox:install {--pages : Also copy the default Inertia pages into the app and point the config at the copies}';

    protected $description = 'Publish the mailbox config and migrations and add the @mailbox Vite alias and tsconfig paths';

    public function handle(): int
    {
        foreach ([
            'mailbox-config',
            'mailbox-migrations',
            'vue-mailbox-config',
        ] as $tag) {
            $this->callSilently('vendor:publish', [
                '--tag' => $tag,
            ]);
        }

        $this->components->info('Published the mailbox config and migrations.');

        $this->installViteAlias();

        $this->installTsPaths([
            '@mailbox/*' => sprintf('%s/*', self::SOURCE),
        ]);

        $this->installPages();

        $this->warnAboutUnusedCopies();

        $this->warnAboutMissingCopies();

        $this->components->info('Run php artisan migrate, schedule mailbox:sync and mailbox:prune-bodies, and let Tailwind scan the package.');

        return self::SUCCESS;
    }

    private function installViteAlias(): void
    {
        $path = $this->viteConfigPath();
        $content = is_null($path) ? '' : File::get($path);
        $aliasArray = app(ViteAliasArray::class);

        if (! $aliasArray->isUsedIn($content)) {
            $this->installAliases([
                '@mailbox' => self::SOURCE,
            ]);

            return;
        }

        $file = basename((string) $path);

        if (str_contains($content, "'@mailbox'") || str_contains($content, '"@mailbox"')) {
            $this->components->info(sprintf('%s already has the Vite aliases.', $file));

            return;
        }

        File::put((string) $path, $aliasArray->add($content, '@mailbox', self::SOURCE));

        $this->components->info(sprintf('Vite aliases added to %s: @mailbox.', $file));
    }

    private function installPages(): void
    {
        if (! $this->option('pages')) {
            $this->components->info('The pages stay in the package: let your Inertia page resolver load mailbox/* pages from @mailbox/pages.');

            return;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'vue-mailbox-pages',
        ]);

        $this->components->info('Published the mailbox pages to resources/js/pages/Mailbox.');

        $configPath = config_path('vue-mailbox.php');

        if (! File::exists($configPath)) {
            return;
        }

        $config = File::get($configPath);
        $pointed = $config;

        foreach ($this->packagePageNames() as $name) {
            $copy = sprintf('Mailbox/%s', Str::after($name, self::PAGE_PREFIX));

            $pointed = str_replace([
                sprintf("'%s'", $name),
                sprintf('"%s"', $name),
            ], [
                sprintf("'%s'", $copy),
                sprintf('"%s"', $copy),
            ], $pointed);
        }

        if ($pointed === $config) {
            return;
        }

        File::put($configPath, $pointed);

        $this->components->info('Pointed the page names in config/vue-mailbox.php at the published pages.');
    }

    private function warnAboutUnusedCopies(): void
    {
        if (! File::exists(resource_path('js/pages/Mailbox/Index.vue'))) {
            return;
        }

        $index = $this->publishedPages()['index'];

        if (! str_starts_with($index, self::PAGE_PREFIX)) {
            return;
        }

        $this->components->warn(sprintf(
            'resources/js/pages/Mailbox has copies of the pages, but the page name "index" is %s, so the app still renders the package pages. Point the page names in config/vue-mailbox.php at the copies (Mailbox/...), or delete the copies.',
            $index,
        ));
    }

    private function warnAboutMissingCopies(): void
    {
        $missing = array_values(array_filter(
            $this->publishedPages(),
            fn (string $name): bool => str_starts_with($name, 'Mailbox/')
                && ! File::exists(resource_path(sprintf('js/pages/%s.vue', $name))),
        ));

        if ($missing === []) {
            return;
        }

        $this->components->warn(sprintf(
            'config/vue-mailbox.php names %s, but resources/js/pages has no such page. Since 0.5 the package pages are called mailbox/...: point these names at mailbox/... and load them from @mailbox/pages, or run mailbox:install --pages to copy the pages.',
            implode(', ', $missing),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function publishedPages(): array
    {
        $configPath = config_path('vue-mailbox.php');
        $published = File::exists($configPath) ? require $configPath : [];

        return [
            ...VueMailboxServiceProvider::defaultPages(),
            ...($published['pages'] ?? []),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function packagePageNames(): array
    {
        return array_values(array_filter(
            VueMailboxServiceProvider::defaultPages(),
            fn (string $name): bool => str_starts_with($name, self::PAGE_PREFIX),
        ));
    }
}
