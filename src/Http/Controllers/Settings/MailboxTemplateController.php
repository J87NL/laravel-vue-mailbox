<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailbox\Actions\SaveTemplate;
use J87NL\Mailbox\Forms\TemplateForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxTemplate;

class MailboxTemplateController extends Controller
{
    public function create(Mailbox $mailbox): Response
    {
        return $this->page($mailbox, null);
    }

    public function store(Request $request, Mailbox $mailbox, SaveTemplate $save): RedirectResponse
    {
        $save->handle($mailbox, null, (new TemplateForm(''))->validate($request));

        return $this->backToSettings($mailbox, __('mailbox::mailbox.template_saved'));
    }

    public function edit(Mailbox $mailbox, MailboxTemplate $template): Response
    {
        return $this->page($mailbox, $template);
    }

    public function update(Request $request, Mailbox $mailbox, MailboxTemplate $template, SaveTemplate $save): RedirectResponse
    {
        $save->handle($mailbox, $template, (new TemplateForm('', $template))->validate($request));

        return $this->backToSettings($mailbox, __('mailbox::mailbox.template_saved'));
    }

    public function destroy(Mailbox $mailbox, MailboxTemplate $template): RedirectResponse
    {
        $template->delete();

        return $this->backToSettings($mailbox, __('mailbox::mailbox.template_deleted'));
    }

    private function page(Mailbox $mailbox, ?MailboxTemplate $template): Response
    {
        $routes = config('vue-mailbox.routes.name');

        return Inertia::render(config('vue-mailbox.pages.settings_template', 'mailbox/Settings/Template'), [
            'backUrl' => route($routes.'settings.edit', $mailbox),
            'destroyUrl' => $template ? route($routes.'settings.templates.destroy', [$mailbox, $template]) : null,
            'form' => TemplateForm::make(
                $template
                    ? route($routes.'settings.templates.update', [$mailbox, $template])
                    : route($routes.'settings.templates.store', $mailbox),
                $template,
            ),
            'title' => is_null($template) ? __('mailbox::mailbox.new_template') : $template->name,
        ]);
    }

    private function backToSettings(Mailbox $mailbox, string $message): RedirectResponse
    {
        return redirect()
            ->route(config('vue-mailbox.routes.name').'settings.edit', $mailbox)
            ->with('success', $message);
    }
}
