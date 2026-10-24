<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareMailboxTranslations
{
    public const PROP = 'vue_mailbox_translations';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share(self::PROP, fn (): array => [
            ...$this->group('mailbox::mailbox'),
            ...$this->group('vue-mailbox::mailbox'),
        ]);

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function group(string $key): array
    {
        $lines = trans($key);

        return is_array($lines) ? $lines : [];
    }
}
