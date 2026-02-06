<?php

declare(strict_types=1);

namespace App\Controller\Api\Notification;

use App\Controller\Api\BaseApi;
use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\DTO\PostCommentResponseDto;
use App\DTO\PostResponseDto;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\NewSignupNotification;
use App\Entity\Notification;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\ReportApprovedNotification;
use App\Entity\ReportCreatedNotification;
use App\Entity\ReportRejectedNotification;
use App\Factory\MessageFactory;
use Symfony\Contracts\Service\Attribute\Required;

class NotificationBaseApi extends BaseApi
{
    private MessageFactory $messageFactory;

    #[Required]
    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    /**
     * Serialize a single message to JSON.
     *
     * @param Notification $dto The Notification to serialize
     *
     * @return array An associative array representation of the message's safe fields, to be used as JSON
     */
    protected function serializeNotification(Notification $dto)
    {
        $toReturn = [
            'notificationId' => $dto->getId(),
            'status' => $dto->status,
            'type' => $dto->getType(),
        ];

        switch ($dto->getType()) {
            case 'entry_created_notification':
            case 'entry_edited_notification':
            case 'entry_deleted_notification':
            case 'entry_mentioned_notification':
                /**
                 * @var \App\Entity\EntryMentionedNotification $dto
                 */
                $entry = $dto->getSubject();
                $toReturn['subject'] = $this->entryFactory->createResponseDto($entry, $this->tagLinkRepository->getTagsOfContent($entry));
                break;
            case 'entry_comment_created_notification':
            case 'entry_comment_edited_notification':
            case 'entry_comment_reply_notification':
            case 'entry_comment_deleted_notification':
            case 'entry_comment_mentioned_notification':
                /**
                 * @var \App\Entity\EntryCommentMentionedNotification $dto
                 */
                $comment = $dto->getSubject();
                $toReturn['subject'] = $this->entryCommentFactory->createResponseDto($comment, $this->tagLinkRepository->getTagsOfContent($comment));
                break;
            case 'post_created_notification':
            case 'post_edited_notification':
            case 'post_deleted_notification':
            case 'post_mentioned_notification':
                /**
                 * @var \App\Entity\PostMentionedNotification $dto
                 */
                $post = $dto->getSubject();
                $toReturn['subject'] = $this->postFactory->createResponseDto($post, $this->tagLinkRepository->getTagsOfContent($post));
                break;
            case 'post_comment_created_notification':
            case 'post_comment_edited_notification':
            case 'post_comment_reply_notification':
            case 'post_comment_deleted_notification':
            case 'post_comment_mentioned_notification':
                /**
                 * @var \App\Entity\PostCommentMentionedNotification $dto
                 */
                $comment = $dto->getSubject();
                $toReturn['subject'] = $this->postCommentFactory->createResponseDto($comment, $this->tagLinkRepository->getTagsOfContent($comment));
                break;
            case 'message_notification':
                if (!$this->isGranted('ROLE_OAUTH2_USER:MESSAGE:READ')) {
                    $toReturn['subject'] = [
                        'messageId' => null,
                        'threadId' => null,
                        'sender' => null,
                        'body' => $this->translator->trans('oauth.client_not_granted_message_read_permission'),
                        'status' => null,
                        'createdAt' => null,
                    ];
                    break;
                }
                /**
                 * @var \App\Entity\MessageNotification $dto
                 */
                $message = $dto->getSubject();
                $toReturn['subject'] = $this->messageFactory->createResponseDto($message);
                break;
            case 'ban':
                /**
                 * @var \App\Entity\MagazineBanNotification $dto
                 */
                $ban = $dto->getSubject();
                $toReturn['subject'] = $this->magazineFactory->createBanDto($ban);
                break;
            case 'report_created_notification':
                /** @var ReportCreatedNotification $n */
                $n = $dto;
                $toReturn['reason'] = $n->report->reason;
                // no break
            case 'report_rejected_notification':
            case 'report_approved_notification':
                /** @var ReportCreatedNotification|ReportRejectedNotification|ReportApprovedNotification $n */
                $n = $dto;
                $toReturn['subject'] = $this->createResponseDtoForReport($n->report->getSubject());
                $toReturn['reportId'] = $n->report->getId();
                break;
            case 'new_signup':
                /** @var NewSignupNotification $n */
                $n = $dto;
                $toReturn['subject'] = $this->userFactory->createDto($n->getSubject());
                break;
        }

        return $toReturn;
    }

    private function createResponseDtoForReport(ReportInterface $subject): EntryCommentResponseDto|EntryResponseDto|PostCommentResponseDto|PostResponseDto
    {
        if ($subject instanceof Entry) {
            return $this->entryFactory->createResponseDto($subject, $this->tagLinkRepository->getTagsOfContent($subject));
        } elseif ($subject instanceof EntryComment) {
            return $this->entryCommentFactory->createResponseDto($subject, $this->tagLinkRepository->getTagsOfContent($subject));
        } elseif ($subject instanceof Post) {
            return $this->postFactory->createResponseDto($subject, $this->tagLinkRepository->getTagsOfContent($subject));
        } elseif ($subject instanceof PostComment) {
            return $this->postCommentFactory->createResponseDto($subject, $this->tagLinkRepository->getTagsOfContent($subject));
        }
        throw new \InvalidArgumentException("cannot work with: '".\get_class($subject)."'");
    }
}
