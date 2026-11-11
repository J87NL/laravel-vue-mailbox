<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Enums\SpamStatus;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;

class SpamSuggestion
{
    public static function offersNotJunk(Mailbox $mailbox, MailboxMessage $message): bool
    {
        return $message->spam_status === SpamStatus::Suggested
            && $message->folder->role === FolderRole::Inbox
            && $mailbox->spamFilterMode()->isActive();
    }
}
