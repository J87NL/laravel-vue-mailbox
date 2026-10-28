<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use J87NL\VueMailbox\Http\Controllers\AttachmentController;
use J87NL\VueMailbox\Http\Controllers\ComposeController;
use J87NL\VueMailbox\Http\Controllers\ContactController;
use J87NL\VueMailbox\Http\Controllers\FolderController;
use J87NL\VueMailbox\Http\Controllers\MessageActionController;
use J87NL\VueMailbox\Http\Controllers\MessageController;
use J87NL\VueMailbox\Http\Controllers\NotificationController;
use J87NL\VueMailbox\Http\Controllers\Settings\MailboxAccessController;
use J87NL\VueMailbox\Http\Controllers\Settings\MailboxSettingsController;
use J87NL\VueMailbox\Http\Middleware\ShareMailboxTranslations;
use J87NL\VueMailbox\VueMailboxServiceProvider;

Route::prefix(config('vue-mailbox.routes.prefix'))
    ->name(config('vue-mailbox.routes.name'))
    ->middleware(ShareMailboxTranslations::class)
    ->group(function (): void {
        Route::get('inline/{message}/{cid}', [AttachmentController::class, 'inline'])
            ->middleware([
                'web',
                'signed',
            ])
            ->where('cid', '.*')
            ->name('inline');

        Route::middleware(config('vue-mailbox.routes.middleware'))->group(function (): void {
            Route::get('/', [FolderController::class, 'index'])
                ->name('index');

            Route::get('notifications', NotificationController::class)
                ->middleware([
                    'can:accessAnyMailbox',
                    sprintf('throttle:%s', VueMailboxServiceProvider::NOTIFICATIONS_LIMITER),
                ])
                ->name('notifications');

            Route::prefix('settings')->name('settings.')->group(function (): void {
                Route::get('/', [MailboxSettingsController::class, 'index'])->middleware('can:manageAnyMailbox')->name('index');
                Route::get('create', [MailboxSettingsController::class, 'create'])->middleware('can:manageMailboxes')->name('create');
                Route::post('/', [MailboxSettingsController::class, 'store'])->middleware('can:manageMailboxes')->name('store');
                Route::delete('{mailbox}', [MailboxSettingsController::class, 'destroy'])
                    ->middleware([
                        'can:manageMailboxes',
                        'can:manageMailbox,mailbox',
                    ])
                    ->name('destroy');

                Route::middleware('can:manageMailbox,mailbox')->scopeBindings()->group(function (): void {
                    Route::get('{mailbox}', [MailboxSettingsController::class, 'edit'])->name('edit');
                    Route::put('{mailbox}', [MailboxSettingsController::class, 'update'])->name('update');
                    Route::get('{mailbox}/accessors', [MailboxAccessController::class, 'search'])
                        ->middleware(sprintf('throttle:%s', VueMailboxServiceProvider::ACCESSOR_SEARCH_LIMITER))
                        ->name('accessors');
                    Route::post('{mailbox}/access', [MailboxAccessController::class, 'store'])->name('access.store');
                    Route::delete('{mailbox}/access/{access}', [MailboxAccessController::class, 'destroy'])->name('access.destroy');
                });
            });

            Route::prefix('{mailbox}')
                ->whereNumber('mailbox')
                ->middleware('can:accessMailbox,mailbox')
                ->scopeBindings()
                ->group(function (): void {
                    Route::get('folders/{folder?}', [FolderController::class, 'show'])->name('folders.show');
                    Route::post('folders/{folder}/refresh', [FolderController::class, 'refresh'])
                        ->middleware(sprintf('throttle:%s', VueMailboxServiceProvider::REFRESH_LIMITER))
                        ->name('folders.refresh');
                    Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
                    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
                    Route::get('messages/{message}/attachments/{index}', [AttachmentController::class, 'download'])
                        ->whereNumber('index')
                        ->name('attachments.download');

                    Route::middleware('can:workInMailbox,mailbox')->group(function (): void {
                        Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
                        Route::post('messages/actions', MessageActionController::class)->name('messages.actions');
                        Route::get('compose', [ComposeController::class, 'create'])->name('compose.create');
                        Route::get('addresses', [ComposeController::class, 'addresses'])->name('addresses');
                        Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
                        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
                        Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
                        Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
                        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
                        Route::post('messages/{message}/contact', [ContactController::class, 'fromMessage'])->name('messages.contact');
                        Route::post('compose', [ComposeController::class, 'store'])->name('compose.store');
                    });
                });
        });
    });
