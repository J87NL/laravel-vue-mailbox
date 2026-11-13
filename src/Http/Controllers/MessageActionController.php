<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use J87NL\Mailbox\Actions\ArchiveMessages;
use J87NL\Mailbox\Actions\DeleteMessages;
use J87NL\Mailbox\Actions\MarkJunk;
use J87NL\Mailbox\Actions\MarkMessages;
use J87NL\Mailbox\Actions\MoveMessages;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\ConversationMessages;
use J87NL\VueMailbox\Support\FolderListQuery;
use J87NL\VueMailbox\Support\SpamSuggestion;

class MessageActionController extends Controller
{
    public function __invoke(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $request->validate([
            'action' => [
                'required',
                Rule::in([
                    'archive',
                    'delete',
                    'delete_conversation',
                    'flag',
                    'junk',
                    'move',
                    'not_junk',
                    'read',
                    'unflag',
                    'unread',
                ]),
            ],
            'folder_id' => [
                'required_if:action,move',
                'nullable',
                'integer',
            ],
            'ids' => [
                'required',
                'array',
                'max:500',
            ],
            'ids.*' => [
                'integer',
            ],
        ]);

        $messages = $mailbox->messages()->with('folder')->whereKey($data['ids'])->get();
        $back = $messages->first()?->folder;
        $user = $request->user();

        abort_unless($this->fitsFolders($mailbox, $data['action'], $messages), 422);
        abort_if($data['action'] === 'delete_conversation' && $messages->count() !== 1, 422);

        match ($data['action']) {
            'archive' => app(ArchiveMessages::class)->handle($mailbox, $messages, $user),
            'delete' => app(DeleteMessages::class)->handle($mailbox, $messages, $user),
            'delete_conversation' => app(DeleteMessages::class)->handle($mailbox, app(ConversationMessages::class)->toDelete($mailbox, $messages->sole()), $user),
            'flag' => app(MarkMessages::class)->flag($messages, $user),
            'junk' => app(MarkJunk::class)->junk($mailbox, $messages, $user),
            'not_junk' => app(MarkJunk::class)->notJunk($mailbox, $messages, $user),
            'move' => app(MoveMessages::class)->handle($messages, $mailbox->folders()->findOrFail($data['folder_id']), $user),
            'read' => app(MarkMessages::class)->read($messages, $user),
            'unflag' => app(MarkMessages::class)->unflag($messages, $user),
            'unread' => app(MarkMessages::class)->unread($messages, $user),
            default => abort(422),
        };

        if (in_array($data['action'], [
            'flag',
            'read',
            'unflag',
        ], true)) {
            return back();
        }

        return redirect()->route(config('vue-mailbox.routes.name').'folders.show', [
            ...array_filter([$mailbox, $back]),
            ...FolderListQuery::from($request),
        ]);
    }

    /**
     * @param  Collection<int, MailboxMessage>  $messages
     */
    private function fitsFolders(Mailbox $mailbox, string $action, Collection $messages): bool
    {
        if ($action === 'junk') {
            return ! $messages->contains(fn (MailboxMessage $message): bool => in_array($message->folder->role, [
                FolderRole::Drafts,
                FolderRole::Sent,
            ], true));
        }

        if ($action === 'not_junk') {
            return $messages->every(fn (MailboxMessage $message): bool => $message->folder->role === FolderRole::Junk || SpamSuggestion::offersNotJunk($mailbox, $message));
        }

        return true;
    }
}
