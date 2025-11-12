<?php

declare(strict_types=1);

namespace App\Controller\Api\Bookmark;

use App\Controller\Api\BaseApi;
use App\DTO\BookmarksDto;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\BookmarkManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookmarkApiController extends BaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Add a bookmark for the subject in the default list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarksDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The specified subject does not exist',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'subject_id',
        description: 'The id of the subject to be added to the default list',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'subject_type',
        description: 'the type of the subject',
        in: 'path',
        schema: new OA\Schema(type: 'string', enum: ['entry', 'entry_comment', 'post', 'post_comment'])
    )]
    #[OA\Tag(name: 'bookmark')]
    #[Security(name: 'oauth2', scopes: ['bookmark:add'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK:ADD')]
    public function subjectBookmarkStandard(int $subject_id, string $subject_type, RateLimiterFactory $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subject = $this->entityManager->getRepository($subjectClass)->find($subject_id);
        if (null === $subject) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $this->bookmarkManager->addBookmarkToDefaultList($user, $subject);

        $dto = new BookmarksDto();
        $dto->bookmarks = $this->bookmarkListRepository->getBookmarksOfContentInterface($subject);

        return new JsonResponse($dto, status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Add a bookmark for the subject in the specified list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarksDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The specified subject or list does not exist',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'subject_id',
        description: 'The id of the subject to be added to the specified list',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'subject_type',
        description: 'the type of the subject',
        in: 'path',
        schema: new OA\Schema(type: 'string', enum: ['entry', 'entry_comment', 'post', 'post_comment'])
    )]
    #[OA\Tag(name: 'bookmark')]
    #[Security(name: 'oauth2', scopes: ['bookmark:add'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK:ADD')]
    public function subjectBookmarkToList(string $list_name, int $subject_id, string $subject_type, RateLimiterFactory $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subject = $this->entityManager->getRepository($subjectClass)->find($subject_id);
        if (null === $subject) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null === $list) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $this->bookmarkManager->addBookmark($user, $list, $subject);

        $dto = new BookmarksDto();
        $dto->bookmarks = $this->bookmarkListRepository->getBookmarksOfContentInterface($subject);

        return new JsonResponse($dto, status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Remove bookmark for the subject from the specified list',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarksDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The specified subject or list does not exist',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'subject_id',
        description: 'The id of the subject to be removed',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'subject_type',
        description: 'the type of the subject',
        in: 'path',
        schema: new OA\Schema(type: 'string', enum: ['entry', 'entry_comment', 'post', 'post_comment'])
    )]
    #[OA\Tag(name: 'bookmark')]
    #[Security(name: 'oauth2', scopes: ['bookmark:remove'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK:REMOVE')]
    public function subjectRemoveBookmarkFromList(string $list_name, int $subject_id, string $subject_type, RateLimiterFactory $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subject = $this->entityManager->getRepository($subjectClass)->find($subject_id);
        if (null === $subject) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $list = $this->bookmarkListRepository->findOneByUserAndName($user, $list_name);
        if (null === $list) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $this->bookmarkRepository->removeBookmarkFromList($user, $list, $subject);

        $dto = new BookmarksDto();
        $dto->bookmarks = $this->bookmarkListRepository->getBookmarksOfContentInterface($subject);

        return new JsonResponse($dto, status: 200, headers: $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'Remove all bookmarks for the subject',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: BookmarksDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'The specified subject does not exist',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'subject_id',
        description: 'The id of the subject to be removed',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'subject_type',
        description: 'the type of the subject',
        in: 'path',
        schema: new OA\Schema(type: 'string', enum: ['entry', 'entry_comment', 'post', 'post_comment'])
    )]
    #[OA\Tag(name: 'bookmark')]
    #[Security(name: 'oauth2', scopes: ['bookmark:remove'])]
    #[IsGranted('ROLE_OAUTH2_BOOKMARK:REMOVE')]
    public function subjectRemoveBookmarks(int $subject_id, string $subject_type, RateLimiterFactory $apiUpdateLimiter): JsonResponse
    {
        $user = $this->getUserOrThrow();
        $headers = $this->rateLimit($apiUpdateLimiter);
        $subjectClass = BookmarkManager::GetClassFromSubjectType($subject_type);
        $subject = $this->entityManager->getRepository($subjectClass)->find($subject_id);
        if (null === $subject) {
            throw new NotFoundHttpException(code: 404, headers: $headers);
        }
        $this->bookmarkRepository->removeAllBookmarksForContent($user, $subject);

        $dto = new BookmarksDto();
        $dto->bookmarks = $this->bookmarkListRepository->getBookmarksOfContentInterface($subject);

        return new JsonResponse($dto, status: 200, headers: $headers);
    }
}
