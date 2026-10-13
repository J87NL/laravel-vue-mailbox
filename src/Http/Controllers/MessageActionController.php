<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use J87NL\Mailbox\Actions\ArchiveMessages;
use J87NL\Mailbox\Actions\DeleteMessages;
use J87NL\Mailbox\Actions\MarkMessages;
use J87NL\Mailbox\Actions\MoveMessages;
use J87NL\Mailbox\Models\Mailbox;

class MessageActionController extends Controller
{
    public function __invoke(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['archive', 'delete', 'flag', 'move', 'read', 'unflag', 'unread'])],
            'folder_id' => ['required_if:action,move', 'nullable', 'integer'],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $messages = $mailbox->messages()->with('folder')->whereKey($data['ids'])->get();
        $back = $messages->first()?->folder;
        $user = $request->user();

        match ($data['action']) {
            'archive' => app(ArchiveMessages::class)->handle($mailbox, $messages, $user),
            'delete' => app(DeleteMessages::class)->handle($mailbox, $messages, $user),
            'flag' => app(MarkMessages::class)->flag($messages, $user),
            'move' => app(MoveMessages::class)->handle($messages, $mailbox->folders()->findOrFail($data['folder_id']), $user),
            'read' => app(MarkMessages::class)->read($messages, $user),
            'unflag' => app(MarkMessages::class)->unflag($messages, $user),
            'unread' => app(MarkMessages::class)->unread($messages, $user),
            default => abort(422),
        };

        if (in_array($data['action'], ['flag', 'read', 'unflag'], true)) {
            return back();
        }

        return redirect()->route(config('vue-mailbox.routes.name').'folders.show', array_filter([$mailbox, $back]));
    }
}
