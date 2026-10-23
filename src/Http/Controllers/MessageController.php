<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\MarkMessages;
use J87NL\Mailbox\Html\MessageHtml;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAuditEntry;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Support\InlineUrls;
use J87NL\VueMailbox\Support\MessageBodyLoader;

class MessageController extends Controller
{
    private const CONVERSATION_LIMIT = 50;

    private const CONVERSATION_SCAN = 1000;

    public function __construct(
        private readonly MessageHtml $html,
        private readonly InlineUrls $inlineUrls,
    ) {}

    public function show(
        Request $request,
        Mailbox $mailbox,
        MailboxMessage $message,
        MessageBodyLoader $loader,
        MarkMessages $markMessages,
        FolderTree $tree,
    ): Response|RedirectResponse {
        if (is_null($loader->load($message->load('folder')))) {
            return $loader->backToFolder($message);
        }

        $canWork = Gate::allows('workInMailbox', $mailbox);

        if ($canWork && ! $message->is_seen) {
            $markMessages->readInBackground(collect([$message]), $request->user());
            $message->is_seen = true;
        }

        $routes = config('vue-mailbox.routes.name');

        return Inertia::render(config('vue-mailbox.pages.show'), [
            ...$this->body($message, $request->boolean('images')),
            'actionsUrl' => $canWork ? route($routes.'messages.actions', $mailbox) : null,
            'attachments' => $this->attachments($mailbox, $message),
            'backUrl' => route($routes.'folders.show', [$mailbox, $message->folder]),
            'composeUrl' => $canWork ? route($routes.'compose.create', $mailbox) : null,
            'conversation' => $this->conversation($mailbox, $message),
            'contactUrl' => $canWork && ! is_null($message->from_email) ? route($routes.'messages.contact', [$mailbox, $message]) : null,
            'folders' => $tree->for($mailbox),
            'history' => $this->history($message),
            'imageOrigin' => $request->getSchemeAndHttpHost(),
            'mailbox' => [
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
            ],
            'message' => $message->only([
                'id',
                'subject',
                'from_email',
                'from_name',
                'to',
                'cc',
                'sent_at',
                'is_flagged',
                'is_answered',
            ]),
        ]);
    }

    /**
     * @return array{hasRemoteImages: bool, html: string, remoteImages: bool}
     */
    private function body(MailboxMessage $message, bool $showImages): array
    {
        $remoteImages = $this->html->remoteImagesAllowed($showImages);

        return [
            'hasRemoteImages' => ! $remoteImages && $this->html->hasRemoteImages($message->body_html),
            'html' => $this->html->render(
                $message->body_html,
                $message->body_text,
                $this->inlineUrls->for($message),
                $remoteImages,
            ),
            'remoteImages' => $remoteImages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachments(Mailbox $mailbox, MailboxMessage $message): array
    {
        return collect($message->attachments ?? [])
            ->map(fn (array $attachment, int $index): array => [
                ...$attachment,
                'url' => route(config('vue-mailbox.routes.name').'attachments.download', [$mailbox, $message, $index]),
            ])
            ->reject(fn (array $attachment): bool => $attachment['inline'] && ! is_null($attachment['content_id']))
            ->values()
            ->all();
    }

    /**
     * @return list<array{current: bool, folder: string, from: string, id: int, sent_at: ?string, subject: ?string, url: string}>
     */
    private function conversation(Mailbox $mailbox, MailboxMessage $message): array
    {
        if (! $mailbox->conversation_view || is_null($message->thread_key)) {
            return [];
        }

        $ids = MailboxMessage::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('thread_key', $message->thread_key)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(self::CONVERSATION_SCAN)
            ->pluck('id')
            ->all();
        $start = $this->windowStart(array_search($message->id, $ids, true), count($ids));

        $messages = MailboxMessage::query()
            ->whereKey(array_slice($ids, $start, self::CONVERSATION_LIMIT))
            ->with('folder')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get([
                'id',
                'mailbox_folder_id',
                'subject',
                'from_email',
                'from_name',
                'sent_at',
            ]);

        if ($messages->count() < 2) {
            return [];
        }

        return $messages
            ->map(fn (MailboxMessage $item): array => [
                'current' => $item->id === $message->id,
                'folder' => $item->folder->role->label() ?? $item->folder->name,
                'from' => $item->from_name ?: (string) $item->from_email,
                'id' => $item->id,
                'sent_at' => $item->sent_at?->toIso8601String(),
                'subject' => $item->subject,
                'url' => route(config('vue-mailbox.routes.name').'messages.show', [$mailbox, $item]),
            ])
            ->values()
            ->all();
    }

    private function windowStart(int|false $position, int $count): int
    {
        $last = max(0, $count - self::CONVERSATION_LIMIT);

        if ($position === false) {
            return $last;
        }

        return max(0, min($position - intdiv(self::CONVERSATION_LIMIT, 2), $last));
    }

    /**
     * @return array<int, array{action: string, at: ?string, target: ?string, user: ?string}>
     */
    private function history(MailboxMessage $message): array
    {
        if (! $message->message_id) {
            return [];
        }

        return MailboxAuditEntry::query()
            ->where('mailbox_id', $message->mailbox_id)
            ->where('message_id', $message->message_id)
            ->with('user')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (MailboxAuditEntry $entry): array => [
                'action' => $entry->action->value,
                'at' => $entry->created_at?->toIso8601String(),
                'target' => $entry->target_path,
                'user' => $entry->user?->getAttribute('name'),
            ])
            ->all();
    }
}
