<?php

declare(strict_types=1);

namespace App\Controller\Api\Post\Moderate;

use App\Controller\Api\Post\PostsBaseApi;
use App\DTO\PostResponseDto;
use App\Entity\Post;
use App\Factory\PostFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Intl\Languages;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostsSetLanguageApi extends PostsBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Post language changed',
        content: new Model(type: PostResponseDto::class),
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
        description: 'You are not authorized to moderate this post',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Post not found',
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
        name: 'post_id',
        in: 'path',
        description: 'The post to change language of',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'lang',
        in: 'path',
        description: 'new language',
        schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 3),
    )]
    #[OA\Tag(name: 'moderation/post')]
    #[Security(name: 'oauth2', scopes: ['moderate:post:language'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:POST:LANGUAGE')]
    #[IsGranted('moderate', subject: 'post')]
    public function __invoke(
        #[MapEntity(id: 'post_id')]
        Post $post,
        EntityManagerInterface $manager,
        PostFactory $factory,
        RateLimiterFactory $apiModerateLimiter
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $request = $this->request->getCurrentRequest();
        $newLang = $request->get('lang', '');

        $valid = false !== array_search($newLang, Languages::getLanguageCodes());

        if (!$valid) {
            throw new BadRequestHttpException('The given language is not valid!');
        }

        $post->lang = $newLang;

        $manager->flush();

        return new JsonResponse(
            $this->serializePost($factory->createDto($post), $this->tagLinkRepository->getTagsOfPost($post)),
            headers: $headers
        );
    }
}
