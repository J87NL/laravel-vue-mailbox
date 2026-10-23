<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\GrantAccess;
use J87NL\Mailbox\Actions\SaveMailbox;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Forms\AccessForm;
use J87NL\Mailbox\Forms\MailboxForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\VueMailbox\Tables\MailboxAccessTable;
use J87NL\VueMailbox\Tables\MailboxesTable;

class MailboxSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(config('vue-mailbox.pages.settings_index'), [
            'createUrl' => Gate::allows('manageMailboxes') ? route($this->routes().'settings.create') : null,
            'table' => MailboxesTable::build(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render(config('vue-mailbox.pages.settings_edit'), [
            'accessForm' => null,
            'accessTable' => null,
            'backUrl' => route($this->routes().'settings.index'),
            'destroyUrl' => null,
            'form' => MailboxForm::make(route($this->routes().'settings.store')),
            'title' => __('mailbox::mailbox.new_mailbox'),
        ]);
    }

    public function store(Request $request, SaveMailbox $save, GrantAccess $grant): RedirectResponse
    {
        $data = (new MailboxForm(''))->validate($request);

        $user = $request->user();

        $mailbox = DB::transaction(function () use ($data, $grant, $save, $user): Mailbox {
            $mailbox = $save->handle(null, $data, $user);

            if ($user instanceof Model && Gate::denies('manageMailbox', $mailbox)) {
                $grant->handle($mailbox, $user->getMorphClass(), $user->getKey(), AccessLevel::Manage, $user);
            }

            return $mailbox;
        });

        return redirect()
            ->route($this->routes().'settings.edit', $mailbox)
            ->with('success', __('mailbox::mailbox.saved'));
    }

    public function edit(Mailbox $mailbox): Response
    {
        return Inertia::render(config('vue-mailbox.pages.settings_edit'), [
            'accessForm' => AccessForm::make(route($this->routes().'settings.access.store', $mailbox)),
            'accessTable' => MailboxAccessTable::build($mailbox),
            'backUrl' => route($this->routes().'settings.index'),
            'destroyUrl' => Gate::allows('manageMailboxes') ? route($this->routes().'settings.destroy', $mailbox) : null,
            'form' => MailboxForm::make(route($this->routes().'settings.update', $mailbox), $mailbox),
            'title' => $mailbox->name,
        ]);
    }

    public function update(Request $request, Mailbox $mailbox, SaveMailbox $save): RedirectResponse
    {
        $data = (new MailboxForm('', $mailbox))->validate($request);

        $save->handle($mailbox, $data, $request->user());

        return back()->with('success', __('mailbox::mailbox.saved'));
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

        return redirect()->route($this->routes().'settings.index');
    }

    private function routes(): string
    {
        return config('vue-mailbox.routes.name');
    }
}
