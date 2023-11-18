<?php

declare(strict_types=1);

namespace App\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!\is_object($value)) {
            throw new UnexpectedTypeException($value, 'object');
        }

        if (!$constraint instanceof Unique) {
            throw new UnexpectedTypeException($constraint, Unique::class);
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($constraint->entityClass, 'e');

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($constraint->fields as $dtoField => $entityField) {
            if (\is_int($dtoField)) {
                $dtoField = $entityField;
            }

            $fieldValue = $propertyAccessor->getValue($value, $dtoField);

            if (\is_string($fieldValue)) {
                $qb->andWhere($qb->expr()->eq("LOWER(e.$entityField)", ":f_$entityField"));
                $qb->setParameter("f_$entityField", mb_strtolower($fieldValue));
            } else {
                $qb->andWhere($qb->expr()->eq("e.$entityField", ":f_$entityField"));
                $qb->setParameter("f_$entityField", $fieldValue);
            }
        }

        foreach ($constraint->idFields as $dtoField => $entityField) {
            if (\is_int($dtoField)) {
                $dtoField = $entityField;
            }

            $fieldValue = $propertyAccessor->getValue($value, $dtoField);

            if (null !== $fieldValue) {
                $qb->andWhere($qb->expr()->neq("e.$entityField", ":i_$entityField"));
                $qb->setParameter("i_$entityField", $fieldValue);
            }
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        if ($count > 0) {
            $this->context->buildViolation($constraint->message)
                ->setCode(Unique::NOT_UNIQUE_ERROR)
                ->atPath($constraint->errorPath)
                ->addViolation();
        }
    }
}
