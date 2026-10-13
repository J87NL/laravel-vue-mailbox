<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
