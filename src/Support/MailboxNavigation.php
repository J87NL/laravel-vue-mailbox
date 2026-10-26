<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Enums\FolderRole;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;

class MailboxNavigation
{
    /**
     * @return list<array{active_path: string, email: string, id: int, name: string, synced_at: string|null, unseen: int, url: string}>
     */
    public function for(?Authenticatable $user): array
    {
        if (is_null($user)) {
            return [];
        }

        $visible = $this->visible($user);

        $unseen = $this->unseenInInbox($visible->modelKeys());
        $routes = config('vue-mailbox.routes.name');

        return $visible
            ->map(fn (Mailbox $mailbox): array => [
                'active_path' => sprintf('/%s/', trim(sprintf('%s/%d', trim((string) config('vue-mailbox.routes.prefix'), '/'), $mailbox->id), '/')),
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
                'synced_at' => $mailbox->synced_at?->toIso8601String(),
                'unseen' => $unseen[$mailbox->id] ?? 0,
                'url' => route($routes.'folders.show', $mailbox),
            ])
            ->all();
    }

    /**
     * @return Collection<int, Mailbox>
     */
    public function visible(Authenticatable $user): Collection
    {
        $mailboxes = Mailbox::query()
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        app(MailboxAccessResolver::class)->preload($user, $mailboxes->modelKeys());

        return $mailboxes
            ->filter(fn (Mailbox $mailbox): bool => Gate::forUser($user)->allows('accessMailbox', $mailbox))
            ->values();
    }

    /**
     * @param  array<int, int>  $mailboxIds
     * @return array<int, int>
     */
    private function unseenInInbox(array $mailboxIds): array
    {
        if ($mailboxIds === []) {
            return [];
        }

        return MailboxFolder::query()
            ->whereIn('mailbox_id', $mailboxIds)
            ->where('role', FolderRole::Inbox)
            ->pluck('unseen_count', 'mailbox_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
