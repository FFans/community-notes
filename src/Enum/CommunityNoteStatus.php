<?php

namespace FFans\CommunityNotes\Enum;

enum CommunityNoteStatus: string
{
    case NeedsMoreRatings = 'needs_more_ratings';
    case Helpful = 'helpful';
    case NotHelpful = 'not_helpful';
}
