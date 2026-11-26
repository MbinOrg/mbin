<?php

declare(strict_types=1);

namespace App\Controller\Api\User;

use App\Controller\Api\BaseApi;
use App\DTO\UserSettingsDto;
use App\DTO\UserStylesDto;

class UserBaseApi extends BaseApi
{
    /**
     * Deserialize a user's settings from JSON.
     *
     * @param UserSettingsDto $dto The UserSettingsDto to modify with new values
     *
     * @return UserSettingsDto An user with only certain fields allowed to be modified by the user
     */
    protected function deserializeUserSettings(UserSettingsDto $dto): UserSettingsDto
    {
        $request = $this->request->getCurrentRequest();
        $deserialized = $this->serializer->deserialize($request->getContent(), UserSettingsDto::class, 'json');
        \assert($deserialized instanceof UserSettingsDto);

        $dto = $deserialized->mergeIntoDto($dto);

        return $dto;
    }

    /**
     * Deserialize a user's styles from JSON.
     *
     * @param UserStylesDto $dto The UserStylesDto to modify with new values
     *
     * @return UserStylesDto An user with only certain fields allowed to be modified by the user
     */
    protected function deserializeUserStyles(UserStylesDto $dto): UserStylesDto
    {
        $request = $this->request->getCurrentRequest();
        $deserialized = $this->serializer->deserialize($request->getContent(), UserStylesDto::class, 'json');
        \assert($deserialized instanceof UserStylesDto);

        $dto = $deserialized->mergeIntoDto($dto);

        return $dto;
    }
}
