<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use J87NL\Mailbox\Actions\GrantAccess;
use J87NL\Mailbox\Contracts\ResolvesAccessors;
use J87NL\Mailbox\Enums\AccessLevel;
use J87NL\Mailbox\Forms\AccessForm;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxAccess;

class MailboxAccessController extends Controller
{
    public const int SEARCH_LIMIT = 20;

    public function search(Request $request, Mailbox $mailbox, ResolvesAccessors $accessors): JsonResponse
    {
        $term = mb_substr(trim($request->string('q')->toString()), 0, 100);

        if (mb_strlen($term) < AccessForm::MIN_SEARCH_LENGTH) {
            return response()->json([]);
        }

        return response()->json(array_map(fn (array $accessor): array => [
            'label' => $accessor['label'],
            'value' => $accessor['key'],
        ], $accessors->search($term, self::SEARCH_LIMIT)));
    }

    public function store(Request $request, Mailbox $mailbox, GrantAccess $grant): RedirectResponse
    {
        $data = (new AccessForm('', ''))->validate($request);

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
