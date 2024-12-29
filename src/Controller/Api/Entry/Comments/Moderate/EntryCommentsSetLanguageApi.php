<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry\Comments\Moderate;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\DTO\EntryCommentResponseDto;
use App\Entity\EntryComment;
use App\Factory\EntryCommentFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Intl\Languages;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryCommentsSetLanguageApi extends EntriesBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Comment language changed',
        content: new Model(type: EntryCommentResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 400,
        description: 'Given language is not valid',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not authorized to moderate this comment',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Comment not found',
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
        name: 'comment_id',
        in: 'path',
        description: 'The comment to change language of',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'lang',
        in: 'path',
        description: 'new language',
        schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 3),
    )]
    #[OA\Tag(name: 'moderation/entry_comment')]
    #[Security(name: 'oauth2', scopes: ['moderate:entry_comment:language'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:ENTRY_COMMENT:LANGUAGE')]
    #[IsGranted('moderate', subject: 'comment')]
    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        EntryComment $comment,
        EntryCommentFactory $factory,
        EntityManagerInterface $manager,
        RateLimiterFactory $apiModerateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $request = $this->request->getCurrentRequest();
        $newLang = $request->get('lang', '');

        $valid = false !== array_search($newLang, Languages::getLanguageCodes());

        if (!$valid) {
            throw new BadRequestHttpException('The given language is not valid!');
        }

        $comment->lang = $newLang;

        $manager->flush();

        return new JsonResponse(
            $this->serializeComment($factory->createDto($comment), $this->tagLinkRepository->getTagsOfEntryComment($comment)),
            headers: $headers
        );
    }
}
