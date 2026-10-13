<?php

declare(strict_types=1);

return [

    'routes' => [
        'prefix' => 'mailbox',
        'name' => 'mailbox.',
        'middleware' => ['web', 'auth'],
    ],

    'pages' => [
        'compose' => 'Mailbox/Compose',
        'contacts_edit' => 'Mailbox/Contacts/Edit',
        'contacts_index' => 'Mailbox/Contacts/Index',
        'index' => 'Mailbox/Index',
        'settings_edit' => 'Mailbox/Settings/Edit',
        'settings_index' => 'Mailbox/Settings/Index',
        'show' => 'Mailbox/Show',
    ],

    'per_page' => 50,

    'poll_seconds' => 30,

    'signed_url_minutes' => 30,

];
