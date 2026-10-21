<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Models\Mailbox;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

class MailboxesTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        $user = $request->user();

        if (is_null($user)) {
            return false;
        }

        return Gate::forUser($user)->allows('manageAnyMailbox');
    }

    public function for(): Builder
    {
        return Mailbox::query()
            ->whereKey($this->manageableIds());
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->name('mailboxes')
            ->defaultSort('name')
            ->withGlobalSearch(columns: [
                'name',
                'email',
            ])
            ->column('name', __('mailbox::mailbox.name'), sortable: true, highlight: true)
            ->column('email', __('mailbox::mailbox.address'), sortable: true)
            ->column('active', __('mailbox::mailbox.active'), boolean: true, alignment: 'center')
            ->column('synced_at', __('mailbox::mailbox.synced_at'), sortable: true, as: fn (mixed $value, Mailbox $mailbox): string => (string) $mailbox->synced_at?->diffForHumans())
            ->column('last_error', __('mailbox::mailbox.last_error'))
            ->rowLink(fn (Mailbox $mailbox): string => route(config('vue-mailbox.routes.name').'settings.edit', $mailbox))
            ->paginate(25);
    }

    private function manageableIds(): array
    {
        $mailboxes = Mailbox::query()
            ->get();
        $user = auth()->user();

        if (! is_null($user)) {
            app(MailboxAccessResolver::class)->preload($user, $mailboxes->modelKeys());
        }

        return $mailboxes
            ->filter(fn (Mailbox $mailbox): bool => Gate::allows('manageMailbox', $mailbox))
            ->modelKeys();
    }
}
