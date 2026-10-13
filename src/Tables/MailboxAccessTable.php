<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Contracts\ResolvesAccessors;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class MailboxAccessTable extends AbstractTable
{
    public function __construct(
        private readonly Mailbox $mailbox,
    ) {}

    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return new self(Mailbox::query()->findOrFail($parameters['mailbox']));
    }

    public function authorize(Request $request): bool
    {
        return Gate::allows('manageMailbox', $this->mailbox);
    }

    public function signedParameters(): array
    {
        return ['mailbox' => $this->mailbox->id];
    }

    public function for(): Builder
    {
        return MailboxAccess::query()->whereBelongsTo($this->mailbox);
    }

    public function configure(TableBuilder $table): void
    {
        $accessors = app(ResolvesAccessors::class);
        $routes = config('vue-mailbox.routes.name');

        $table
            ->name('access')
            ->defaultSort('accessor_type')
            ->withoutGlobalSearch()
            ->withoutColumnToggle()
            ->column('accessor_id', __('mailbox::mailbox.accessor'), as: fn (mixed $value, MailboxAccess $access): string => $accessors->label($access->accessor_type, $access->accessor_id) ?? $access->accessor_type.' #'.$access->accessor_id)
            ->column('level', __('mailbox::mailbox.level'), as: fn (mixed $value, MailboxAccess $access): string => $access->level->label())
            ->rowAction(
                __('mailbox::mailbox.revoke'),
                url: fn (MailboxAccess $access): string => route($routes.'settings.access.destroy', [$this->mailbox, $access]),
                method: 'delete',
                confirm: __('mailbox::mailbox.revoke_confirm'),
                variant: 'destructive',
            )
            ->emptyState(title: __('mailbox::mailbox.access_empty'))
            ->paginate(50);
    }
}
