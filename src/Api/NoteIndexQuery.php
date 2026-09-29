<?php

namespace FFans\CommunityNotes\Api;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use Flarum\Api\Context;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Pagination\Pagination;

class NoteIndexQuery
{
    public function validatePage(Context $context): void
    {
        $page = $context->queryParam('page') ?? [];
        if (!is_array($page)) {
            throw new BadRequestException('Invalid pagination parameters.');
        }
        foreach (['offset', 'limit'] as $key) {
            if (isset($page[$key]) && (!is_scalar($page[$key]) || !ctype_digit((string)$page[$key])
                    || ($key === 'limit' && (int)$page[$key] < 1))) {
                throw new BadRequestException('Pagination parameters must be valid integers.');
            }
        }
    }

    public function __invoke(Builder $query, ?Pagination $pagination, Context $context, array $filters, ?array $sort, int $offset, ?int $limit): Context
    {
        if ($sort || array_diff(array_keys($filters), ['post', 'status', 'queue', 'feed', 'moderation'])) {
            throw new BadRequestException('Unsupported community note filter or sort.');
        }
        if (isset($filters['moderation'])) {
            $context->getActor()->assertPermission(Permissions::allows($context->getActor(), Permissions::MODERATE));
            if (!in_array($filters['moderation'], ['all', 'hidden'], true) || count($filters) !== 1) {
                throw new BadRequestException('Invalid moderation filter.');
            }
            if ($filters['moderation'] === 'hidden') {
                $query->where('is_hidden', true);
            }
            $query->orderByDesc('created_at')->orderByDesc('id');
        }
        if (isset($filters['post'])) {
            if (!is_scalar($filters['post']) || !ctype_digit((string)$filters['post']) || (int)$filters['post'] < 1) {
                throw new BadRequestException('Invalid post ID.');
            }
            $query->where('post_id', $filters['post']);
        }
        if (isset($filters['status'])) {
            if (!is_string($filters['status']) || !CommunityNoteStatus::tryFrom($filters['status'])) {
                throw new BadRequestException('Invalid community note status.');
            }
            $query->where('status', $filters['status']);
        }
        if (isset($filters['queue'])) {
            if ($filters['queue'] !== 'rating') {
                throw new BadRequestException('Invalid rating queue.');
            }
            $actor = $context->getActor();
            $actor->assertPermission(Permissions::allows($actor, Permissions::RATE) || Permissions::allows($actor, Permissions::MODERATE));
            $query->where('is_hidden', false)
                ->where(fn(Builder $q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $actor->id))
                ->whereHas('post', fn(Builder $q) => $q->where('type', 'comment')->whereNull('hidden_at'))
                ->withExists(['ratings as actor_has_rated' => fn(Builder $q) => $q->where('user_id', $actor->id)])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CommunityNoteStatus::NeedsMoreRatings->value])
                ->orderBy('actor_has_rated');
        }
        if (isset($filters['feed'])) {
            if (!in_array($filters['feed'], ['helpful', 'latest'], true) || count($filters) !== 1) {
                throw new BadRequestException('Invalid post feed filter.');
            }
            $actor = $context->getActor();
            $actor->assertPermission(Permissions::allows($actor, Permissions::RATE) || Permissions::allows($actor, Permissions::MODERATE));
            $helpful = $filters['feed'] === 'helpful';
            $table = $query->getModel()->getTable();
            $query->where('is_hidden', false)
                ->whereHas('post', fn(Builder $q) => $q->where('type', 'comment')->whereNull('hidden_at'));
            if ($helpful) {
                $query->where('status', CommunityNoteStatus::Helpful->value);
            } else {
                // 复用原帖公开附注关系，在分页前排除已经展示附注的帖子。
                $query->whereDoesntHave('post.communityNote');
            }
            // 在分页前每帖只保留最新的匹配附注，避免跨页重复和客户端去重导致空页。
            $query->whereNotExists(function (QueryBuilder $newer) use ($table, $helpful) {
                $newer->selectRaw('1')->from($table . ' as newer_note')
                    ->whereColumn('newer_note.post_id', $table . '.post_id')
                    ->where('newer_note.is_hidden', false)
                    ->where(function (QueryBuilder $order) use ($table) {
                        $order->whereColumn('newer_note.created_at', '>', $table . '.created_at')
                            ->orWhere(fn(QueryBuilder $tie) => $tie
                                ->whereColumn('newer_note.created_at', $table . '.created_at')
                                ->whereColumn('newer_note.id', '>', $table . '.id'));
                    });
                if ($helpful) {
                    $newer->where('newer_note.status', CommunityNoteStatus::Helpful->value);
                }
            })->orderByDesc('created_at')->orderByDesc('id');
        } elseif (!isset($filters['moderation'])) {
            $query->orderBy('created_at')->orderBy('id');
        }
        $context = $context->withQuery($query);
        $pagination?->apply($query);

        return $context;
    }
}
