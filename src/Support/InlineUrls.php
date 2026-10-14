<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\URL;
use J87NL\Mailbox\Contracts\ResolvesInlineImageUrls;
use J87NL\Mailbox\Models\MailboxMessage;

class InlineUrls implements ResolvesInlineImageUrls
{
    /**
     * @return Closure(string): string
     */
    public function for(MailboxMessage $message): Closure
    {
        $expires = now()->addMinutes((int) config('vue-mailbox.signed_url_minutes'));

        return fn (string $contentId): string => URL::temporarySignedRoute($this->routeName(), $expires, [
            $message,
            $contentId,
        ]);
    }

    public function parse(string $url): ?array
    {
        if (! str_starts_with($url, 'http')) {
            return null;
        }

        $request = Request::create($url);

        if (! URL::hasCorrectSignature($request)) {
            return null;
        }

        $named = Router::getRoutes()->getByName($this->routeName());

        if (is_null($named)) {
            return null;
        }

        $route = clone $named;

        if (! $route->matches($request)) {
            return null;
        }

        $route->bind($request);

        return [
            'content_id' => (string) $route->parameter('cid'),
            'message_id' => (int) $route->parameter('message'),
        ];
    }

    private function routeName(): string
    {
        return sprintf('%sinline', config('vue-mailbox.routes.name'));
    }
}
