<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class SubjectExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest(
                'entry', function ($subject) {
                    return $subject instanceof Entry;
                }
            ),
            new TwigTest(
                'entry_comment', function ($subject) {
                    return $subject instanceof EntryComment;
                }
            ),
            new TwigTest(
                'post', function ($subject) {
                    return $subject instanceof Post;
                }
            ),
            new TwigTest(
                'post_comment', function ($subject) {
                    return $subject instanceof PostComment;
                }
            ),
            new TwigTest(
                'magazine', function ($subject) {
                    return $subject instanceof Magazine;
                }
            ),
            new TwigTest(
                'user', function ($subject) {
                    return $subject instanceof User;
                }
            ),
        ];
    }
}
