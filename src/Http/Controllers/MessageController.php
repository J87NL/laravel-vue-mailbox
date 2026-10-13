<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\MarkMessages;
use J87NL\Mailbox\Html\MessageHtml;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAuditEntry;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Support\MessageBodyLoader;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageHtml $html,
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
            $markMessages->read(collect([$message]), $request->user());
            $message->is_seen = true;
        }

        $routes = config('vue-mailbox.routes.name');

        return Inertia::render(config('vue-mailbox.pages.show'), [
            ...$this->body($message, $request->boolean('images')),
            'actionsUrl' => $canWork ? route($routes.'messages.actions', $mailbox) : null,
            'attachments' => $this->attachments($mailbox, $message),
            'backUrl' => route($routes.'folders.show', [$mailbox, $message->folder]),
            'composeUrl' => $canWork ? route($routes.'compose.create', $mailbox) : null,
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
        $inlineRoute = config('vue-mailbox.routes.name').'inline';
        $expires = now()->addMinutes((int) config('vue-mailbox.signed_url_minutes'));

        return [
            'hasRemoteImages' => ! $remoteImages && $this->html->hasRemoteImages($message->body_html),
            'html' => $this->html->render(
                $message->body_html,
                $message->body_text,
                fn (string $cid): string => URL::temporarySignedRoute($inlineRoute, $expires, [$message, $cid]),
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
