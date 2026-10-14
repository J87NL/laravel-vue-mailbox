<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use DirectoryTree\ImapEngine\Attachment;
use Illuminate\Routing\Controller;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\MessageBodyLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const INLINE_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/svg+xml',
        'image/webp',
    ];

    private const SVG_POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    public function download(Mailbox $mailbox, MailboxMessage $message, int $index, MessageBodyLoader $loader): StreamedResponse
    {
        $attachment = $loader->attachment($message->load('folder'), $index);

        abort_if(is_null($attachment), 404);

        return $this->stream($attachment, 'attachment');
    }

    public function inline(MailboxMessage $message, string $cid, MessageBodyLoader $loader): Response
    {
        $attachment = $loader->inlineAttachment($message->load('folder'), $cid);

        abort_if(is_null($attachment), 404);

        $type = $this->mediaType($attachment);

        abort_unless(in_array($type, self::INLINE_TYPES, true), 404);

        $response = $this->stream($attachment, 'inline')->setPrivate()->setMaxAge(3600);
        $response->headers->set('Content-Type', $type);

        if ($type === 'image/svg+xml') {
            $response->headers->set('Content-Security-Policy', self::SVG_POLICY);
        }

        return $response;
    }

    private function stream(Attachment $attachment, string $disposition): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($attachment): void {
                echo $attachment->contents();
            },
            $this->filename($attachment),
            [
                'Content-Type' => $disposition === 'inline' ? $attachment->contentType() : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition,
        );
    }

    private function filename(Attachment $attachment): string
    {
        $filename = trim((string) preg_replace('/[\/\\\\\x00-\x1F\x7F]+/u', '-', (string) $attachment->filename()), ' .-');

        return $filename === '' ? 'attachment' : $filename;
    }

    private function mediaType(Attachment $attachment): string
    {
        return strtolower(trim(explode(';', $attachment->contentType())[0]));
    }
}
