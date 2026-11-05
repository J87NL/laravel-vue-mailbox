<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use J87NL\VueMailbox\Tables\MessagesTable;

class FolderListQuery
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Request $request): array
    {
        return collect($request->query())
            ->filter(fn (mixed $value, int|string $key): bool => Str::startsWith((string) $key, sprintf('%s_', MessagesTable::NAME)))
            ->all();
    }
}
