<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;
use J87NL\Mailbox\Models\MailboxMessage;

class MailboxNotifications
{
    public const string PROP = 'vueMailboxNotifications';

    public const int LIMIT = 10;

    public const int MAX_AGE_HOURS = 24;

    public function __construct(
        private readonly MailboxNavigation $navigation,
    ) {}

    /**
     * @return array{labels: array<string, string>, poll_seconds: int, url: string}|null
     */
    public static function shared(?Authenticatable $user): ?array
    {
        if (is_null($user) || Gate::forUser($user)->denies('accessAnyMailbox')) {
            return null;
        }

        return [
            'labels' => [
                'blocked' => __('vue-mailbox::mailbox.notifications_blocked'),
                'blocked_hint' => __('vue-mailbox::mailbox.notifications_blocked_hint'),
                'disable' => __('vue-mailbox::mailbox.notifications_disable'),
                'disable_mailbox' => __('vue-mailbox::mailbox.notifications_disable_mailbox'),
                'enable' => __('vue-mailbox::mailbox.notifications_enable'),
                'enable_mailbox' => __('vue-mailbox::mailbox.notifications_enable_mailbox'),
                'new_messages' => __('vue-mailbox::mailbox.new_messages'),
                'no_subject' => __('mailbox::mailbox.no_subject'),
                'off_hint' => __('vue-mailbox::mailbox.notifications_off_hint'),
                'on_hint' => __('vue-mailbox::mailbox.notifications_on_hint'),
            ],
            'poll_seconds' => max(10, (int) config('vue-mailbox.poll_seconds', 30)),
            'url' => route(config('vue-mailbox.routes.name').'notifications'),
        ];
    }

    /**
     * @param  list<int>|null  $mailboxIds
     * @return array{count: int, cursor: int, mailboxes: list<int>, messages: list<array{folder_id: int, folder_url: string, from_email: string|null, from_name: string|null, id: int, mailbox_id: int, mailbox_name: string, sent_at: string|null, subject: string|null, url: string}>}
     */
    public function since(Authenticatable $user, ?int $after, ?array $mailboxIds = null): array
    {
        $cursor = (int) MailboxMessage::query()->max('id');

        $mailboxes = $this->navigation->visible($user)
            ->when(! is_null($mailboxIds), fn (Collection $visible): Collection => $visible->whereIn('id', $mailboxIds))
            ->keyBy('id');

        if (is_null($after) || $after >= $cursor || $mailboxes->isEmpty()) {
            return $this->empty($cursor, $mailboxes->keys()->all());
        }

        $query = $this->unread($mailboxes, $user, $after, $cursor);
        $count = (clone $query)->count();
        $routes = config('vue-mailbox.routes.name');

        $messages = $query
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get([
                'id',
                'mailbox_id',
                'mailbox_folder_id',
                'subject',
                'from_email',
                'from_name',
                'sent_at',
            ])
            ->map(function (MailboxMessage $message) use ($mailboxes, $routes): array {
                $mailbox = $mailboxes->get($message->mailbox_id);

                return [
                    'folder_id' => $message->mailbox_folder_id,
                    'folder_url' => route($routes.'folders.show', [$mailbox, $message->mailbox_folder_id]),
                    'from_email' => $message->from_email,
                    'from_name' => $message->from_name,
                    'id' => $message->id,
                    'mailbox_id' => $message->mailbox_id,
                    'mailbox_name' => $mailbox->name,
                    'sent_at' => $message->sent_at?->toIso8601String(),
                    'subject' => $message->subject,
                    'url' => route($routes.'messages.show', [$mailbox, $message]),
                ];
            })
            ->values()
            ->all();

        return [
            'count' => $count,
            'cursor' => $cursor,
            'mailboxes' => $mailboxes->keys()->all(),
            'messages' => $messages,
        ];
    }

    /**
     * @param  Collection<int, Mailbox>  $mailboxes
     * @return Builder<MailboxMessage>
     */
    private function unread(Collection $mailboxes, Authenticatable $user, int $after, int $cursor): Builder
    {
        $inboxes = MailboxFolder::query()
            ->whereIn('mailbox_id', $mailboxes->modelKeys())
            ->where('role', FolderRole::Inbox)
            ->pluck('id')
            ->all();

        $ownAddresses = $mailboxes
            ->pluck('email')
            ->push(data_get($user, 'email'))
            ->filter(fn (mixed $email): bool => is_string($email) && $email !== '')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values()
            ->all();

        return MailboxMessage::query()
            ->where('id', '>', $after)
            ->where('id', '<=', $cursor)
            ->whereIn('mailbox_folder_id', $inboxes)
            ->where('is_seen', false)
            ->where(function (Builder $query): void {
                $query->whereNull('sent_at')
                    ->orWhere('sent_at', '>=', now()->subHours(self::MAX_AGE_HOURS));
            })
            ->where(function (Builder $query) use ($ownAddresses): void {
                $query->whereNull('from_email')
                    ->orWhereNotIn(DB::raw('lower(from_email)'), $ownAddresses);
            });
    }

    /**
     * @param  list<int>  $mailboxIds
     * @return array{count: int, cursor: int, mailboxes: list<int>, messages: list<never>}
     */
    private function empty(int $cursor, array $mailboxIds): array
    {
        return [
            'count' => 0,
            'cursor' => $cursor,
            'mailboxes' => $mailboxIds,
            'messages' => [],
        ];
    }
}
