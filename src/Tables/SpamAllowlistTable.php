<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxSpamAllowlistEntry;
use TranquilTools\TableBuilder\TableBuilder;

final class SpamAllowlistTable extends MailboxTable
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
        return Gate::allows('manageMailbox', $this->mailbox);
    }

    public function signedParameters(): array
    {
        return [
            'mailbox' => $this->mailbox->id,
        ];
    }

    public function for(): Builder
    {
        return MailboxSpamAllowlistEntry::query()
            ->where(function (Builder $query): void {
                $query->where('mailbox_id', $this->mailbox->id)
                    ->orWhereNull('mailbox_id');
            });
    }

    public function configure(TableBuilder $table): void
    {
        $routes = config('vue-mailbox.routes.name');

        $table
            ->name('spam_allowlist')
            ->defaultSort('pattern')
            ->withGlobalSearch(columns: [
                'pattern',
            ])
            ->withoutColumnToggle()
            ->column('pattern', __('vue-mailbox::mailbox.spam_allowlist_pattern'), sortable: true, highlight: true)
            ->column('source', __('vue-mailbox::mailbox.spam_allowlist_source'), as: fn (mixed $value, MailboxSpamAllowlistEntry $entry): string => __(sprintf('vue-mailbox::mailbox.spam_allowlist_source_%s', $entry->source->value)))
            ->column('mailbox_id', __('vue-mailbox::mailbox.spam_allowlist_scope'), as: fn (mixed $value, MailboxSpamAllowlistEntry $entry): string => __(is_null($entry->mailbox_id) ? 'vue-mailbox::mailbox.spam_allowlist_scope_global' : 'vue-mailbox::mailbox.spam_allowlist_scope_mailbox'))
            ->rowAction(
                __('mailbox::mailbox.delete'),
                url: fn (MailboxSpamAllowlistEntry $entry): string => route($routes.'settings.spam-allowlist.destroy', [$this->mailbox, $entry->id]),
                method: 'delete',
                confirm: __('vue-mailbox::mailbox.spam_allowlist_remove_confirm'),
                variant: 'destructive',
                visible: fn (MailboxSpamAllowlistEntry $entry): bool => ! is_null($entry->mailbox_id),
            )
            ->emptyState(title: __('vue-mailbox::mailbox.spam_allowlist_empty'))
            ->paginate(50);
    }
}
