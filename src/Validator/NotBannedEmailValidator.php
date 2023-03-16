<?php

namespace App\Validator;

use App\Repository\BanRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotBannedEmailValidator extends ConstraintValidator
{
    public function __construct(
        private BanRepository $banRepository,
    ) {
    }
    
    public function validate($value, Constraint $constraint)
    {
        /* @var App\Validator\NotBannedEmail $constraint */

        if (null === $value || '' === $value) {
            return;
        }

        $targetedBan = $this->banRepository->findOneBy(['email' => $value]);
        if (null !== $targetedBan) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
