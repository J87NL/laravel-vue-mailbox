<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Closure;
use DirectoryTree\ImapEngine\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use J87NL\Mailbox\Actions\FetchMessageBody;
use J87NL\Mailbox\Actions\ForgetMessage;
use J87NL\Mailbox\Exceptions\MessageGoneException;
use J87NL\Mailbox\Models\MailboxMessage;

class MessageBodyLoader
{
    public function __construct(
        private readonly FetchMessageBody $fetch,
        private readonly ForgetMessage $forget,
    ) {}

    public function load(MailboxMessage $message): ?MailboxMessage
    {
        return $this->unlessGone($message, fn (): MailboxMessage => $this->fetch->handle($message));
    }

    public function attachment(MailboxMessage $message, int $index): ?Attachment
    {
        return $this->unlessGone($message, fn (): ?Attachment => $this->fetch->attachment($message, $index));
    }

    /**
     * @param  array<int, int>  $indexes
     * @return array<int, Attachment|null>|null
     */
    public function attachmentsAt(MailboxMessage $message, array $indexes): ?array
    {
        return $this->unlessGone($message, fn (): array => $this->fetch->attachmentsAt($message, $indexes));
    }

    public function inlineAttachment(MailboxMessage $message, string $contentId): ?Attachment
    {
        return $this->unlessGone($message, fn (): ?Attachment => $this->fetch->inlineAttachment($message, $contentId));
    }

    public function backToFolder(MailboxMessage $message): RedirectResponse
    {
        return redirect()
            ->route(config('vue-mailbox.routes.name').'folders.show', [
                $message->mailbox_id,
                $message->mailbox_folder_id,
            ])
            ->with('error', __('mailbox::mailbox.message_gone'));
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult|null
     */
    private function unlessGone(MailboxMessage $message, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (MessageGoneException $exception) {
            Log::info($exception->getMessage(), [
                'mailbox_id' => $message->mailbox_id,
                'message_id' => $message->id,
            ]);

            $this->forget->handle($message);

            return null;
        }
    }
}
