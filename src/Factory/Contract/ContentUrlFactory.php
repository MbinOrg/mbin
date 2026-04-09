<?php

namespace App\Factory\Contract;

use App\Entity\Contracts\ContentInterface;

/**
 * @template T of ContentInterface
 */
interface ContentUrlFactory
{

    /**
     * @param T $subject
     * @return string the AP ID globally identifying the activity of $subject
     */
    public function getActivityPubId($subject): string;

    /**
     * @param T $subject
     * @return string the URL on this host to the page showing the subject
     */
    public function getLocalUrl($subject): string;
}
