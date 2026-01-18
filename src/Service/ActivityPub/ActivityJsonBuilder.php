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
use App\Entity\MagazineBan;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use App\Factory\ActivityPub\EntryCommentNoteFactory;
use App\Factory\ActivityPub\EntryPageFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\InstanceFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\PostCommentNoteFactory;
use App\Factory\ActivityPub\PostNoteFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ActivityJsonBuilder
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly InstanceFactory $instanceFactory,
        private readonly PersonFactory $personFactory,
        private readonly GroupFactory $groupFactory,
        private readonly ActivityFactory $activityFactory,
        private readonly ContextsProvider $contextsProvider,
        private readonly EntryPageFactory $entryPageFactory,
        private readonly EntryCommentNoteFactory $entryCommentNoteFactory,
        private readonly PostNoteFactory $postNoteFactory,
        private readonly PostCommentNoteFactory $postCommentNoteFactory,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function buildActivityJson(Activity $activity, bool $includeContext = true): array
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
            'Block' => $this->buildBlockFromActivity($activity),
            'Lock' => $this->buildLockFromActivity($activity),
            default => new \LogicException(),
        };
        $this->logger->debug('activity json: {json}', ['json' => json_encode($json, JSON_PRETTY_PRINT)]);

        if (!$includeContext) {
            unset($json['@context']);
        }

        return $json;
    }

    public function buildCreateFromActivity(Activity $activity): array
    {
        $o = $activity->objectEntry ?? $activity->objectEntryComment ?? $activity->objectPost ?? $activity->objectPostComment ?? $activity->objectMessage;
        $item = $this->activityFactory->create($o, true);

        unset($item['@context']);

        $activityJson = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Create',
            'actor' => $item['attributedTo'],
            'published' => $item['published'],
            'to' => $item['to'],
            'cc' => $item['cc'],
            'object' => $item,
        ];

        if (isset($item['audience'])) {
            $activityJson['audience'] = $item['audience'];
        }

        return $activityJson;
    }

    public function buildLikeFromActivity(Activity $activity): array
    {
        $actor = $this->personFactory->getActivityPubId($activity->userActor);
        if (null !== $activity->userActor->apId) {
            if ('test' === $this->kernel->getEnvironment()) {
                // ignore this in testing
            } else {
                throw new \LogicException('activities cannot be build for remote users');
            }
        }
        $object = $activity->getObject();
        if (!\is_string($object)) {
            throw new \LogicException('object must be a string');
        }

        $activityJson = [
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

        if (null !== $activity->audience) {
            $activityJson['cc'][] = $this->urlGenerator->generate('ap_magazine_followers', ['name' => $activity->audience->name], UrlGeneratorInterface::ABSOLUTE_URL);
            $activityJson['audience'] = $this->groupFactory->getActivityPubId($activity->audience);
        }

        return $activityJson;
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

        $activityJson = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Undo',
            'actor' => $object['actor'],
            'object' => $object,
            'to' => $object['to'],
            'cc' => $object['cc'] ?? [],
        ];

        if (isset($object['audience'])) {
            $activityJson['audience'] = $object['audience'];
        }

        return $activityJson;
    }

    public function buildAnnounceFromActivity(Activity $activity): array
    {
        $actor = $activity->getActor();
        $to = [ActivityPubActivityInterface::PUBLIC_URL];

        $cc = [];
        if ($actor instanceof User) {
            $cc[] = $this->personFactory->getActivityPubFollowersId($actor);
        } elseif ($actor instanceof Magazine) {
            $cc[] = $this->groupFactory->getActivityPubFollowersId($actor);
        }

        $object = $activity->getObject();

        if (null !== $activity->innerActivity) {
            $object = $this->buildActivityJson($activity->innerActivity);
        } elseif (null !== $activity->innerActivityUrl) {
            $object = $this->apHttpClient->getActivityObject($activity->innerActivityUrl);
        } elseif ($object instanceof ActivityPubActivityInterface) {
            $object = $this->activityFactory->create($object);
            if (isset($object['attributedTo'])) {
                $to[] = $object['attributedTo'];
            } elseif (isset($object['actor'])) {
                $to[] = $object['actor'];
            }
        }

        if (isset($object['@context'])) {
            unset($object['@context']);
        }
        $actorUrl = $actor instanceof User ? $this->personFactory->getActivityPubId($actor) : $this->groupFactory->getActivityPubId($actor);

        if (isset($object['cc'])) {
            $cc = array_merge($cc, array_filter($object['cc'], fn (string $url) => $url !== $actorUrl));
        }

        $activityJson = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Announce',
            'actor' => $actorUrl,
            'object' => $object,
            'to' => $to,
            'cc' => $cc,
            'published' => (new \DateTime())->format(DATE_ATOM),
        ];

        if ($actor instanceof Magazine) {
            $activityJson['audience'] = $this->groupFactory->getActivityPubId($actor);
        }

        return $activityJson;
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

        if (isset($item->magazine)) {
            $audience = $this->groupFactory->getActivityPubId($item->magazine);
        }

        $activityJson = [
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

        if (isset($audience)) {
            $activityJson['audience'] = $audience;
        }

        return $activityJson;
    }

    public function buildAddRemoveFromActivity(Activity $activity): array
    {
        if (null !== $activity->objectUser) {
            $object = $this->personFactory->getActivityPubId($activity->objectUser);
        } elseif (null !== $activity->objectEntry) {
            $object = $this->entryPageFactory->getActivityPubId($activity->objectEntry);
        } else {
            throw new \LogicException('There is no object set for the add/remove activity');
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'object' => $object,
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
            'to' => [
                $activityObject,
            ],
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

        if (null !== ($activityObject = $activity->getObject())) {
            $object = $activityObject;
        } elseif (null !== $activity->innerActivity) {
            $object = $this->buildActivityJson($activity->innerActivity);
            if (isset($object['@context'])) {
                unset($object['@context']);
            }
        } else {
            throw new \LogicException('There is no object set for the accept/reject activity');
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => $activity->type,
            'actor' => $actor,
            'object' => $object,
            'to' => [
                $object['actor'],
            ],
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

        $activityJson = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Update',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'published' => $entity['published'],
            'to' => $entity['to'],
            'cc' => $entity['cc'],
            'object' => $entity,
        ];

        if (null !== $activity->audience) {
            $activityJson['audience'] = $this->groupFactory->getActivityPubId($activity->audience);
        }

        return $activityJson;
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

    private function buildBlockFromActivity(Activity $activity): array
    {
        $object = $activity->getObject();
        $expires = null;
        $cc = [];
        if ($object instanceof MagazineBan) {
            $reason = $object->reason;
            $jsonObject = $this->personFactory->getActivityPubId($object->user);
            $target = $this->groupFactory->getActivityPubId($object->magazine);
            $expires = $object->expiredAt?->format(DATE_ATOM);
            $cc = [$this->groupFactory->getActivityPubId($activity->audience)];
        } elseif ($object instanceof User) {
            $reason = $object->banReason;
            $jsonObject = $this->personFactory->getActivityPubId($object);
            $target = $this->instanceFactory->getTargetUrl();
        } else {
            throw new \LogicException('Object of a block activity has to be of type MagazineBan');
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Block',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'object' => $jsonObject,
            'target' => $target,
            'summary' => $reason,
            'audience' => $activity->audience ? $this->groupFactory->getActivityPubId($activity->audience) : null,
            'expires' => $expires,
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => $cc,
        ];
    }

    private function buildLockFromActivity(Activity $activity): array
    {
        $object = $activity->getObject();
        if ($object instanceof Entry) {
            $objectUrl = $this->entryPageFactory->getActivityPubId($object);
        } elseif ($object instanceof Post) {
            $objectUrl = $this->postNoteFactory->getActivityPubId($object);
        } else {
            throw new \LogicException('Lock activity is only supported for entries and posts, not for '.\get_class($object));
        }

        return [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Lock',
            'actor' => $this->personFactory->getActivityPubId($activity->userActor),
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => [
                $this->groupFactory->getActivityPubId($object->magazine),
                $this->urlGenerator->generate('ap_user_followers', ['username' => $activity->userActor->username], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'object' => $objectUrl,
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
