<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;

class ConversationMessages
{
    /**
     * @return array<int, int>
     */
    public function allMailFolderIds(Mailbox $mailbox): array
    {
        return MailboxFolder::query()
            ->where('mailbox_id', $mailbox->id)
            ->whereNotNull('flags')
            ->get([
                'id',
                'flags',
            ])
            ->filter(fn (MailboxFolder $folder): bool => $folder->isAllMail())
            ->modelKeys();
    }

    /**
     * @param  Builder<MailboxMessage>  $query
     * @param  array<int, int>  $allMailFolderIds
     * @return Builder<MailboxMessage>
     */
    public function constrain(Builder $query, Mailbox $mailbox, MailboxMessage $message, array $allMailFolderIds): Builder
    {
        return $query
            ->where('mailbox_id', $mailbox->id)
            ->where('thread_key', $message->thread_key)
            ->when($allMailFolderIds !== [], fn (Builder $query): Builder => $query->where(function (Builder $copies) use ($allMailFolderIds): void {
                $copies->whereNotIn('mailbox_folder_id', $allMailFolderIds)
                    ->orWhereNotExists(function (QueryBuilder $elsewhere) use ($allMailFolderIds): void {
                        $elsewhere->selectRaw('1')
                            ->from('mailbox_messages', 'elsewhere')
                            ->whereColumn('elsewhere.mailbox_id', 'mailbox_messages.mailbox_id')
                            ->whereColumn('elsewhere.message_id', 'mailbox_messages.message_id')
                            ->whereNotIn('elsewhere.mailbox_folder_id', $allMailFolderIds);
                    });
            }));
    }
}
