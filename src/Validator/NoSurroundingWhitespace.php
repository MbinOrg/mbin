<?php

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NoSurroundingWhitespace extends Constraint
{
    public const string NOT_UNIQUE_ERROR = '492764ab-760d-48da-8d2e-1d5f3e5fac4c';

    protected const array ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NO_SURROUNDING_WHITESPACE_ERROR',
    ];

    public string $message = 'The value must not have whitespaces at the beginning or end.';

    #[HasNamedArguments]
    public function __construct(
        public bool $allowEmpty = false,
    ) {
        parent::__construct();
    }

    public function getTargets(): array
    {
        return [Constraint::PROPERTY_CONSTRAINT];
    }
}
