<?php

declare(strict_types=1);

namespace App\Controller\Api\Domain;

use App\Controller\Api\BaseApi;
use App\DTO\DomainDto;
use App\Entity\Domain;
use App\Factory\DomainFactory;
use Symfony\Contracts\Service\Attribute\Required;

class DomainBaseApi extends BaseApi
{
    private readonly DomainFactory $factory;

    #[Required]
    public function setFactory(DomainFactory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Serialize a domain to JSON.
     */
    protected function serializeDomain(DomainDto|Domain $dto): DomainDto
    {
        $response = $dto instanceof Domain ? $this->factory->createDto($dto) : $dto;

        return $response;
    }
}
