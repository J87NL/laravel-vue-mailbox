<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Actions\ArchiveMessages;
use J87NL\Mailbox\Actions\DeleteMessages;
use J87NL\Mailbox\Actions\MarkMessages;
use J87NL\Mailbox\Actions\MoveMessages;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Support\DisplayTime;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class MessagesTable extends AbstractTable
{
    public function __construct(
        private readonly MailboxFolder $folder,
    ) {}

    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return new self(MailboxFolder::query()
            ->with('mailbox')->findOrFail($parameters['folder']));
    }

    public function authorize(Request $request): bool
    {
        return Gate::allows('accessMailbox', $this->folder->mailbox);
    }

    public function authorizeBulkAction(Request $request, int $key): bool
    {
        return Gate::allows('workInMailbox', $this->folder->mailbox);
    }

    public function signedParameters(): array
    {
        return [
            'folder' => $this->folder->id,
        ];
    }

    public function for(): Builder
    {
        return MailboxMessage::query()
            ->select([
                'id',
                'mailbox_id',
                'mailbox_folder_id',
                'uid',
                'subject',
                'from_email',
                'from_name',
                'sent_at',
                'is_seen',
                'is_flagged',
                'is_answered',
                'has_attachments',
            ])
            ->whereBelongsTo($this->folder, 'folder');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->name('messages')
            ->defaultSortDesc('sent_at')
            ->withGlobalSearch(columns: [
                'subject',
                'from_email',
                'from_name',
            ])
            ->column('from_name', __('mailbox::mailbox.from'), canBeHidden: false, highlight: true, as: fn (?string $name, MailboxMessage $message): string => $this->sender($name, $message))
            ->column('subject', __('mailbox::mailbox.subject'), canBeHidden: false, as: fn (?string $subject, MailboxMessage $message): HtmlString|string => $this->subject($subject, $message))
            ->column('sent_at', __('mailbox::mailbox.received'), sortable: true, as: fn (mixed $value, MailboxMessage $message): string => $this->received($message))
            ->rowLink(fn (MailboxMessage $message): string => route(config('vue-mailbox.routes.name').'messages.show', [$this->folder->mailbox, $message]))
            ->rowClass(fn (MailboxMessage $message): string => $message->is_seen ? '' : 'font-semibold')
            ->poll((int) config('vue-mailbox.poll_seconds', 30))
            ->emptyState(title: __('mailbox::mailbox.empty'))
            ->paginate(config('vue-mailbox.per_page'));

        if (! Gate::allows('workInMailbox', $this->folder->mailbox)) {
            return;
        }

        $mailbox = $this->folder->mailbox;

        $table
            ->bulkAction(__('mailbox::mailbox.archive'), before: fn (array $ids): int => app(ArchiveMessages::class)->handle($mailbox, $this->messages($ids), auth()->user()))
            ->bulkAction(__('mailbox::mailbox.delete'), before: fn (array $ids): int => app(DeleteMessages::class)->handle($mailbox, $this->messages($ids), auth()->user()), confirm: __('mailbox::mailbox.delete_confirm'))
            ->bulkAction(__('mailbox::mailbox.mark_read'), before: function (array $ids): void {
                app(MarkMessages::class)->read($this->messages($ids), auth()->user());
            })
            ->bulkAction(__('mailbox::mailbox.mark_unread'), before: function (array $ids): void {
                app(MarkMessages::class)->unread($this->messages($ids), auth()->user());
            })
            ->bulkAction(
                __('mailbox::mailbox.move_to_folder'),
                before: fn (array $ids, ?string $choice): int => app(MoveMessages::class)->handle(
                    $this->messages($ids),
                    $mailbox->folders()->findOrFail((int) $choice),
                    auth()->user(),
                ),
                choices: $this->moveChoices(),
                choicesLabel: __('mailbox::mailbox.folder'),
            );
    }

    /**
     * @return array<int, string>
     */
    private function moveChoices(): array
    {
        return $this->folder->mailbox->loadMissing('folders')->folders
            ->where('selectable', true)
            ->reject(fn (MailboxFolder $folder): bool => $folder->id === $this->folder->id)
            ->sortBy(fn (MailboxFolder $folder): string => sprintf('%d-%s', $folder->role->sortOrder(), mb_strtolower($folder->path)))
            ->mapWithKeys(fn (MailboxFolder $folder): array => [$folder->id => $folder->role->label() ?? $folder->name])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, MailboxMessage>
     */
    private function messages(array $ids): Collection
    {
        return MailboxMessage::query()
            ->whereBelongsTo($this->folder, 'folder')
            ->whereKey($ids)
            ->with('folder')
            ->get();
    }

    private function received(MailboxMessage $message): string
    {
        if (! $message->sent_at) {
            return '';
        }

        return DisplayTime::format($message->sent_at, DisplayTime::isToday($message->sent_at) ? 'H:i' : 'd-m-Y');
    }

    private function sender(?string $name, MailboxMessage $message): string
    {
        return $name ?: (string) $message->from_email;
    }

    private function subject(?string $subject, MailboxMessage $message): HtmlString|string
    {
        $flag = $message->is_flagged ? '⚑ ' : '';
        $answered = $message->is_answered ? '↩ ' : '';
        $text = $flag.$answered.($subject ?: '—');

        if (! $message->has_attachments) {
            return $text;
        }

        return new HtmlString(sprintf('%s %s', e($text), $this->paperclip()));
    }

    private function paperclip(): string
    {
        $label = e(__('vue-mailbox::mailbox.has_attachments'));

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="%s" style="display:inline;vertical-align:-2px;opacity:.6"><title>%s</title><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
            $label,
            $label,
        );
    }
}
