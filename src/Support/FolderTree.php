<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use J87NL\Mailbox\Models\Mailbox;
use J87NL\Mailbox\Models\MailboxFolder;

class FolderTree
{
    public function for(Mailbox $mailbox): array
    {
        $routeName = config('vue-mailbox.routes.name').'folders.show';

        return $mailbox->loadMissing('folders')->folders
            ->where('selectable', true)
            ->sortBy(fn (MailboxFolder $folder): string => $folder->role->sortOrder().'-'.mb_strtolower($folder->path))
            ->map(fn (MailboxFolder $folder): array => [
                'depth' => $folder->depth(),
                'id' => $folder->id,
                'label' => $folder->role->label() ?? $folder->name,
                'name' => $folder->name,
                'path' => $folder->path,
                'role' => $folder->role->value,
                'syncs' => ! $folder->skipsSync(),
                'unseen' => $folder->unseen_count,
                'url' => route($routeName, [$mailbox, $folder]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{active_path: string, email: string, id: int, name: string, synced_at: string|null, unseen: int, url: string}>
     */
    public function mailboxes(): array
    {
        return app(MailboxNavigation::class)->for(auth()->user());
    }
}
