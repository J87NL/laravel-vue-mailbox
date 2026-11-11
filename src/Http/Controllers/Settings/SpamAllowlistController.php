<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Spam\SpamAllowlist;
use J87NL\VueMailbox\Forms\SpamAllowlistForm;

class SpamAllowlistController extends Controller
{
    public function store(Request $request, Mailbox $mailbox, SpamAllowlist $allowlist): RedirectResponse
    {
        $data = (new SpamAllowlistForm(''))->validate($request);

        $allowlist->add($mailbox, $data['pattern']);

        return back()->with('success', __('vue-mailbox::mailbox.spam_allowlist_added'));
    }

    public function destroy(Mailbox $mailbox, string $entry, SpamAllowlist $allowlist): RedirectResponse
    {
        $allowlist->remove($mailbox, $mailbox->spamAllowlist()->findOrFail($entry)->pattern);

        return back()->with('success', __('vue-mailbox::mailbox.spam_allowlist_removed'));
    }
}
