<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Image;
use App\Message\Contracts\AsyncSlowMessageInterface;

/**
 * Will check for every provided image if it is not referenced anywhere
 * and if this is the case deletes the entity from the database & the file from storage.
 */
class DeleteImageV2Message implements AsyncSlowMessageInterface
{
    /**
     * @param array<string, ?string> $images a list of mappings where the key is the sha256 (hex) of the image entity and the value is its filepath
     */
    public function __construct(
        public array $images,
    ) {
    }

    public static function fromImage(Image $img): self
    {
        return new self([bin2hex($img->sha256) => $img->filePath]);
    }
}
