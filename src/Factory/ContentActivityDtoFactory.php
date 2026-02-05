<?php

namespace App\Factory;

use App\DTO\ActivitiesResponseDto;
use App\Entity\Contracts\VotableInterface;
use App\Entity\Favourite;
use App\Entity\PostFavourite;
use App\Entity\PostVote;
use App\Entity\Vote;

class ContentActivityDtoFactory
{

    public function __construct(
        private readonly UserFactory $userFactory,
    ) { }

    public function createActivitiesDto(VotableInterface $subject): ActivitiesResponseDto {
        $dto = ActivitiesResponseDto::create([], [], null);
        /* @var Vote $upvote */
        foreach ($subject->getUpVotes() as $upvote) {
            $dto->boosts[] = $this->userFactory->createSmallDto($upvote->user);
        }

        if(\property_exists($subject, 'favourites')) {
            /* @var Favourite $favourite */
            foreach ($subject->favourites as $favourite) {
                $dto->upvotes[] = $this->userFactory->createSmallDto($favourite->user);
            }
        } else {
            $dto->upvotes = null;
        }

        return $dto;
    }

}
