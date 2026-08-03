<?php

namespace App\Utils;

use Symfony\Component\HttpFoundation\Request;

class Polyfills
{
    /**
     * Gets a "parameter" value from the request.
     *
     * This function uses the original behavior from https://github.com/symfony/symfony/blob/7.3/src/Symfony/Component/HttpFoundation/Request.php .
     * It was deprecated and removed, but it is easier to just revive it than clean up our code.
     */
    public static function requestParam(Request $req, string $key, mixed $default = null): mixed
    {
        if ($req !== $result = $req->attributes->get($key, $req)) {
            return $result;
        }

        if ($req->query->has($key)) {
            return $req->query->all()[$key];
        }

        if ($req->request->has($key)) {
            return $req->request->all()[$key];
        }

        return $default;
    }
}
