# Artisan commands

## mailbox:install

Sets up the package in your app.

```bash
php artisan mailbox:install
```

| Option | Description |
|---|---|
| `--pages` | Also publish the default Inertia pages to `resources/js/pages/Mailbox` for customizing |

The command:

1. Publishes the tags `mailbox-config` (`config/mailbox.php`), `mailbox-migrations` (the core migrations) and
   `vue-mailbox-config` (`config/vue-mailbox.php`). Files that already exist are not overwritten.
2. Adds the Vite alias `@mailbox` pointing to `./vendor/j87nl/laravel-vue-mailbox/resources/js` to
   `vite.config.ts` (or `.js`, `.mjs`, `.mts`). Without a Vite config it warns and skips this step.
3. Adds the path `@mailbox/*` to `tsconfig.json`, when that file exists.
4. With `--pages`, publishes the `vue-mailbox-pages` tag.

An alias or path that is already there is left alone, so running the command again is safe. When it cannot patch a
file, it prints a warning; add the alias by hand as shown in [Installation](installation.md#2-run-the-installer).

Afterwards, run `php artisan migrate`, schedule the core commands `mailbox:sync` and `mailbox:prune-bodies`, and let
Tailwind scan the package.

## Publish tags

| Tag | Publishes |
|---|---|
| `vue-mailbox-config` | `config/vue-mailbox.php` |
| `vue-mailbox-pages` | The default pages to `resources/js/pages/Mailbox` |

The core commands (`mailbox:sync`, `mailbox:prune-bodies` and others) are documented in the `j87nl/laravel-mailbox`
docs.
