<?php

namespace FFans\CommunityNotes\Api;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Api\Resource\CommunityNoteResource;
use Flarum\Api\Context;
use Flarum\Api\Schema\Integer;
use Flarum\Api\Schema\Relationship\ToOne;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;

class CommunityNotePostFields
{
    public function __invoke(): array
    {
        return [
            ToOne::make('communityNote')->type('community-notes')->includable(),
            // 仅管理者需要计数；Core 一次批量聚合整个帖子批次，包括待评价和已隐藏附注。
            Integer::make('communityNoteCount')
                ->visible(fn(Post $post, Context $context) => $post instanceof CommentPost && Permissions::allows($context->getActor(), Permissions::MODERATE))
                ->countRelation('communityNotes'),
            // 附注页面按当前用户的详情可见范围批量计数，不复用仅供管理的总数。
            // 按非空主键计数，避免 Core 将同关系的两个 COUNT(*) 合并到同一缓冲区。
            Integer::make('visibleCommunityNoteCount')
                ->visible(fn(Post $post, Context $context) => $post instanceof CommentPost
                    && $context->collection instanceof CommunityNoteResource
                    && (Permissions::allows($context->getActor(), Permissions::RATE) || Permissions::allows($context->getActor(), Permissions::MODERATE)))
                ->relationAggregate('communityNotes', 'id', 'count', function (Builder $query, Context $context) {
                    $actor = $context->getActor();
                    $posts = Post::query()->whereVisibleTo($actor)->select('posts.id');
                    if (!Permissions::allows($actor, Permissions::MODERATE)) {
                        $query->where('is_hidden', false);
                        $posts->where('posts.type', 'comment')->whereNull('posts.hidden_at');
                    }
                    $query->whereIn('post_id', $posts);
                }),
            // Core 的关系聚合缓冲器批量查询；仅返回当前作者自己的 ID（包括已隐藏的附注）。
            Integer::make('myCommunityNoteId')->nullable()
                ->visible(fn(Post $post, Context $context) => !$context->getActor()->isGuest() && $post instanceof CommentPost)
                ->maxRelation('communityNotes', 'id', fn($query, Context $context) => $query->where('user_id', $context->getActor()->id)),
        ];
    }
}
