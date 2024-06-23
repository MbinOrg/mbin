<?php

declare(strict_types=1);

namespace App\Pagination\Transformation;

interface ResultTransformer
{
    public function transform(iterable $input): iterable;
}
