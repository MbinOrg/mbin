<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use App\Factory\ActivityPub\EntryCommentNoteFactory;
use App\Factory\ActivityPub\EntryPageFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\PostCommentNoteFactory;
use App\Factory\ActivityPub\PostNoteFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ActivityJsonBuilder
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PersonFactory $personFactory,
        private readonly GroupFactory $groupFactory,
        private readonly ActivityFactory $activityFactory,
        private readonly ContextsProvider $contextsProvider,
        private readonly EntryPageFactory $entryPageFactory,
        private readonly EntryCommentNoteFactory $entryCommentNoteFactory,
        private readonly PostNoteFactory $postNoteFactory,
        private readonly PostCommentNoteFactory $postCommentNoteFactory,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClient $apHttpClient,
    ) {
    }

    public function buildActivityJson(Activity $activity): array
    {
        $this->logger->debug('activity json: build for {id}', ['id' => $activity->uuid->toString()]);
        if (null !== $activity->activityJson) {
            $json = json_decode($activity->activityJson, true);
            $this->logger->debug('activity json: {json}', ['json' => json_encode($json, JSON_PRETTY_PRINT)]);

            return $json;
        }

        $json = match ($activity->type) {
            'Create' => $this->buildCreateFromActivity($activity),
            'Like' => $this->buildLikeFromActivity($activity),
            'Undo' => $this->buildUndoFromActivity($activity),
            'Announce' => $this->buildAnnounceFromActivity($activity),
            'Delete' => $this->buildDeleteFromActivity($activity),
            'Add', 'Remove' => $this->buildAddRemoveFromActivity($activity),
            'Flag' => $this->buildFlagFromActivity($activity),
            'Follow' => $this->buildFollowFromActivity($activity),
            'Accept', 'Reject' => $this->buildAcceptRejectFromActivity($activity),
            'Update' => $this->buildUpdateFromActivity($activity),
            default => new \LogicException(),
        };
        $this->logger->debug('activity json: {json}', ['json' => json_encode($json, JSON_PRETTY_PRINT)]);

        return $json;
    }

    public function buildCreateFromActivity(Activity $activity): array
    {
        $o = $activity->objectEntry ?? $activity->objectEntryComment ?? $activity->objectPost ?? $activity->objectPostComment ?? $activity->objectMessage;
        $item = $this->activityFactory->create($o, true);

        unset($item['@context']);

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Create',
            'actor' => $item['attributedTo'],
            'published' => $item['published'],
            'to' => $item['to'],
            'cc' => $item['cc'],
            'object' => $item,
        ];
    }

    public function buildLikeFromActivity(Activity $activity): array
    {
        $actor = $this->personFactory->getActivityPubId($activity->userActor);
        if (null !== $activity->userActor->apId) {
            throw new \LogicException('activities cannot be build for remote users');
        }
        $object = $activity->getObject();
        if (!\is_string($object)) {
            throw new \LogicException('object must be a string');
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Like',
            'actor' => $actor,
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => [
                $this->urlGenerator->generate('ap_user_followers', ['username' => $activity->userActor->username], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'object' => $object,
        ];
    }

    public function buildUndoFromActivity(Activity $activity): array
    {
        if (null !== $activity->innerActivity) {
            $object = $this->buildActivityJson($activity->innerActivity);
        } elseif (null !== $activity->innerActivityUrl) {
            $object = $this->apHttpClient->getActivityObject($activity->innerActivityUrl);
            if (!\is_array($object)) {
                throw new \LogicException('object must be another activity');
            }
        } else {
            throw new \LogicException('undo activity must have an inner activity / -url');
        }

        unset($object['@context']);

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Undo',
            'actor' => $object['actor'],
            'object' => $object,
        ];
    }

    public function buildAnnounceFromActivity(Activity $activity): array
    {
        $actor = $activity->getActor();
        $to = [ActivityPubActivityInterface::PUBLIC_URL];

        $object = $activity->getObject();

        if (null !== $activity->innerActivity) {
            $object = $this->buildActivityJson($activity->innerActivity);
        } elseif (null !== $activity->innerActivityUrl) {
            $object = $this->apHttpClient->getActivityObject($activity->innerActivityUrl);
        } elseif ($object instanceof ActivityPubActivityInterface) {
            $object = $this->activityFactory->create($object);
            if (isset($object['attributedTo'])) {
                $to[] = $object['attributedTo'];
            }

            unset($object['@context']);
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Announce',
            'actor' => $actor instanceof User ? $this->personFactory->getActivityPubId($actor) : $this->groupFactory->getActivityPubId($actor),
            'object' => $object,
            'to' => $to,
            'cc' => $object['cc'] ?? [],
            'published' => (new \DateTime())->format(DATE_ATOM),
        ];
    }

    public function buildDeleteFromActivity(Activity $activity): array
    {
        $item = $activity->getObject();
        if (!\is_array($item)) {
            throw new \LogicException();
        }

        $activityActor = $activity->getActor();
        if ($activityActor instanceof User) {
            $userUrl = $this->personFactory->getActivityPubId($activityActor);
        } elseif ($activityActor instanceof Magazine) {
            $userUrl = $this->groupFactory->getActivityPubId($activityActor);
        } else {
            throw new \LogicException();
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $userUrl,
            'object' => [
                'id' => $item['id'],
                'type' => 'Tombstone',
            ],
            'to' => $item['to'],
            'cc' => $item['cc'],
        ];
    }

    public function buildAddRemoveFromActivity(Activity $activity): array
    {
        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'object' => $this->personFactory->getActivityPubId($activity->objectUser),
            'cc' => [$this->groupFactory->getActivityPubId($activity->audience)],
            'type' => $activity->type,
            'target' => $activity->targetString,
            'audience' => $this->groupFactory->getActivityPubId($activity->audience),
        ];
    }

    public function buildFlagFromActivity(Activity $activity): array
    {
        // mastodon does not accept a report that does not have an array as object.
        // I created an issue for it: https://github.com/mastodon/mastodon/issues/28159
        $mastodonObject = [
            $this->getPublicUrl($activity->getObject()),
            $this->personFactory->getActivityPubId($activity->objectUser),
        ];

        // lemmy does not accept a report that does have an array as object.
        // I created an issue for it: https://github.com/LemmyNet/lemmy/issues/4217
        $lemmyObject = $this->getPublicUrl($activity->getObject());

        if ('random' !== $activity->audience || $activity->audience->apId) {
            // apAttributedToUrl is not a standardized field,
            // so it is not implemented by every software that supports groups.
            // Some don't have moderation at all, so it will probably remain optional in the future.
            $audience = $this->groupFactory->getActivityPubId($activity->audience);
            $object = $lemmyObject;
        } else {
            $audience = $this->personFactory->getActivityPubId($activity->objectUser);
            $object = $mastodonObject;
        }

        $result = [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Flag',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'object' => $object,
            'audience' => $audience,
            'summary' => $activity->contentString,
            'content' => $activity->contentString,
        ];

        if ('random' !== $activity->audience->name || $activity->audience->apId) {
            $result['to'] = [$this->groupFactory->getActivityPubId($activity->audience)];
        }

        return $result;
    }

    public function buildFollowFromActivity(Activity $activity): array
    {
        $object = $activity->getObject();
        if ($object instanceof User) {
            $activityObject = $this->personFactory->getActivityPubId($object);
        } else {
            $activityObject = $this->groupFactory->getActivityPubId($object);
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Follow',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'object' => $activityObject,
        ];
    }

    public function buildAcceptRejectFromActivity(Activity $activity): array
    {
        $activityActor = $activity->getActor();
        if ($activityActor instanceof User) {
            $actor = $this->personFactory->getActivityPubId($activityActor);
        } elseif ($activityActor instanceof Magazine) {
            $actor = $this->groupFactory->getActivityPubId($activityActor);
        } else {
            throw new \LogicException();
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => $activity->type,
            'actor' => $actor,
            'object' => $activity->getObject(),
        ];
    }

    public function buildUpdateFromActivity(Activity $activity): array
    {
        $object = $activity->getObject();
        if ($object instanceof ActivityPubActivityInterface) {
            return $this->buildUpdateForContentFromActivity($activity, $object);
        } elseif ($object instanceof ActivityPubActorInterface) {
            return $this->buildUpdateForActorFromActivity($activity, $object);
        } else {
            throw new \LogicException();
        }
    }

    public function buildUpdateForContentFromActivity(Activity $activity, ActivityPubActivityInterface $content): array
    {
        $entity = $this->activityFactory->create($content);

        $entity['object']['updated'] = $content->editedAt ? $content->editedAt->format(DATE_ATOM) : (new \DateTime())->format(DATE_ATOM);

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Update',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'published' => $entity['published'],
            'to' => $entity['to'],
            'cc' => $entity['cc'],
            'object' => $entity,
        ];
    }

    public function buildUpdateForActorFromActivity(Activity $activity, ActivityPubActorInterface $object): array
    {
        if ($object instanceof User) {
            $activityObject = $this->personFactory->create($object, false);
            if (null === $object->apId) {
                $cc = [$this->urlGenerator->generate('ap_user_followers', ['username' => $object->username], UrlGeneratorInterface::ABSOLUTE_URL)];
            } else {
                $cc = [$object->apFollowersUrl];
            }
        } elseif ($object instanceof Magazine) {
            $activityObject = $this->groupFactory->create($object, false);
            if (null === $object->apId) {
                $cc = [$this->urlGenerator->generate('ap_magazine_followers', ['name' => $object->name], UrlGeneratorInterface::ABSOLUTE_URL)];
            } else {
                $cc = [$object->apFollowersUrl];
            }
        } else {
            throw new \LogicException('Unknown actor type: '.\get_class($object));
        }

        $actorUrl = $this->personFactory->getActivityPubId($activity->userActor);

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Update',
            'actor' => $actorUrl,
            'published' => $activityObject['published'],
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => $cc,
            'object' => $activityObject,
        ];
    }

    public function getPublicUrl(ReportInterface|ActivityPubActivityInterface $subject): string
    {
        if ($subject instanceof Entry) {
            return $this->entryPageFactory->getActivityPubId($subject);
        } elseif ($subject instanceof EntryComment) {
            return $this->entryCommentNoteFactory->getActivityPubId($subject);
        } elseif ($subject instanceof Post) {
            return $this->postNoteFactory->getActivityPubId($subject);
        } elseif ($subject instanceof PostComment) {
            return $this->postCommentNoteFactory->getActivityPubId($subject);
        } elseif ($subject instanceof Message) {
            return $this->urlGenerator->generate('ap_message', ['uuid' => $subject->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        throw new \LogicException("can't handle ".\get_class($subject));
    }
}
