<?php

declare(strict_types=1);

namespace App\Controller\Api\Poll;

use App\Controller\Api\BaseApi;
use App\DTO\PollResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Poll;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Schema\Errors\BadRequestErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\PollManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PollVoteController extends BaseApi
{
    #[OA\Response(
        response: 200,
        description: 'voted on poll',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: PollResponseDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Poll was not valid. Possibly: poll already ended, choices do not exist, user already voted',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Poll not found',
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
        name: 'choices',
        description: 'The user\'s voting choices',
        in: 'query',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Tag(name: 'entry')]
    #[OA\Tag(name: 'poll')]
    #[Security(name: 'oauth2', scopes: ['entry:vote'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY:VOTE')]
    public function voteOnEntry(
        #[MapEntity(mapping: ['entryId' => 'id'])] Entry $entry,
        Request $request,
        PollManager $pollManager,
        RateLimiterFactoryInterface $apiVoteLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiVoteLimiter);
        $poll = $entry->poll;
        if (null === $poll) {
            throw new NotFoundHttpException();
        }

        return $this->voteOnPoll($request, $pollManager, $this->getUserOrThrow(), $entry, $poll, $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'voted on poll',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: PollResponseDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Poll was not valid. Possibly: poll already ended, choices do not exist, user already voted',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Poll not found',
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
        name: 'choices',
        description: 'The user\'s voting choices',
        in: 'query',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Tag(name: 'entry_comment')]
    #[OA\Tag(name: 'poll')]
    #[Security(name: 'oauth2', scopes: ['entry_comment:vote'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY_COMMENT:VOTE')]
    public function voteOnEntryComment(
        #[MapEntity(mapping: ['entryId' => 'id'])] Entry $entry,
        #[MapEntity(mapping: ['commentId' => 'id'])] EntryComment $comment,
        Request $request,
        PollManager $pollManager,
        RateLimiterFactoryInterface $apiVoteLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiVoteLimiter);
        $poll = $comment->poll;
        if (null === $poll) {
            throw new NotFoundHttpException();
        }

        if ($comment->entry->getId() !== $entry->getId()) {
            throw new BadRequestHttpException();
        }

        return $this->voteOnPoll($request, $pollManager, $this->getUserOrThrow(), $comment, $poll, $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'voted on poll',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: PollResponseDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Poll was not valid. Possibly: poll already ended, choices do not exist, user already voted',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Poll not found',
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
        name: 'choices',
        description: 'The user\'s voting choices',
        in: 'query',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Tag(name: 'post')]
    #[OA\Tag(name: 'poll')]
    #[Security(name: 'oauth2', scopes: ['post:vote'])]
    #[IsGranted('ROLE_OAUTH2_POST:VOTE')]
    public function voteOnPost(
        #[MapEntity(mapping: ['postId' => 'id'])] Post $post,
        Request $request,
        PollManager $pollManager,
        RateLimiterFactoryInterface $apiVoteLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiVoteLimiter);
        $poll = $post->poll;
        if (null === $poll) {
            throw new NotFoundHttpException();
        }

        return $this->voteOnPoll($request, $pollManager, $this->getUserOrThrow(), $post, $poll, $headers);
    }

    #[OA\Response(
        response: 200,
        description: 'voted on poll',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: PollResponseDto::class)
    )]
    #[OA\Response(
        response: 400,
        description: 'Poll was not valid. Possibly: poll already ended, choices do not exist, user already voted',
        content: new OA\JsonContent(ref: new Model(type: BadRequestErrorSchema::class))
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Poll not found',
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
        name: 'choices',
        description: 'The user\'s voting choices',
        in: 'query',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Tag(name: 'post_comment')]
    #[OA\Tag(name: 'poll')]
    #[Security(name: 'oauth2', scopes: ['post_comment:vote'])]
    #[IsGranted('ROLE_OAUTH2_POST_COMMENT:VOTE')]
    public function voteOnPostComment(
        #[MapEntity(mapping: ['postId' => 'id'])] Post $post,
        #[MapEntity(mapping: ['commentId' => 'id'])] PostComment $comment,
        Request $request,
        PollManager $pollManager,
        RateLimiterFactoryInterface $apiVoteLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiVoteLimiter);
        $poll = $comment->poll;
        if (null === $poll) {
            throw new NotFoundHttpException();
        }

        if ($comment->post->getId() !== $post->getId()) {
            throw new BadRequestHttpException();
        }

        return $this->voteOnPoll($request, $pollManager, $this->getUserOrThrow(), $comment, $poll, $headers);
    }

    public function voteOnPoll(Request $request, PollManager $pollManager, User $user, Entry|EntryComment|Post|PostComment $content, Poll $poll, array $headers): JsonResponse
    {
        if ($content->poll->getId() !== $poll->getId() || $content->poll->hasEnded() || $content->poll->hasUserVoted($user)) {
            throw new BadRequestHttpException();
        }

        $choices = $request->query->all('choices');

        foreach ($choices as $choice) {
            if (!$content->poll->findChoice($choice)) {
                throw new BadRequestHttpException();
            }
        }

        $pollManager->vote($poll, $content, $user, $choices);
        $this->entityManager->refresh($poll);

        return new JsonResponse($this->serializePoll($poll), headers: $headers);
    }
}
