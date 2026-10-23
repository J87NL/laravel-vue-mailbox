# Testing and development

## Testing your app

The core package ships a fake IMAP and SMTP connector for tests, `J87NL\Mailbox\Testing\FakeConnector`. This package's
own test suite uses it to run the routes without a mail server:

```php
<?php

declare(strict_types=1);

use J87NL\Mailbox\Testing\FakeConnector;

beforeEach(function (): void {
    $this->connector = FakeConnector::install();
});
```

Fake the disk of the core raw message cache (`Storage::fake('local')`) so tests that open a message do not write to
`storage/app`. How the fake works is documented in the core
[testing](https://github.com/J87NL/laravel-mailbox/blob/main/docs/testing.md) docs.

The page props are plain Inertia props, so you can check them with Inertia's testing helpers:

```php
use Inertia\Testing\AssertableInertia;

$this->actingAs($user)
    ->get(route('mailbox.folders.show', [$mailbox, $folder]))
    ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('mailbox/Index', false)
        ->has('table')
        ->where('vue_mailbox_translations.reply', 'Reply'));
```

Pass `false` as the second argument of `component()` for a package page: Inertia's `ensure_pages_exist` check looks for
the page in `resources/js/pages` of your app, where `mailbox/Index` does not exist. A copy in your app
(`Mailbox/Index`) needs no `false`.

## Working on the package

```bash
composer install
composer test
composer analyse
composer format
```

| Script | Runs |
|---|---|
| `composer test` | Pest |
| `composer analyse` | PHPStan (Larastan) |
| `composer format` | Pint |

The tests run on Orchestra Testbench with an SQLite database and the core migrations.

### Against a local core checkout

The core package comes from Packagist. To work against a local checkout of `j87nl/laravel-mailbox`, add a path
repository temporarily and do not commit it:

```bash
composer config repositories.core path ../laravel-mailbox
composer require j87nl/laravel-mailbox:@dev
# when done
git checkout composer.json && composer update j87nl/laravel-mailbox
```
