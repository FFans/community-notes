<?php

namespace FFans\CommunityNotes\Model;

use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityNoteHistory extends AbstractModel
{
    public const UPDATED_AT = null;

    protected $table = 'ffans_community_notes_note_history';
    public $timestamps = true;

    protected $casts = [
        'event' => CommunityNoteHistoryEvent::class,
        'from_status' => CommunityNoteStatus::class,
        'to_status' => CommunityNoteStatus::class,
        'note_id' => 'integer',
        'actor_user_id' => 'integer',
        'score' => 'float',
        'rating_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(CommunityNote::class, 'note_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
