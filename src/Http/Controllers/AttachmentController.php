<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use DirectoryTree\ImapEngine\Attachment;
use Illuminate\Routing\Controller;
use J87NL\Mailbox\Actions\FetchMessageBody;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const INLINE_TYPES = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];

    public function download(Mailbox $mailbox, MailboxMessage $message, int $index, FetchMessageBody $fetch): StreamedResponse
    {
        $attachment = $fetch->attachment($message->load('folder'), $index);

        abort_if(is_null($attachment), 404);

        return $this->stream($attachment, 'attachment');
    }

    public function inline(MailboxMessage $message, string $cid, FetchMessageBody $fetch): Response
    {
        $attachment = $fetch->inlineAttachment($message->load('folder'), $cid);

        abort_if(is_null($attachment) || ! in_array($attachment->contentType(), self::INLINE_TYPES, true), 404);

        return $this->stream($attachment, 'inline')->setPrivate()->setMaxAge(3600);
    }

    private function stream(Attachment $attachment, string $disposition): StreamedResponse
    {
        $filename = $attachment->filename() ?: 'attachment';

        return response()->streamDownload(
            fn () => print $attachment->contents(),
            $filename,
            [
                'Content-Type' => $disposition === 'inline' ? $attachment->contentType() : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition,
        );
    }
}
