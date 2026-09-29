<?php

namespace FFans\CommunityNotes\Api;

use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use FFans\CommunityNotes\Model\CommunityNote;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublicCommunityNoteRelation
{
    public function __invoke(Post $post): HasOne
    {
        return $post->hasOne(CommunityNote::class, 'post_id')
            ->where('status', CommunityNoteStatus::Helpful->value)
            ->where('is_hidden', false)
            ->whereHas('post', fn($query) => $query->where('type', 'comment')->whereNull('hidden_at'))
            ->orderByDesc('score')->orderByDesc('rating_count')
            ->orderBy('status_changed_at')->orderBy('id')->limit(1);
    }
}
