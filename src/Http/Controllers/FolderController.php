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
use J87NL\Mailbox\Jobs\SyncMailboxJob;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Sync\SyncFolders;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Tables\MessagesTable;
use TranquilTools\FormBuilder\FormConfig;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderTree $tree,
    ) {}

    public function index(): RedirectResponse|Response
    {
        abort_unless(Gate::any([
            'accessAnyMailbox',
            'manageAnyMailbox',
        ]), 403);

        $first = $this->tree->mailboxes()[0] ?? null;

        if (! is_null($first)) {
            return redirect($first['url']);
        }

        if (Gate::allows('manageAnyMailbox')) {
            return redirect()->route(config('vue-mailbox.routes.name').'settings.index');
        }

        return Inertia::render(config('vue-mailbox.pages.empty'), [
            'hint' => __('vue-mailbox::mailbox.no_mailbox_hint'),
            'title' => __('vue-mailbox::mailbox.no_mailbox_title'),
        ]);
    }

    public function show(Mailbox $mailbox, SyncFolders $syncFolders, ?MailboxFolder $folder = null): Response
    {
        $folder ??= $this->inbox($mailbox, $syncFolders);

        if (is_null($folder)) {
            SyncMailboxJob::dispatch($mailbox->id);

            return $this->render($mailbox, null);
        }

        if ($folder->needsSync()) {
            SyncFolderJob::dispatch($folder->id);
        }

        return $this->render($mailbox, $folder);
    }

    public function refresh(Mailbox $mailbox, MailboxFolder $folder): RedirectResponse
    {
        SyncFolderJob::dispatch($folder->id);

        return redirect()->route(config('vue-mailbox.routes.name').'folders.show', [$mailbox, $folder]);
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

    private function inbox(Mailbox $mailbox, SyncFolders $syncFolders): ?MailboxFolder
    {
        $inbox = $mailbox->folderWithRole(FolderRole::Inbox);

        if (! is_null($inbox)) {
            return $inbox;
        }

        $syncFolders->bootstrap($mailbox);

        return $mailbox->folderWithRole(FolderRole::Inbox);
    }

    private function render(Mailbox $mailbox, ?MailboxFolder $folder): Response
    {
        $routes = config('vue-mailbox.routes.name');
        $canWork = Gate::allows('workInMailbox', $mailbox);

        return Inertia::render(config('vue-mailbox.pages.index'), [
            'composeUrl' => $canWork ? route($routes.'compose.create', $mailbox) : null,
            'contactsUrl' => route($routes.'contacts.index', $mailbox),
            'folder' => is_null($folder) ? null : [
                'id' => $folder->id,
                'label' => $folder->role->label() ?? $folder->name,
                'name' => $folder->name,
                'role' => $folder->role->value,
                'syncs' => ! $folder->skipsSync(),
            ],
            'folderForm' => fn (): ?FormConfig => $canWork ? FolderForm::make($mailbox, route($routes.'folders.store', $mailbox)) : null,
            'folders' => fn (): array => $this->tree->for($mailbox),
            'mailbox' => [
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
            ],
            'mailboxes' => fn (): array => config('vue-mailbox.mailbox_switcher', true) ? $this->tree->mailboxes() : [],
            'refreshUrl' => is_null($folder) ? null : route($routes.'folders.refresh', [$mailbox, $folder]),
            'settingsUrl' => Gate::allows('manageMailbox', $mailbox) ? route($routes.'settings.edit', $mailbox) : null,
            'syncNotice' => ! is_null($folder) && $folder->skipsSync() ? __('vue-mailbox::mailbox.not_synced_hint') : null,
            'syncing' => is_null($folder) ? __('mailbox::mailbox.syncing') : null,
            'table' => is_null($folder) ? null : MessagesTable::build($folder->setRelation('mailbox', $mailbox)),
        ]);
    }
}
