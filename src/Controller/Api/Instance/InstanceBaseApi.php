<?php

declare(strict_types=1);

namespace App\Controller\Api\Instance;

use App\Controller\Api\BaseApi;
use App\DTO\InstanceDomainsRequestDto;
use App\Entity\Instance;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstanceBaseApi extends BaseApi
{
    /**
     * @return Instance[]
     */
    protected function getInstancesFromDomainsRequest(): array
    {
        /** @var InstanceDomainsRequestDto $domains */
        $domains = $this->serializer->deserialize($this->request->getCurrentRequest()->getContent(), InstanceDomainsRequestDto::class, 'json');

        if (empty($domains->domains)) {
            throw new BadRequestException('domains must not be empty');
        }

        return array_map(function ($domain) {
            $instance = $this->instanceRepository->findOneBy(['domain' => $domain]);
            if (null === $instance) {
                throw new NotFoundHttpException('instance '.$domain.' not found');
            }

            return $instance;
        }, $domains->domains);
    }
}
