<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\FetchMessageBody;
use J87NL\Mailbox\Actions\SendMessage;
use J87NL\Mailbox\Forms\ComposeForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\FolderTree;

class ComposeController extends Controller
{
    public function create(Request $request, Mailbox $mailbox, FetchMessageBody $fetchBody, FolderTree $tree): Response
    {
        $routes = config('vue-mailbox.routes.name');
        $source = $request->integer('reply') ? $mailbox->messages()->with('folder')->find($request->integer('reply')) : null;
        $mode = $request->string('mode', 'reply')->value();

        return Inertia::render(config('vue-mailbox.pages.compose'), [
            'backUrl' => $source
                ? route($routes.'messages.show', [$mailbox, $source])
                : route($routes.'folders.show', $mailbox),
            'folders' => $tree->for($mailbox),
            'form' => ComposeForm::make(
                route($routes.'compose.store', $mailbox),
                $source ? $this->defaults($mailbox, $fetchBody->handle($source), $mode) : [],
            ),
            'mailbox' => ['id' => $mailbox->id, 'name' => $mailbox->name, 'email' => $mailbox->email],
            'mode' => $source ? $mode : 'new',
        ]);
    }

    public function store(Request $request, Mailbox $mailbox, SendMessage $send): RedirectResponse
    {
        $data = (new ComposeForm(''))->validate($request);
        $data['attachments'] = $request->file('attachments', []);

        $replyTo = isset($data['reply_to_message'])
            ? $mailbox->messages()->with('folder')->find($data['reply_to_message'])
            : null;

        $send->handle($mailbox, $data, $request->user(), $replyTo);

        return redirect()
            ->route(config('vue-mailbox.routes.name').'folders.show', $mailbox)
            ->with('success', __('mailbox::mailbox.sent'));
    }

    private function defaults(Mailbox $mailbox, MailboxMessage $source, string $mode): array
    {
        $subject = (string) $source->subject;

        if ($mode === 'forward') {
            return [
                'body' => '<p></p>'.$this->quote($source),
                'subject' => Str::startsWith(Str::lower($subject), ['fw:', 'fwd:']) ? $subject : 'Fwd: '.$subject,
            ];
        }

        return [
            'body' => '<p></p>'.$this->quote($source),
            'cc' => $mode === 'reply_all' ? $this->others($mailbox, $source) : '',
            'reply_to_message' => $source->id,
            'subject' => Str::startsWith(Str::lower($subject), 're:') ? $subject : 'Re: '.$subject,
            'to' => (string) $source->from_email,
        ];
    }

    private function others(Mailbox $mailbox, MailboxMessage $source): string
    {
        return collect([...($source->to ?? []), ...($source->cc ?? [])])
            ->pluck('email')
            ->reject(fn (?string $email): bool => in_array(Str::lower((string) $email), [Str::lower($mailbox->email), Str::lower((string) $source->from_email)], true))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function quote(MailboxMessage $source): string
    {
        $sender = trim(sprintf('%s <%s>', (string) $source->from_name, (string) $source->from_email));
        $body = $source->body_text ? nl2br(e($source->body_text)) : e(strip_tags((string) $source->body_html));

        return sprintf(
            '<p>%s — %s</p><blockquote>%s</blockquote>',
            e($sender),
            e((string) $source->sent_at?->format('d-m-Y H:i')),
            $body,
        );
    }
}
