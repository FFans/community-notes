<?php

namespace FFans\CommunityNotes\Scoring;

use FFans\CommunityNotes\Model\CommunityNote;

interface NoteScorer
{
    public function score(CommunityNote $note): ScoreResult;
}
