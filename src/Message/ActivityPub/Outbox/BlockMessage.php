<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class BlockMessage implements ActivityPubOutboxInterface
{
    /**
     * Exactly of the parameters must not be null.
     *
     * @param int|null $magazineBanId if the block has a magazine as a target
     * @param int|null $bannedUserId  if the block has the instance as a target
     * @param int|null $actor         the user issuing the ban, only used for instance bans, otherwise MagazineBan::$bannedBy is used
     *
     * @see MagazineBan::$bannedBy
     */
    public function __construct(
        public ?int $magazineBanId,
        public ?int $bannedUserId,
        public ?int $actor,
    ) {
    }
}
