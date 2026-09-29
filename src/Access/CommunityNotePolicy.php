<?php

namespace FFans\CommunityNotes\Access;

use FFans\CommunityNotes\Model\CommunityNote;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class CommunityNotePolicy extends AbstractPolicy
{
    // 创建时传入仅设置 post_id 的待创建附注，沿用 Flarum 的模型 Policy 分派。
    public function create(User $actor, CommunityNote $note): bool
    {
        if (!CommunityNotePermissions::allows($actor, CommunityNotePermissions::CREATE)) {
            return false;
        }
        $post = $this->eligiblePost($actor, $note);

        return $post !== null
            && (int)$post->user_id !== (int)$actor->id
            && !CommunityNote::query()->where('post_id', $post->id)->where('user_id', $actor->id)->exists();
    }

    public function update(User $actor, CommunityNote $note): bool
    {
        return CommunityNotePermissions::allows($actor, CommunityNotePermissions::CREATE)
            && $note->user_id === (int)$actor->id
            && !$note->is_hidden
            && $note->rating_count === 0
            && !$note->ratings()->exists()
            && $this->eligiblePost($actor, $note) !== null;
    }

    public function delete(User $actor, CommunityNote $note): bool
    {
        return $this->moderate($actor, $note) || $this->update($actor, $note);
    }

    public function rate(User $actor, CommunityNote $note): bool
    {
        return CommunityNotePermissions::allows($actor, CommunityNotePermissions::RATE)
            && $note->user_id !== (int)$actor->id
            && !$note->is_hidden
            && $this->eligiblePost($actor, $note) !== null;
    }

    public function viewCandidate(User $actor, CommunityNote $note): bool
    {
        if ($this->moderate($actor, $note)) {
            return true;
        }

        return $this->rate($actor, $note);
    }

    public function moderate(User $actor, CommunityNote $note): bool
    {
        return CommunityNotePermissions::allows($actor, CommunityNotePermissions::MODERATE)
            && Post::query()->whereVisibleTo($actor)->whereKey($note->post_id)->exists();
    }

    private function eligiblePost(User $actor, CommunityNote $note): ?CommentPost
    {
        // can('view', $post) 不能代替 Core 的可见性查询（含讨论、私密帖及扩展范围）。
        $post = Post::query()->whereVisibleTo($actor)->whereNull('posts.hidden_at')->find($note->post_id);

        return $post instanceof CommentPost ? $post : null;
    }
}
