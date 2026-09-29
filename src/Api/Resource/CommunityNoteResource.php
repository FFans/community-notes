<?php

namespace FFans\CommunityNotes\Api\Resource;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use FFans\CommunityNotes\Model\CommunityNote;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

class CommunityNoteResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'community-notes';
    }

    public function model(): string
    {
        return CommunityNote::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();
        $posts = Post::query()->whereVisibleTo($actor)->select('posts.id');
        $moderator = Permissions::allows($actor, Permissions::MODERATE);
        if (!$moderator) {
            $posts->where('posts.type', 'comment')->whereNull('posts.hidden_at');
            $query->where('is_hidden', false);
            if (!Permissions::allows($actor, Permissions::RATE)) {
                $query->where(function (Builder $visible) use ($actor) {
                    $visible->where('status', CommunityNoteStatus::Helpful->value);
                    // 作者需要读取自己提交的附注，以便在首次评价前编辑或删除。
                    if (!$actor->isGuest()) {
                        $visible->orWhere('user_id', $actor->id);
                    }
                });
            }
        }
        $query->whereIn('post_id', $posts)->with('sources');
        if ($moderator && $context->collection instanceof self) {
            // 按请求字段批量读取管理数据，避免列表逐条查询或加载未使用的历史。
            $fields = $context->sparseFields($this);
            if (isset($fields['ratings'])) {
                $query->with('ratings.reasons');
            }
            if (isset($fields['history'])) {
                $query->with('history');
            }
        }
        if (Permissions::allows($actor, Permissions::RATE)) {
            $query->with(['viewerRating' => fn($ratings) => $ratings->where('user_id', $actor->id)->with('reasons')]);
        }
    }

    public function fields(): array
    {
        // 帖子流携带公开字段及当前用户的评价；个人评价通过 scope 批量加载。
        // 分数和管理记录仅在附注 API 按权限提供；操作 Policy 由写入服务执行。
        $detail = fn(CommunityNote $note, Context $context) => $context->collection instanceof self;
        $moderator = fn(CommunityNote $note, Context $context) => $detail($note, $context) && Permissions::allows($context->getActor(), Permissions::MODERATE);

        return [
            Schema\Str::make('reason')->get(fn(CommunityNote $note) => $note->reason->value),
            Schema\Str::make('content'),
            Schema\Str::make('status')->get(fn(CommunityNote $note) => $note->status->value),
            Schema\Integer::make('ratingCount'),
            Schema\Number::make('score')->nullable()->visible(fn(CommunityNote $note, Context $context) => $detail($note, $context) && (Permissions::allows($context->getActor(), Permissions::RATE) || $moderator($note, $context))),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\DateTime::make('statusChangedAt'),
            Schema\Arr::make('sources')->get(fn(CommunityNote $note) => $note->sources->pluck('url')->all()),
            Schema\Boolean::make('isMine')->get(fn(CommunityNote $note, Context $context) => !$context->getActor()->isGuest() && $note->user_id === (int)$context->getActor()->id),
            Schema\Arr::make('myRating')->nullable()
                ->visible(fn(CommunityNote $note, Context $context) => Permissions::allows($context->getActor(), Permissions::RATE))
                ->get(function (CommunityNote $note, Context $context) {
                    // 写入端点返回单条模型，未经过读取 scope 时再查询当前用户的评价。
                    $rating = $note->relationLoaded('viewerRating') ? $note->viewerRating
                        : $note->ratings()->where('user_id', $context->getActor()->id)->with('reasons')->first();

                    return $rating ? ['value' => $rating->value->value, 'reasons' => $rating->reasons->map(fn($reason) => $reason->reason->value)->all()] : null;
                }),
            // Core 默认按模型名推断 communityNote，不能把任意候选附注写入帖子的公开附注关系。
            Schema\Relationship\ToOne::make('post')->type('posts')->includable()->inverse(''),
            Schema\Relationship\ToOne::make('user')->type('users')->includable()->visible($moderator),
            Schema\Boolean::make('isHidden')->visible($moderator),
            Schema\DateTime::make('hiddenAt')->nullable()->visible($moderator),
            Schema\Str::make('hiddenReason')->nullable()->visible($moderator),
            Schema\Relationship\ToOne::make('hiddenBy')->type('users')->includable()->visible($moderator),
            Schema\Arr::make('ratings')->visible($moderator)->get(fn(CommunityNote $note) => $note->loadMissing('ratings.reasons')->ratings->map(fn($rating) => [
                'id' => (string)$rating->id, 'userId' => $rating->user_id,
                'value' => $rating->value->value,
                'reasons' => $rating->reasons->map(fn($reason) => $reason->reason->value)->all(),
                'createdAt' => $rating->created_at?->toIso8601String(),
                'updatedAt' => $rating->updated_at?->toIso8601String(),
            ])->all()),
            Schema\Arr::make('history')->visible($moderator)->get(fn(CommunityNote $note) => $note->history->map(fn($entry) => [
                'id' => (string)$entry->id, 'event' => $entry->event->value,
                'actorUserId' => $entry->actor_user_id,
                'fromStatus' => $entry->from_status?->value, 'toStatus' => $entry->to_status?->value,
                'score' => $entry->score, 'ratingCount' => $entry->rating_count,
                'reason' => $entry->reason, 'createdAt' => $entry->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->paginate(20, 50)
                ->before(\Closure::fromCallable([new \FFans\CommunityNotes\Api\NoteIndexQuery(), 'validatePage']))
                ->query(\Closure::fromCallable(new \FFans\CommunityNotes\Api\NoteIndexQuery())),
            Endpoint\Show::make(),
            ...(new \FFans\CommunityNotes\Api\NoteWriteEndpoints())(),
            \FFans\CommunityNotes\Api\Endpoint\RateCommunityNote::make('rating'),
            \FFans\CommunityNotes\Api\Endpoint\HideCommunityNote::make('hide'),
            \FFans\CommunityNotes\Api\Endpoint\RestoreCommunityNote::make('restore'),
        ];
    }
}
