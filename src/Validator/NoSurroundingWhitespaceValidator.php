<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoSurroundingWhitespaceValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!\is_string($value) && null !== $value) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!$constraint instanceof NoSurroundingWhitespace) {
            throw new UnexpectedTypeException($constraint, NoSurroundingWhitespace::class);
        }

        if (null === $value) {
            return;
        }

        if ('' === $value) {
            if ($constraint->allowEmpty) {
                return;
            }
            $this->context->buildViolation($constraint->message)
                ->setCode(NoSurroundingWhitespace::NOT_UNIQUE_ERROR)
                ->addViolation();
        }

        if(\trim($value) !== $value) {
            $this->context->buildViolation($constraint->message)
                ->setCode(NoSurroundingWhitespace::NOT_UNIQUE_ERROR)
                ->addViolation();
        }
    }
}
