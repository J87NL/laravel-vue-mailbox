<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use J87NL\Mailbox\Actions\GrantAccess;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Forms\AccessForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;

class MailboxAccessController extends Controller
{
    public function store(Request $request, Mailbox $mailbox, GrantAccess $grant): RedirectResponse
    {
        $data = (new AccessForm(''))->validate($request);

        [$type, $id] = explode(':', $data['accessor'], 2);

        $grant->handle($mailbox, $type, $id, AccessLevel::from($data['level']), $request->user());

        return back();
    }

    public function destroy(Request $request, Mailbox $mailbox, MailboxAccess $access, GrantAccess $grant): RedirectResponse
    {
        $grant->revoke($access, $request->user());

        return back();
    }
}
