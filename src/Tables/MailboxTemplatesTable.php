<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxTemplate;
use TranquilTools\TableBuilder\TableBuilder;

final class MailboxTemplatesTable extends MailboxTable
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
        return MailboxTemplate::query()
            ->whereBelongsTo($this->mailbox);
    }

    public function configure(TableBuilder $table): void
    {
        $routes = config('vue-mailbox.routes.name');

        $table
            ->name('templates')
            ->defaultSort('name')
            ->withoutGlobalSearch()
            ->withoutColumnToggle()
            ->column('name', __('mailbox::mailbox.template_name'), sortable: true, highlight: true)
            ->column('subject', __('mailbox::mailbox.subject'), as: fn (?string $subject): string => $subject ?? '')
            ->rowLink(fn (MailboxTemplate $template): string => route($routes.'settings.templates.edit', [$this->mailbox, $template]))
            ->rowAction(
                __('mailbox::mailbox.delete'),
                url: fn (MailboxTemplate $template): string => route($routes.'settings.templates.destroy', [$this->mailbox, $template]),
                method: 'delete',
                confirm: __('mailbox::mailbox.template_delete_confirm'),
                variant: 'destructive',
            )
            ->emptyState(title: __('mailbox::mailbox.templates_empty'))
            ->paginate(50);
    }
}
