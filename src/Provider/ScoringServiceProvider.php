<?php

namespace FFans\CommunityNotes\Provider;

use FFans\CommunityNotes\Scoring\NoteScorer;
use FFans\CommunityNotes\Scoring\SimpleNoteScorer;
use Flarum\Foundation\AbstractServiceProvider;

class ScoringServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(NoteScorer::class, SimpleNoteScorer::class);
    }
}
