<?php

namespace App\Service\Contracts;

/**
 * Implement this interface to become available in \App\Service\ServiceRegistry
 */
interface SwitchableService
{
    /**
     * @return string[] list of classes this service can handle
     */
    public function getSupportedTypes(): array;
}
