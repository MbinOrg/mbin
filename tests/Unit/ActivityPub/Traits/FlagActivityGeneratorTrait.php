<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\DTO\ReportDto;
use App\Entity\Activity;
use App\Entity\User;

trait FlagActivityGeneratorTrait
{
    public function getFlagEntryActivity(User $reportingUser): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        $report = $this->reportManager->report(ReportDto::create($entry), $reportingUser);

        return $this->flagFactory->build($report);
    }

    public function getFlagEntryCommentActivity(User $reportingUser): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        $report = $this->reportManager->report(ReportDto::create($entryComment), $reportingUser);

        return $this->flagFactory->build($report);
    }

    public function getFlagNestedEntryCommentActivity(User $reportingUser): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        $report = $this->reportManager->report(ReportDto::create($entryComment2), $reportingUser);

        return $this->flagFactory->build($report);
    }

    public function getFlagPostActivity(User $reportingUser): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        $report = $this->reportManager->report(ReportDto::create($post), $reportingUser);

        return $this->flagFactory->build($report);
    }

    public function getFlagPostCommentActivity(User $reportingUser): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        $report = $this->reportManager->report(ReportDto::create($postComment), $reportingUser);

        return $this->flagFactory->build($report);
    }

    public function getFlagNestedPostCommentActivity(User $reportingUser): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        $report = $this->reportManager->report(ReportDto::create($postComment2), $reportingUser);

        return $this->flagFactory->build($report);
    }
}
