<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Commands;

use Illuminate\Console\Command;
use TranquilTools\VueFormBuilder\Support\Installer\InstallsFrontendAliases;

class InstallCommand extends Command
{
    use InstallsFrontendAliases;

    private const SOURCE = './vendor/j87nl/laravel-vue-mailbox/resources/js';

    protected $signature = 'mailbox:install {--pages : Also publish the default Inertia pages for customising}';

    protected $description = 'Publish the mailbox config and migrations and add the @mailbox Vite alias and tsconfig paths';

    public function handle(): int
    {
        foreach (['mailbox-config', 'mailbox-migrations', 'vue-mailbox-config'] as $tag) {
            $this->callSilently('vendor:publish', [
                '--tag' => $tag,
            ]);
        }

        $this->components->info('Published the mailbox config and migrations.');

        $this->installAliases([
            '@mailbox' => self::SOURCE,
        ]);

        $this->installTsPaths([
            '@mailbox/*' => sprintf('%s/*', self::SOURCE),
        ]);

        if ($this->option('pages')) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'vue-mailbox-pages',
            ]);

            $this->components->info('Published the mailbox pages to resources/js/pages/Mailbox.');
        }

        $this->components->info('Run php artisan migrate, schedule mailbox:sync and mailbox:prune-bodies, and let Tailwind scan the package.');

        return self::SUCCESS;
    }
}
