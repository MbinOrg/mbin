<?php

namespace App\Service;

use App\Service\Contracts\SwitchableService;
use App\Utils\SqlHelpers;
use Psr\Log\LoggerInterface;

/**
 * This class collects all services implementing special contract-interfaces
 * and allows to get the right implementation for a given object.
 *
 * ATTENTION: every interface must be implemented by exactly one class for some type of object.
 */
class SwitchingServiceRegistry
{

    /** @var SwitchableService[] */
    private array $services;
    private array $cache = [];

    public function __construct(iterable $services) {
        $this->services = [...$services];
    }

    /**
     * @template S of SwitchableService
     * @template O of object
     * @param O $object
     * @param class-string<S> $interface
     * @return S
     */
    public function getService(object $object, string $interface): object {
        $objectClass = \get_class($object);
        if(isset($this->cache[$objectClass][$interface])) {
            return $this->cache[$objectClass][$interface];
        }

        $closestImpl = null;
        foreach ($this->services as $impl) {
            if(!\is_subclass_of($impl, $interface)) continue;
            foreach ($impl->getSupportedTypes() as $supportedClass) {
                if ($objectClass === $supportedClass || \is_subclass_of($object, $supportedClass)) {
                    $dist = $this->parentDistance($objectClass, $supportedClass);
                    if ($closestImpl === null || $closestImpl[0] > $dist) {
                        $closestImpl = [$dist, $impl];
                    }
                }
            }
        }

        if($closestImpl === null){
            throw new \LogicException('service '.$interface.' was requested for '.\get_class($object).' but no implementation is available');
        }

        $this->cache[$objectClass][$interface] = $closestImpl[1];
        return $closestImpl[1];
    }

    private function parentDistance(string $class, string $target): int {
        $cur = $class;
        $distance = 0;
        while($cur !== $target){
            $cur = \get_parent_class($cur);
            if($cur === false) {
                throw new \LogicException($class.' is not a subclass of '.$target);
            }
        }
        return $distance;
    }
}
