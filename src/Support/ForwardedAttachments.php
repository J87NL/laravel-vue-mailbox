<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use DirectoryTree\ImapEngine\Attachment;
use Illuminate\Validation\ValidationException;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;

class ForwardedAttachments
{
    public function __construct(
        private readonly MessageBodyLoader $loader,
    ) {}

    /**
     * @return array<int, array{key: string, name: string, url: string}>
     */
    public function describe(Mailbox $mailbox, MailboxMessage $source): array
    {
        return collect($source->attachments ?? [])
            ->map(fn (array $attachment, int $index): array => [
                ...$attachment,
                'index' => $index,
            ])
            ->reject(fn (array $attachment): bool => $attachment['inline'] && ! is_null($attachment['content_id']))
            ->map(fn (array $attachment): array => [
                'key' => (string) $attachment['index'],
                'name' => (string) ($attachment['filename'] ?: __('mailbox::mailbox.attachment')),
                'url' => route(config('vue-mailbox.routes.name').'attachments.download', [$mailbox, $source, $attachment['index']]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, Attachment>
     */
    public function resolve(MailboxMessage $source, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $attachments = $this->loader->attachmentsAt($source, array_map('intval', $keys)) ?? [];
        $found = array_values(array_filter($attachments));

        if (count($found) !== count($keys)) {
            throw ValidationException::withMessages([
                'attachments' => __('mailbox::mailbox.forward_attachments_gone'),
            ]);
        }

        return $found;
    }
}
