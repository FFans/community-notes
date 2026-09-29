<?php

namespace FFans\CommunityNotes\Model;

use FFans\CommunityNotes\Enum\CommunityNoteReason;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use Flarum\Database\AbstractModel;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommunityNote extends AbstractModel
{
    protected $table = 'ffans_community_notes_notes';
    public $timestamps = true;

    protected $casts = [
        'reason' => CommunityNoteReason::class,
        'status' => CommunityNoteStatus::class,
        'post_id' => 'integer',
        'user_id' => 'integer',
        'rating_count' => 'integer',
        'helpful_count' => 'integer',
        'somewhat_helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'score' => 'float',
        'status_changed_at' => 'datetime',
        'is_hidden' => 'boolean',
        'hidden_at' => 'datetime',
        'hidden_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CommunityNoteSource::class, 'note_id')->orderBy('position')->orderBy('id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(CommunityNoteRating::class, 'note_id');
    }

    // API 按当前用户约束此关系，独立于管理界面读取的全部评价。
    public function viewerRating(): HasOne
    {
        return $this->hasOne(CommunityNoteRating::class, 'note_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CommunityNoteHistory::class, 'note_id')->orderBy('created_at')->orderBy('id');
    }
}
