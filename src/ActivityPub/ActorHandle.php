<?php

declare(strict_types=1);

namespace App\ActivityPub;

class ActorHandle
{
    public const HANDLE_PATTERN = '/^(?P<prefix>[@!])?(?P<name>[\w\-\.]+)@(?P<host>[\w\.\-]+)(?P<port>:[\d]+)?$/';

    public function __construct(
        public ?string $prefix = null,
        public ?string $name = null,
        public ?string $host = null,
        public ?int $port = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->formatWithPrefix($this->prefix);
    }

    public static function parse(string $handle): ?static
    {
        if (preg_match(static::HANDLE_PATTERN, $handle, $match)) {
            $new = new static(
                $match['prefix'] ?? null,
                $match['name'],
                $match['host']
            );
            $new->setPort($match['port'] ?? null);

            return $new;
        }

        return null;
    }

    public static function isHandle(string $handle)
    {
        if (preg_match(static::HANDLE_PATTERN, $handle, $matches)) {
            return !empty($matches['name']) && !empty($matches['host']);
        }

        return false;
    }

    public function isValid(): bool
    {
        return static::isHandle((string) $this);
    }

    /** @return string port as string in the format ':9000' or empty string if it's null */
    public function getPortString(): string
    {
        return !empty($this->port) ? ':'.$this->port : '';
    }

    /** @param int|string|null $port port as either plain int or string formatted like ':9000' */
    public function setPort(int|string|null $port)
    {
        if (\is_string($port)) {
            $this->port = \intval(ltrim($port, ':'));
        } else {
            $this->port = $port;
        }

        return $this;
    }

    /** @return string the handle's domain and optionally port in the format `host[:port]` */
    public function getDomain(): string
    {
        return $this->host.$this->getPortString();
    }

    /** @param ?string $domain the domain in the format `host[:port]` to set both handle's host and port */
    public function setDomain(?string $domain)
    {
        $url = parse_url($domain ?? '');

        $this->host = $url['host'] ?? null;
        $this->port = $url['port'] ?? null;

        return $this;
    }

    public function formatWithPrefix(?string $prefix): string
    {
        return "{$prefix}{$this->name}@{$this->getDomain()}";
    }

    /** @return string handle in the form `name@domain` */
    public function plainHandle(): string
    {
        return $this->formatWithPrefix('');
    }

    /** @return string handle in the form `@name@domain` */
    public function atHandle(): string
    {
        return $this->formatWithPrefix('@');
    }

    /** @return string handle in the form `!name@domain` */
    public function bangHandle(): string
    {
        return $this->formatWithPrefix('!');
    }
}
