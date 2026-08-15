<?php

namespace App\Entity\Traits;

trait ExtendedContentTrait
{
    /**
     * @var array<string, mixed>
     * May contain the following properties:
     *   - boostUsers:
     *     - desc: list of (followed) users who boosted this item + when it was boosted
     *     - value: ['user' => User, 'time' => DateTimeImmutable][]
     */
    public array $extendedContentProperties = [];
}