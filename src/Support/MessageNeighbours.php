<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Tables\MessagesTable;

class MessageNeighbours
{
    /**
     * @return array{next: ?int, previous: ?int}
     */
    public function around(Mailbox $mailbox, MailboxMessage $message): array
    {
        [$list, $anchor, $descending] = $this->prepare($mailbox, $message);

        return [
            'next' => $this->closest($list, $anchor, larger: ! $descending),
            'previous' => $this->closest($list, $anchor, larger: $descending),
        ];
    }

    /**
     * @param  array<int, int>  $excludedIds
     */
    public function next(Mailbox $mailbox, MailboxMessage $message, array $excludedIds = []): ?int
    {
        [$list, $anchor, $descending] = $this->prepare($mailbox, $message);

        return $this->closest($list, $anchor, larger: ! $descending, excludedIds: $excludedIds);
    }

    /**
     * @return array{0: Builder<MailboxMessage>, 1: MailboxMessage, 2: bool}
     */
    private function prepare(Mailbox $mailbox, MailboxMessage $message): array
    {
        $folder = $message->folder->setRelation('mailbox', $mailbox);
        $table = new MessagesTable($folder);

        /** @var Builder<MailboxMessage> $list */
        $list = $table->filteredQuery();
        $anchor = $table->groupsConversations() && ! is_null($message->thread_key)
            ? $this->conversationRow($message)
            : $message;

        return [
            $list,
            $anchor,
            $this->descending($list),
        ];
    }

    private function conversationRow(MailboxMessage $message): MailboxMessage
    {
        return MailboxMessage::query()
            ->where('mailbox_folder_id', $message->mailbox_folder_id)
            ->where('thread_key', $message->thread_key)
            ->orderByRaw('case when sent_at is null then 1 else 0 end')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'sent_at',
            ]) ?? $message;
    }

    /**
     * @param  Builder<MailboxMessage>  $list
     */
    private function descending(Builder $list): bool
    {
        $order = collect($list->getQuery()->orders ?? [])
            ->first(fn (array $order): bool => ($order['column'] ?? null) === 'sent_at');

        return is_null($order) || mb_strtolower((string) $order['direction']) === 'desc';
    }

    /**
     * @param  Builder<MailboxMessage>  $list
     * @param  array<int, int>  $excludedIds
     */
    private function closest(Builder $list, MailboxMessage $anchor, bool $larger, array $excludedIds = []): ?int
    {
        foreach ($this->segments($anchor, $larger) as $segment) {
            $id = $segment($list->clone()
                ->reorder()
                ->when($excludedIds !== [], fn (Builder $query): Builder => $query->whereKeyNot($excludedIds)))
                ->value('mailbox_messages.id');

            if (! is_null($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * @return list<Closure(Builder<MailboxMessage>): Builder<MailboxMessage>>
     */
    private function segments(MailboxMessage $anchor, bool $larger): array
    {
        $operator = $larger ? '>' : '<';
        $direction = $larger ? 'asc' : 'desc';
        $undated = fn (Builder $query): Builder => $query
            ->whereNull('mailbox_messages.sent_at')
            ->orderBy('mailbox_messages.id', $direction);
        $undatedBeyondAnchor = fn (Builder $query): Builder => $undated($query)
            ->where('mailbox_messages.id', $operator, $anchor->id);
        $dated = fn (Builder $query): Builder => $query
            ->whereNotNull('mailbox_messages.sent_at')
            ->orderBy('mailbox_messages.sent_at', $direction)
            ->orderBy('mailbox_messages.id', $direction);

        if (is_null($anchor->sent_at)) {
            return $larger
                ? [
                    $undatedBeyondAnchor,
                    $dated,
                ]
                : [
                    $undatedBeyondAnchor,
                ];
        }

        $datedBeyondAnchor = fn (Builder $query): Builder => $query
            ->where(function (Builder $beyond) use ($anchor, $operator): void {
                $beyond->where('mailbox_messages.sent_at', $operator, $anchor->sent_at)
                    ->orWhere(function (Builder $same) use ($anchor, $operator): void {
                        $same->where('mailbox_messages.sent_at', $anchor->sent_at)
                            ->where('mailbox_messages.id', $operator, $anchor->id);
                    });
            })
            ->orderBy('mailbox_messages.sent_at', $direction)
            ->orderBy('mailbox_messages.id', $direction);

        return $larger
            ? [
                $datedBeyondAnchor,
            ]
            : [
                $datedBeyondAnchor,
                $undated,
            ];
    }
}
