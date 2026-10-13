<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Illuminate\Support\Str;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Support\DisplayTime;

class ComposeDefaults
{
    private const FORWARD_PREFIXES = [
        'fw:',
        'fwd:',
    ];

    /**
     * @return array<string, mixed>
     */
    public function for(Mailbox $mailbox, ?MailboxMessage $source, string $mode): array
    {
        if (is_null($source)) {
            return [
                'body' => $this->body($mailbox, ''),
            ];
        }

        $subject = (string) $source->subject;
        $body = $this->body($mailbox, $this->quote($source));

        if ($mode === 'forward') {
            return [
                'body' => $body,
                'forward_message' => $source->id,
                'subject' => Str::startsWith(Str::lower($subject), self::FORWARD_PREFIXES) ? $subject : sprintf('Fwd: %s', $subject),
            ];
        }

        $recipients = $this->replyRecipients($mailbox, $source);

        return [
            'body' => $body,
            'cc' => $mode === 'reply_all' ? $this->others($mailbox, $source, $recipients) : [],
            'reply_to_message' => $source->id,
            'subject' => Str::startsWith(Str::lower($subject), 're:') ? $subject : sprintf('Re: %s', $subject),
            'to' => $recipients,
        ];
    }

    private function body(Mailbox $mailbox, string $quote): string
    {
        if (is_null($mailbox->signature) || $mailbox->signature === '') {
            return sprintf('<p></p>%s', $quote);
        }

        return sprintf('<p></p><div class="mailbox-signature">%s</div>%s', $mailbox->signature, $quote);
    }

    /**
     * @return array<int, string>
     */
    private function replyRecipients(Mailbox $mailbox, MailboxMessage $source): array
    {
        if ($this->isOwnAddress($mailbox, $source->from_email)) {
            return collect($source->to ?? [])
                ->pluck('email')
                ->filter()
                ->reject(fn (string $email): bool => $this->isOwnAddress($mailbox, $email))
                ->unique(fn (string $email): string => Str::lower($email))
                ->values()
                ->all();
        }

        $replyTo = collect($source->reply_to ?? [])
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if ($replyTo !== []) {
            return $replyTo;
        }

        if (is_null($source->from_email)) {
            return [];
        }

        return [
            $source->from_email,
        ];
    }

    private function isOwnAddress(Mailbox $mailbox, ?string $email): bool
    {
        return ! is_null($email) && Str::lower($email) === Str::lower($mailbox->email);
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    private function others(Mailbox $mailbox, MailboxMessage $source, array $recipients): array
    {
        $excluded = array_map(fn (string $email): string => Str::lower($email), [
            $mailbox->email,
            (string) $source->from_email,
            ...$recipients,
        ]);

        return collect([...($source->to ?? []), ...($source->cc ?? [])])
            ->pluck('email')
            ->filter()
            ->reject(fn (string $email): bool => in_array(Str::lower($email), $excluded, true))
            ->unique(fn (string $email): string => Str::lower($email))
            ->values()
            ->all();
    }

    private function quote(MailboxMessage $source): string
    {
        $sender = trim(sprintf('%s <%s>', (string) $source->from_name, (string) $source->from_email));
        $body = $source->body_text ? nl2br(e($source->body_text)) : e(strip_tags((string) $source->body_html));

        return sprintf(
            '<p>%s — %s</p><blockquote>%s</blockquote>',
            e($sender),
            e(DisplayTime::format($source->sent_at, 'd-m-Y H:i')),
            $body,
        );
    }
}
