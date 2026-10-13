<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\CreateFolder;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Forms\FolderForm;
use J87NL\Mailbox\Jobs\SyncFolderJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Tables\MessagesTable;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderTree $tree,
    ) {}

    public function index(): RedirectResponse
    {
        $first = $this->tree->mailboxes()[0] ?? null;

        abort_if(is_null($first), 404);

        return redirect($first['url']);
    }

    public function show(Mailbox $mailbox, ?MailboxFolder $folder = null): Response
    {
        $folder ??= $mailbox->folderWithRole(FolderRole::Inbox);

        abort_if(is_null($folder), 404);

        if ($folder->needsSync()) {
            SyncFolderJob::dispatch($folder->id);
        }

        $routes = config('vue-mailbox.routes.name');
        $canWork = Gate::allows('workInMailbox', $mailbox);

        return Inertia::render(config('vue-mailbox.pages.index'), [
            'composeUrl' => $canWork ? route($routes.'compose.create', $mailbox) : null,
            'folder' => ['id' => $folder->id, 'name' => $folder->name, 'role' => $folder->role->value],
            'folderForm' => $canWork ? FolderForm::make($mailbox, route($routes.'folders.store', $mailbox)) : null,
            'folders' => $this->tree->for($mailbox),
            'mailbox' => ['id' => $mailbox->id, 'name' => $mailbox->name, 'email' => $mailbox->email],
            'mailboxes' => $this->tree->mailboxes(),
            'settingsUrl' => Gate::allows('manageMailbox', $mailbox) ? route($routes.'settings.edit', $mailbox) : null,
            'table' => MessagesTable::build($folder),
        ]);
    }

    public function store(Request $request, Mailbox $mailbox, CreateFolder $createFolder): RedirectResponse
    {
        $data = (new FolderForm($mailbox, ''))->validate($request);

        $parent = isset($data['parent_id'])
            ? $mailbox->folders()->findOrFail($data['parent_id'])
            : null;

        $folder = $createFolder->handle($mailbox, $data['name'], $parent, $request->user());

        return redirect()->route(config('vue-mailbox.routes.name').'folders.show', [$mailbox, $folder]);
    }
}
