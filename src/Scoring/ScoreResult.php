<?php

namespace FFans\CommunityNotes\Scoring;

use FFans\CommunityNotes\Enum\CommunityNoteStatus;

final readonly class ScoreResult
{
    public function __construct(
        public int                 $ratingCount,
        public int                 $helpfulCount,
        public int                 $somewhatHelpfulCount,
        public int                 $notHelpfulCount,
        public ?float              $score,
        public CommunityNoteStatus $status,
    )
    {
    }
}
