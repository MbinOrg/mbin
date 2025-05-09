<?php

declare(strict_types=1);

namespace App\Utils;

class RegPatterns
{
    public const MAGAZINE_NAME = '/^[a-zA-Z0-9_]{2,25}$/';
    public const USERNAME = '/^[a-zA-Z0-9_\-]{1,30}$/';
    public const LOCAL_MAGAZINE = '/^@\w{2,25}\b/';
    public const LOCAL_USER = '/^@[a-zA-Z0-9_-]{1,30}\b/';
    public const AP_MAGAZINE = '/^(!\w{2,25})(@)(([a-z0-9|-]+\.)*[a-z0-9|-]+\.[a-z]+)/';
    public const AP_USER = '/^(@\w{1,30})(@)(([a-z0-9|-]+\.)*[a-z0-9|-]+\.[a-z]+)/';
    public const LOCAL_TAG_REGEX = '\B#([\w][\w\p{M}·・]+)';
    public const LOCAL_TAG = '/'.self::LOCAL_TAG_REGEX.'/u';
    public const COMMUNITY_REGEX = '\B!(\w{1,30})(?:@)?((?:[\pL\pN\pS\pM\-\_]++\.)+[\pL\pN\pM]++|[a-z0-9\-\_]++)?';
    public const MENTION_REGEX = '\B@([a-zA-Z0-9\-\_]{1,30})(?:@)?((?:[\pL\pN\pS\pM\-\_]++\.)+[\pL\pN\pM]++|[a-z0-9\-\_]++)?';
    public const LOCAL_USER_REGEX = '/(?<!\/)\B@([a-zA-Z0-9_-]{1,30}@?)/u';
    public const REMOTE_USER_REGEX = '/(?<!\/)\B@([a-zA-Z0-9._-]+@?)(@)(([\pL\pN\pS\pM\-\_]++\.)+[\pL\pN\pM]++|[a-z0-9\-\_]++)/u';
    public const INVALID_TAG_CHARACTERS = '/[(){}\/:@]/';
    public const URL_SEPARATOR_REGEX = '/[ \n\[\]()]/';
}
