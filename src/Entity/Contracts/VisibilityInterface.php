<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

interface VisibilityInterface
{
    /** may be viewed by everyone */
    public const VISIBILITY_VISIBLE = 'visible';
    /** removed by author; should not be viewed by anyone */
    public const VISIBILITY_SOFT_DELETED = 'soft_deleted';
    /** removed by moderator; may be viewed by mods / admins */
    public const VISIBILITY_TRASHED = 'trashed';
    /** may be viewed by followers of author */
    public const VISIBILITY_PRIVATE = 'private';

    public function getVisibility(): string;

    public function isVisible(): bool;

    public function isTrashed(): bool;

    public function isPrivate(): bool;

    public function isSoftDeleted(): bool;

    public function softDelete(): void;

    public function trash(): void;

    public function restore(): void;
}
