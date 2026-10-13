<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use J87NL\VueMailbox\Http\Controllers\AttachmentController;
use J87NL\VueMailbox\Http\Controllers\ComposeController;
use J87NL\VueMailbox\Http\Controllers\FolderController;
use J87NL\VueMailbox\Http\Controllers\MessageActionController;
use J87NL\VueMailbox\Http\Controllers\MessageController;
use J87NL\VueMailbox\Http\Controllers\Settings\MailboxAccessController;
use J87NL\VueMailbox\Http\Controllers\Settings\MailboxSettingsController;

Route::prefix(config('vue-mailbox.routes.prefix'))
    ->name(config('vue-mailbox.routes.name'))
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
                ->middleware('can:accessAnyMailbox')
                ->name('index');

            Route::prefix('settings')->name('settings.')->group(function (): void {
                Route::get('/', [MailboxSettingsController::class, 'index'])->name('index');
                Route::get('create', [MailboxSettingsController::class, 'create'])->middleware('can:manageMailboxes')->name('create');
                Route::post('/', [MailboxSettingsController::class, 'store'])->middleware('can:manageMailboxes')->name('store');
                Route::delete('{mailbox}', [MailboxSettingsController::class, 'destroy'])->middleware('can:manageMailboxes')->name('destroy');

                Route::middleware('can:manageMailbox,mailbox')->scopeBindings()->group(function (): void {
                    Route::get('{mailbox}', [MailboxSettingsController::class, 'edit'])->name('edit');
                    Route::put('{mailbox}', [MailboxSettingsController::class, 'update'])->name('update');
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
                    Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
                    Route::get('messages/{message}/attachments/{index}', [AttachmentController::class, 'download'])
                        ->whereNumber('index')
                        ->name('attachments.download');

                    Route::middleware('can:workInMailbox,mailbox')->group(function (): void {
                        Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
                        Route::post('messages/actions', MessageActionController::class)->name('messages.actions');
                        Route::get('compose', [ComposeController::class, 'create'])->name('compose.create');
                        Route::post('compose', [ComposeController::class, 'store'])->name('compose.store');
                    });
                });
        });
    });
