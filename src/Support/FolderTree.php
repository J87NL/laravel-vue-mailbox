<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use J87NL\Mailbox\Access\MailboxAccessResolver;
use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;

class FolderTree
{
    public function for(Mailbox $mailbox): array
    {
        $routeName = config('vue-mailbox.routes.name').'folders.show';

        return $mailbox->folders()
            ->where('selectable', true)
            ->get()
            ->sortBy(fn (MailboxFolder $folder): string => $folder->role->sortOrder().'-'.mb_strtolower($folder->path))
            ->map(fn (MailboxFolder $folder): array => [
                'depth' => $folder->depth(),
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'role' => $folder->role->value,
                'unseen' => $folder->unseen_count,
                'url' => route($routeName, [$mailbox, $folder]),
            ])
            ->values()
            ->all();
    }

    public function mailboxes(): array
    {
        $mailboxes = Mailbox::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $this->preloadAccess($mailboxes->modelKeys());

        return $mailboxes
            ->filter(fn (Mailbox $mailbox): bool => auth()->user()?->can('accessMailbox', $mailbox) ?? false)
            ->map(fn (Mailbox $mailbox): array => [
                'email' => $mailbox->email,
                'id' => $mailbox->id,
                'name' => $mailbox->name,
                'synced_at' => $mailbox->synced_at?->toIso8601String(),
                'url' => route(config('vue-mailbox.routes.name').'folders.show', $mailbox),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $mailboxIds
     */
    private function preloadAccess(array $mailboxIds): void
    {
        $user = auth()->user();

        if (is_null($user)) {
            return;
        }

        app(MailboxAccessResolver::class)->preload($user, $mailboxIds);
    }
}
