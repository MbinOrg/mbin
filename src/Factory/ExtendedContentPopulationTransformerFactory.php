<?php

namespace App\Factory;

use App\Entity\User;
use App\Pagination\Transformation\ExtendedContentPopulationTransformer;
use App\Repository\Criteria;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class ExtendedContentPopulationTransformerFactory
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ){}

    public function create(Criteria $criteria, ?User $loggedInUser): ExtendedContentPopulationTransformer {
        return new ExtendedContentPopulationTransformer(
            $this->entityManager,
            $this->userRepository,
            $criteria,
            $loggedInUser,
        );
    }
}