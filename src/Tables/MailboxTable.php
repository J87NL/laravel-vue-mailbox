<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tables;

use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

abstract class MailboxTable extends AbstractTable
{
    private const string COMPACT_CELL = 'px-3 py-2';

    public function make(): TableBuilder
    {
        return parent::make()->class(self::COMPACT_CELL, self::COMPACT_CELL);
    }
}
