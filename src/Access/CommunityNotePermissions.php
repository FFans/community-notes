<?php

namespace FFans\CommunityNotes\Access;

use Flarum\User\User;

final class CommunityNotePermissions
{
    public const CREATE = 'ffans-community-notes.note.create';
    public const RATE = 'ffans-community-notes.note.rate';
    public const MODERATE = 'ffans-community-notes.note.moderate';

    // 这里只判断全局资格，具体帖子可见性、作者和锁定状态由后续 Policy 判断。
    public static function allows(User $actor, string $permission): bool
    {
        return !$actor->isGuest() && $actor->hasPermission($permission);
    }
}
