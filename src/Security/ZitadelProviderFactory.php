<?php

declare(strict_types=1);

namespace App\Security;

use App\Provider\Zitadel;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ThePhpLeague\OAuth2\Client\Provider\ProviderFactory;
use ThePhpLeague\OAuth2\Client\Provider\ProviderInterface;

class ZitadelProviderFactory extends ProviderFactory
{
    protected function createProvider(array $options): ProviderInterface
    {
        return new Zitadel($options);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefined(['base_url']);
    }
}
