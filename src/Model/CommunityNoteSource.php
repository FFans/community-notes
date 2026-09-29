<?php

namespace FFans\CommunityNotes\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityNoteSource extends AbstractModel
{
    public const UPDATED_AT = null;

    protected $table = 'ffans_community_notes_note_sources';
    public $timestamps = true;

    protected $casts = [
        'note_id' => 'integer',
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(CommunityNote::class, 'note_id');
    }
}
