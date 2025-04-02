<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry\Comments;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\EntryCommentRequestDto;
use App\DTO\EntryCommentResponseDto;
use App\DTO\ImageUploadDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Factory\EntryCommentFactory;
use App\Service\EntryCommentManager;
use App\Service\ImageManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntryCommentsCreateApi extends EntriesBaseApi
{
    use PrivateContentTrait;

    #[OA\Response(
        response: 201,
        description: 'Comment created',
        content: new Model(type: EntryCommentResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'The request body was invalid or the comment you are replying to does not belong to the entry you are trying to add the new comment to.',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not permitted to add comments to this entry',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Entry or parent comment not found',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Parameter(
        name: 'entry_id',
        in: 'path',
        description: 'Entry to which the new comment will belong',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(content: new Model(
        type: EntryCommentRequestDto::class,
        groups: [
            'common',
            'comment',
            'no-upload',
        ]
    ))]
    #[OA\Tag(name: 'entry_comment')]
    #[Security(name: 'oauth2', scopes: ['entry_comment:create'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY_COMMENT:CREATE')]
    #[IsGranted('comment', subject: 'entry')]
    public function __invoke(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'comment_id')]
        ?EntryComment $parent,
        EntryCommentManager $manager,
        EntryCommentFactory $factory,
        ValidatorInterface $validator,
        RateLimiterFactory $apiCommentLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiCommentLimiter);

        if (!$this->isGranted('create_content', $entry->magazine)) {
            throw new AccessDeniedHttpException();
        }

        if ($parent && $parent->entry->getId() !== $entry->getId()) {
            throw new BadRequestHttpException('The parent comment does not belong to that entry!');
        }
        $dto = $this->deserializeComment();

        $dto->entry = $entry;
        $dto->magazine = $entry->magazine;
        $dto->parent = $parent;

        $errors = $validator->validate($dto);
        if (\count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        // Rate limiting already taken care of
        $comment = $manager->create($dto, $this->getUserOrThrow(), rateLimit: false);
        $dto = $factory->createDto($comment);
        $dto->parent = $parent;

        return new JsonResponse(
            $this->serializeComment($dto, $this->tagLinkRepository->getTagsOfEntryComment($comment)),
            status: 201,
            headers: $headers
        );
    }

    #[OA\Response(
        response: 201,
        description: 'Comment created',
        content: new Model(type: EntryCommentResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'The request body was invalid or the comment you are replying to does not belong to the entry you are trying to add the new comment to.',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not permitted to add comments to this entry',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Entry or parent comment not found',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Parameter(
        name: 'entry_id',
        in: 'path',
        description: 'Entry to which the new comment will belong',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(content: new OA\MediaType(
        'multipart/form-data',
        schema: new OA\Schema(
            ref: new Model(
                type: EntryCommentRequestDto::class,
                groups: [
                    'common',
                    'comment',
                    ImageUploadDto::IMAGE_UPLOAD,
                ]
            )
        ),
        encoding: [
            'imageUpload' => [
                'contentType' => ImageManager::IMAGE_MIMETYPE_STR,
            ],
        ]
    ))]
    #[OA\Tag(name: 'entry_comment')]
    #[Security(name: 'oauth2', scopes: ['entry_comment:create'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY_COMMENT:CREATE')]
    #[IsGranted('comment', subject: 'entry')]
    public function uploadImage(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        #[MapEntity(id: 'comment_id')]
        ?EntryComment $parent,
        EntryCommentManager $manager,
        EntryCommentFactory $factory,
        ValidatorInterface $validator,
        RateLimiterFactory $apiImageLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiImageLimiter);

        $image = $this->handleUploadedImage();

        if (!$this->isGranted('create_content', $entry->magazine)) {
            throw new AccessDeniedHttpException();
        }

        if ($parent && $parent->entry->getId() !== $entry->getId()) {
            throw new BadRequestHttpException('The parent comment does not belong to that entry!');
        }

        $dto = $this->deserializeCommentFromForm();

        $dto->entry = $entry;
        $dto->magazine = $entry->magazine;
        $dto->parent = $parent;
        $dto->image = $this->imageFactory->createDto($image);

        $errors = $validator->validate($dto);
        if (\count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        // Rate limiting already taken care of
        $comment = $manager->create($dto, $this->getUserOrThrow(), rateLimit: false);
        $dto = $factory->createDto($comment);
        $dto->parent = $parent;

        return new JsonResponse(
            $this->serializeComment($dto, $this->tagLinkRepository->getTagsOfEntryComment($comment)),
            status: 201,
            headers: $headers
        );
    }
}
