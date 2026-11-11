<?php

declare(strict_types=1);

namespace J87NL\VueMailbox\Forms;

use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\FormConfig;

class SpamAllowlistForm extends AbstractForm
{
    public const string PATTERN_RULE = '/^@?([^\s@]+@)?[^\s@]+\.[^\s@]+$/';

    public function __construct(
        private readonly string $action,
    ) {}

    public function configure(FormConfig $form): void
    {
        $form->action($this->action)->method('POST')->resetOnSuccess()->columns(2);
    }

    public function fields(): array
    {
        return [
            Text::make('pattern')
                ->label(__('vue-mailbox::mailbox.spam_allowlist_pattern'))
                ->placeholder(__('vue-mailbox::mailbox.spam_allowlist_placeholder'))
                ->rules('required', 'string', sprintf('regex:%s', self::PATTERN_RULE))
                ->maxLength(255),
            Submit::make('add')->label(__('vue-mailbox::mailbox.spam_allowlist_add'))->columnSpanFull(),
        ];
    }
}
