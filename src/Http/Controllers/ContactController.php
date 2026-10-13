<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\AddContact;
use J87NL\Mailbox\Forms\ContactForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxContact;
use J87NL\Mailbox\Models\MailboxMessage;
use J87NL\VueMailbox\Support\FolderTree;
use J87NL\VueMailbox\Tables\ContactsTable;

class ContactController extends Controller
{
    public function index(Mailbox $mailbox, FolderTree $tree): Response
    {
        return Inertia::render(config('vue-mailbox.pages.contacts_index'), [
            'backUrl' => route($this->routes().'folders.show', $mailbox),
            'createUrl' => Gate::allows('workInMailbox', $mailbox) ? route($this->routes().'contacts.create', $mailbox) : null,
            'folders' => $tree->for($mailbox),
            'mailbox' => $this->summary($mailbox),
            'table' => ContactsTable::build($mailbox),
        ]);
    }

    public function create(Mailbox $mailbox): Response
    {
        return $this->edit($mailbox, null);
    }

    public function store(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = (new ContactForm($mailbox, ''))->validate($this->normalised($request));

        $mailbox->contacts()->create($data);

        return redirect()
            ->route($this->routes().'contacts.index', $mailbox)
            ->with('success', __('mailbox::mailbox.contact_saved'));
    }

    public function edit(Mailbox $mailbox, ?MailboxContact $contact): Response
    {
        return Inertia::render(config('vue-mailbox.pages.contacts_edit'), [
            'backUrl' => route($this->routes().'contacts.index', $mailbox),
            'destroyUrl' => $contact ? route($this->routes().'contacts.destroy', [$mailbox, $contact]) : null,
            'form' => ContactForm::make(
                $mailbox,
                $contact
                    ? route($this->routes().'contacts.update', [$mailbox, $contact])
                    : route($this->routes().'contacts.store', $mailbox),
                $contact,
            ),
            'mailbox' => $this->summary($mailbox),
            'title' => $contact?->label() ?? __('mailbox::mailbox.new_contact'),
        ]);
    }

    public function update(Request $request, Mailbox $mailbox, MailboxContact $contact): RedirectResponse
    {
        $contact->update((new ContactForm($mailbox, '', $contact))->validate($this->normalised($request)));

        return redirect()
            ->route($this->routes().'contacts.index', $mailbox)
            ->with('success', __('mailbox::mailbox.contact_saved'));
    }

    public function destroy(Mailbox $mailbox, MailboxContact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()
            ->route($this->routes().'contacts.index', $mailbox)
            ->with('success', __('mailbox::mailbox.contact_deleted'));
    }

    public function fromMessage(Mailbox $mailbox, MailboxMessage $message, AddContact $add): RedirectResponse
    {
        abort_if(is_null($message->from_email), 404);

        $add->handle($mailbox, $message->from_email, $message->from_name);

        return back()->with('success', __('mailbox::mailbox.contact_added'));
    }

    private function normalised(Request $request): Request
    {
        $email = $request->input('email');

        if (! is_string($email)) {
            return $request;
        }

        $request->merge([
            'email' => Str::lower(trim($email)),
        ]);

        return $request;
    }

    /**
     * @return array{email: string, id: int, name: string}
     */
    private function summary(Mailbox $mailbox): array
    {
        return [
            'email' => $mailbox->email,
            'id' => $mailbox->id,
            'name' => $mailbox->name,
        ];
    }

    private function routes(): string
    {
        return config('vue-mailbox.routes.name');
    }
}
