<?php

declare(strict_types=1);

namespace App\DTO;

use App\Service\ImageManager;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;

#[OA\Schema()]
class ImageUploadDto
{
    public const IMAGE_UPLOAD = 'imageUpload';
    /**
     * Only use this in cases where alt text will be added through different means.
     */
    public const IMAGE_UPLOAD_NO_ALT = 'imageUploadNoAlt';
    #[Groups([
        self::IMAGE_UPLOAD,
    ])]
    public ?string $alt = null;
    #[Groups([
        self::IMAGE_UPLOAD,
        self::IMAGE_UPLOAD_NO_ALT,
    ])]
    #[OA\Property(
        type: 'string', format: 'binary', nullable: true,
        encoding: new OA\Encoding(property: 'uploadImage', contentType: ImageManager::IMAGE_MIMETYPE_STR)
    )]
    public ?UploadedFile $uploadImage = null;
}
