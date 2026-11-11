<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use J87NL\Mailbox\Enums\SpamStatus;
use J87NL\Mailbox\Models\MailboxMessage;

class SpamBadge
{
    /**
     * @return array{detail: string, label: string, status: string}|null
     */
    public static function for(MailboxMessage $message): ?array
    {
        $status = $message->spam_status;

        if (! in_array($status, [
            SpamStatus::Suggested,
            SpamStatus::Moved,
        ], true)) {
            return null;
        }

        return [
            'detail' => self::detail($message),
            'label' => __(sprintf('vue-mailbox::mailbox.spam_%s', $status->value)),
            'status' => $status->value,
        ];
    }

    private static function detail(MailboxMessage $message): string
    {
        $score = is_null($message->spam_score)
            ? null
            : __('vue-mailbox::mailbox.spam_score', ['score' => number_format($message->spam_score, 2)]);

        return collect([
            $score,
            $message->spam_reason,
        ])
            ->filter(fn (?string $part): bool => ! is_null($part) && trim($part) !== '')
            ->implode(': ');
    }
}
