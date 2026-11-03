<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use J87NL\Mailbox\Actions\RenderTemplate;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxTemplate;

class TemplateController extends Controller
{
    public function __invoke(Request $request, Mailbox $mailbox, MailboxTemplate $template, RenderTemplate $render): JsonResponse
    {
        $data = $request->validate([
            'reply' => [
                'nullable',
                'integer',
            ],
            'subject' => [
                'nullable',
                'string',
                'max:250',
            ],
            'to' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $recipient = isset($data['to']) ? Str::lower(trim($data['to'])) : null;

        return response()->json($render->handle(
            $template,
            $request->user(),
            $recipient,
            $this->sourceName($mailbox, (int) ($data['reply'] ?? 0), $recipient),
            $data['subject'] ?? null,
        ));
    }

    private function sourceName(Mailbox $mailbox, int $reply, ?string $recipient): ?string
    {
        if ($reply < 1 || is_null($recipient)) {
            return null;
        }

        $source = $mailbox->messages()->find($reply);

        if (is_null($source) || Str::lower((string) $source->from_email) !== $recipient) {
            return null;
        }

        return $source->from_name;
    }
}
