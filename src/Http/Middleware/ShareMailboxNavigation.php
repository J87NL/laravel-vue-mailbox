<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use J87NL\VueMailbox\Support\MailboxNavigation;
use J87NL\VueMailbox\Support\MailboxNotifications;
use Symfony\Component\HttpFoundation\Response;

class ShareMailboxNavigation
{
    public const PROP = 'mailboxNavigation';

    public function __construct(
        private readonly MailboxNavigation $navigation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share(self::PROP, fn (): array => $this->navigation->for($request->user()));

        Inertia::share(MailboxNotifications::PROP, fn (): ?array => MailboxNotifications::shared($request->user()));

        return $next($request);
    }
}
