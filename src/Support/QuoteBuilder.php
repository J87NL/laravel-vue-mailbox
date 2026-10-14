<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use J87NL\Mailbox\Html\MessageHtml;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Support\DisplayTime;

class QuoteBuilder
{
    private const BLOCKQUOTE_STYLE = 'margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex';

    public function __construct(
        private readonly MessageHtml $html,
        private readonly InlineUrls $inlineUrls,
    ) {}

    public function reply(MailboxMessage $source): string
    {
        return sprintf(
            '<p>%s</p><blockquote style="%s">%s</blockquote>',
            e(__('mailbox::mailbox.quote_wrote', [
                'date' => DisplayTime::format($source->sent_at, 'd-m-Y H:i'),
                'name' => $this->sender($source),
            ])),
            self::BLOCKQUOTE_STYLE,
            $this->original($source),
        );
    }

    public function forward(MailboxMessage $source): string
    {
        $lines = array_filter([
            e(sprintf('---------- %s ---------', __('mailbox::mailbox.quote_forwarded'))),
            $this->headerLine('from', $this->sender($source)),
            $this->headerLine('date', DisplayTime::format($source->sent_at, 'd-m-Y H:i')),
            $this->headerLine('subject', (string) $source->subject),
            $this->headerLine('to', $this->addresses($source->to)),
            $this->headerLine('cc', $this->addresses($source->cc)),
        ]);

        return sprintf(
            '<div class="mailbox-quote"><p>%s</p>%s</div>',
            implode('<br>', $lines),
            $this->original($source),
        );
    }

    private function original(MailboxMessage $source): string
    {
        return $this->html->quote($source->body_html, $source->body_text, $this->inlineUrls->for($source));
    }

    private function headerLine(string $label, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return sprintf('%s: %s', e(__(sprintf('mailbox::mailbox.%s', $label))), e($value));
    }

    private function sender(MailboxMessage $source): string
    {
        if (is_null($source->from_name) || $source->from_name === '') {
            return (string) $source->from_email;
        }

        return sprintf('%s <%s>', $source->from_name, (string) $source->from_email);
    }

    /**
     * @param  array<int, array{email: string, name: string}>|null  $addresses
     */
    private function addresses(?array $addresses): string
    {
        return collect($addresses ?? [])
            ->map(fn (array $address): string => $address['name'] === '' ? $address['email'] : sprintf('%s <%s>', $address['name'], $address['email']))
            ->implode(', ');
    }
}
