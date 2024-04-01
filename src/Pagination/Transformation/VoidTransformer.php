<?php

declare(strict_types=1);

namespace App\Pagination\Transformation;

class VoidTransformer implements ResultTransformer
{
    public function transform(iterable $input): iterable
    {
        return $input;
    }
}
