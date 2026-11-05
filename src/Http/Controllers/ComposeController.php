<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\SendMessage;
use J87NL\Mailbox\Actions\SuggestAddresses;
use J87NL\Mailbox\Forms\ComposeForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\Mailbox\Models\MailboxTemplate;
use J87NL\VueMailbox\Support\ComposeDefaults;
use J87NL\VueMailbox\Support\FolderListQuery;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Support\ForwardedAttachments;
use J87NL\VueMailbox\Support\MessageBodyLoader;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ComposeController extends Controller
{
    private const array SOURCE_MODES = [
        'forward',
        'reply',
        'reply_all',
    ];

    public function __construct(
        private readonly ForwardedAttachments $forwarded,
    ) {}

    public function create(Request $request, Mailbox $mailbox, MessageBodyLoader $loader, ComposeDefaults $defaults, FolderTree $tree): Response|RedirectResponse
    {
        $routes = config('vue-mailbox.routes.name');
        $source = $request->integer('reply')
            ? $mailbox->messages()->with('folder')->find($request->integer('reply'))
            : null;
        $mode = $source ? $request->query('mode', 'reply') : 'new';

        abort_unless(is_string($mode), 404);
        abort_if($source && ! in_array($mode, self::SOURCE_MODES, true), 404);

        if ($source && is_null($loader->load($source))) {
            return $loader->backToFolder($source);
        }

        $listQuery = FolderListQuery::from($request);

        return Inertia::render(config('vue-mailbox.pages.compose'), [
            'backUrl' => $source
                ? route($routes.'messages.show', [$mailbox, $source, ...$listQuery])
                : route($routes.'folders.show', $mailbox),
            'folders' => $tree->for($mailbox),
            'form' => ComposeForm::make(
                route($routes.'compose.store', [$mailbox, ...$listQuery]),
                $defaults->for($mailbox, $source, $mode),
                route($routes.'addresses', $mailbox),
                $source && $mode === 'forward' ? $this->forwarded->describe($mailbox, $source) : [],
            ),
            'mailbox' => [
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
            ],
            'mode' => $mode,
            'templates' => $this->templates($mailbox, $source),
        ]);
    }

    public function store(Request $request, Mailbox $mailbox, SendMessage $send): RedirectResponse
    {
        $forwardOf = $this->message($mailbox, $request->integer('forward_message'));
        $form = new ComposeForm('', [], null, $forwardOf ? $this->forwarded->describe($mailbox, $forwardOf) : []);

        $data = $form->validate($request);
        $data['attachments'] = $request->file('attachments', []);
        $data['forwarded_attachments'] = $forwardOf
            ? $this->forwarded->resolve($forwardOf, $form->keptForwardedAttachments($request))
            : [];

        $replyTo = $this->message($mailbox, (int) ($data['reply_to_message'] ?? 0));
        $data['quote_source'] = $forwardOf ?? $replyTo;

        try {
            $send->handle($mailbox, $data, $request->user(), $replyTo);
        } catch (TransportExceptionInterface $e) {
            throw ValidationException::withMessages([
                'body' => __('vue-mailbox::mailbox.send_failed', ['error' => $e->getMessage()]),
            ]);
        }

        return redirect()
            ->route(config('vue-mailbox.routes.name').'folders.show', [
                ...array_filter([
                    $mailbox,
                    $data['quote_source']?->folder,
                ]),
                ...FolderListQuery::from($request),
            ])
            ->with('success', __('mailbox::mailbox.sent'));
    }

    public function addresses(Request $request, Mailbox $mailbox, SuggestAddresses $suggest): JsonResponse
    {
        return response()->json($suggest->handle($mailbox, $request->string('q')->limit(100, '')->value()));
    }

    /**
     * @return list<array{id: int, name: string, url: string}>
     */
    private function templates(Mailbox $mailbox, ?MailboxMessage $source): array
    {
        return $mailbox->templates()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ])
            ->map(fn (MailboxTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'url' => route(config('vue-mailbox.routes.name').'templates.render', array_filter([
                    'mailbox' => $mailbox,
                    'reply' => $source?->id,
                    'template' => $template,
                ])),
            ])
            ->values()
            ->all();
    }

    private function message(Mailbox $mailbox, int $id): ?MailboxMessage
    {
        if ($id < 1) {
            return null;
        }

        return $mailbox->messages()
            ->with('folder')
            ->find($id);
    }
}
