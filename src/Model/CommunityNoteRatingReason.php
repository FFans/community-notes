<?php

namespace FFans\CommunityNotes\Model;

use FFans\CommunityNotes\Enum\CommunityNoteRatingReason as RatingReason;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityNoteRatingReason extends AbstractModel
{
    protected $table = 'ffans_community_notes_rating_reasons';

    protected $casts = [
        'rating_id' => 'integer',
        'reason' => RatingReason::class,
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(CommunityNoteRating::class, 'rating_id');
    }
}
