<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxContact;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class ContactsTable extends AbstractTable
{
    public function __construct(
        private readonly Mailbox $mailbox,
    ) {}

    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return new self(Mailbox::query()
            ->findOrFail($parameters['mailbox']));
    }

    public function authorize(Request $request): bool
    {
        return Gate::allows('accessMailbox', $this->mailbox);
    }

    public function authorizeBulkAction(Request $request, int $key): bool
    {
        return Gate::allows('workInMailbox', $this->mailbox);
    }

    public function signedParameters(): array
    {
        return [
            'mailbox' => $this->mailbox->id,
        ];
    }

    public function for(): Builder
    {
        return MailboxContact::query()
            ->whereBelongsTo($this->mailbox);
    }

    public function configure(TableBuilder $table): void
    {
        $routes = config('vue-mailbox.routes.name');

        $table
            ->name('contacts')
            ->defaultSort('name')
            ->withGlobalSearch(columns: [
                'name',
                'email',
                'organisation',
            ])
            ->column('name', __('mailbox::mailbox.name'), sortable: true, highlight: true)
            ->column('email', __('mailbox::mailbox.address'), sortable: true)
            ->column('organisation', __('mailbox::mailbox.organisation'), sortable: true)
            ->emptyState(title: __('mailbox::mailbox.contacts_empty'))
            ->paginate(50);

        if (! Gate::allows('workInMailbox', $this->mailbox)) {
            return;
        }

        $table
            ->rowAction(
                __('mailbox::mailbox.edit'),
                url: fn (MailboxContact $contact): string => route($routes.'contacts.edit', [$this->mailbox, $contact]),
            )
            ->rowAction(
                __('mailbox::mailbox.delete'),
                url: fn (MailboxContact $contact): string => route($routes.'contacts.destroy', [$this->mailbox, $contact]),
                method: 'delete',
                confirm: __('mailbox::mailbox.contact_delete_confirm'),
                variant: 'destructive',
            )
            ->bulkAction(
                __('mailbox::mailbox.delete_selected'),
                before: function (array $ids): void {
                    $this->mailbox->contacts()
                        ->whereKey($ids)
                        ->delete();
                },
                confirm: __('mailbox::mailbox.contact_delete_confirm'),
            );
    }
}
