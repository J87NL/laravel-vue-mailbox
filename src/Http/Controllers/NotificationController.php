<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use J87NL\VueMailbox\Support\MailboxNotifications;

class NotificationController extends Controller
{
    public function __invoke(Request $request, MailboxNotifications $notifications): JsonResponse
    {
        $data = $request->validate([
            'after' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $after = isset($data['after']) ? (int) $data['after'] : null;

        return response()->json($notifications->since($request->user(), $after));
    }
}
