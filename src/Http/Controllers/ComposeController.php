<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\SendMessage;
use J87NL\Mailbox\Forms\ComposeForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Support\DisplayTime;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Support\MessageBodyLoader;

class ComposeController extends Controller
{
    private const FORWARD_PREFIXES = [
        'fw:',
        'fwd:',
    ];

    public function create(Request $request, Mailbox $mailbox, MessageBodyLoader $loader, FolderTree $tree): Response|RedirectResponse
    {
        $routes = config('vue-mailbox.routes.name');
        $source = $request->integer('reply')
            ? $mailbox->messages()->with('folder')->find($request->integer('reply'))
            : null;
        $mode = $request->string('mode', 'reply')->value();

        if ($source && is_null($loader->load($source))) {
            return $loader->backToFolder($source);
        }

        return Inertia::render(config('vue-mailbox.pages.compose'), [
            'backUrl' => $source
                ? route($routes.'messages.show', [$mailbox, $source])
                : route($routes.'folders.show', $mailbox),
            'folders' => $tree->for($mailbox),
            'form' => ComposeForm::make(
                route($routes.'compose.store', $mailbox),
                $source ? $this->defaults($mailbox, $source, $mode) : [],
            ),
            'mailbox' => [
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
            ],
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

    /**
     * @return array<string, mixed>
     */
    private function defaults(Mailbox $mailbox, MailboxMessage $source, string $mode): array
    {
        $subject = (string) $source->subject;
        $body = sprintf('<p></p>%s', $this->quote($source));

        if ($mode === 'forward') {
            return [
                'body' => $body,
                'subject' => Str::startsWith(Str::lower($subject), self::FORWARD_PREFIXES) ? $subject : sprintf('Fwd: %s', $subject),
            ];
        }

        $recipients = $this->replyRecipients($mailbox, $source);

        return [
            'body' => $body,
            'cc' => $mode === 'reply_all' ? $this->others($mailbox, $source, $recipients) : '',
            'reply_to_message' => $source->id,
            'subject' => Str::startsWith(Str::lower($subject), 're:') ? $subject : sprintf('Re: %s', $subject),
            'to' => implode(', ', $recipients),
        ];
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
     */
    private function others(Mailbox $mailbox, MailboxMessage $source, array $recipients): string
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
            ->implode(', ');
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
