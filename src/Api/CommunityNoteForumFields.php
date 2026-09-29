<?php

namespace FFans\CommunityNotes\Api;

use FFans\CommunityNotes\Access\CommunityNotePermissions;
use Flarum\Api\Context;
use Flarum\Api\Schema\Boolean;

class CommunityNoteForumFields
{
    public function __invoke(): array
    {
        return [
            Boolean::make('canCreateCommunityNotes')
                ->get(fn($forum, Context $context) => CommunityNotePermissions::allows($context->getActor(), CommunityNotePermissions::CREATE)),
            Boolean::make('canRateCommunityNotes')
                ->get(fn($forum, Context $context) => CommunityNotePermissions::allows($context->getActor(), CommunityNotePermissions::RATE)),
            Boolean::make('canModerateCommunityNotes')
                ->get(fn($forum, Context $context) => CommunityNotePermissions::allows($context->getActor(), CommunityNotePermissions::MODERATE)),
        ];
    }
}
