<?php

declare(strict_types=1);

namespace App\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property('softwareName', example: 'mbin', type: 'string'),
        new OA\Property('softwareVersion', example: '2.0.0', type: 'string'),
        new OA\Property('softwareRepository', example: 'https://github.com/MbinOrg/mbin', type: 'string'),
        new OA\Property('websiteDomain', example: 'https://mbin.social', type: 'string'),
        new OA\Property('websiteContactEmail', example: 'contact@mbin.social', type: 'string'),
        new OA\Property('websiteTitle', example: 'Mbin', type: 'string'),
        new OA\Property('websiteOpenRegistrations', example: true, type: 'boolean'),
        new OA\Property('websiteFederationEnabled', example: true, type: 'boolean'),
        new OA\Property('websiteDefaultLang', example: 'en', type: 'string'),
    ]
)]
class InfoSchema
{
}
