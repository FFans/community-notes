<?php

namespace FFans\CommunityNotes\Model;

use FFans\CommunityNotes\Enum\CommunityNoteRatingValue;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityNoteRating extends AbstractModel
{
    protected $table = 'ffans_community_notes_note_ratings';
    public $timestamps = true;

    protected $casts = [
        'value' => CommunityNoteRatingValue::class,
        'note_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(CommunityNote::class, 'note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasons(): HasMany
    {
        return $this->hasMany(CommunityNoteRatingReason::class, 'rating_id');
    }
}
