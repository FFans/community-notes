<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Service\RateCommunityNote;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\Guest;
use Flarum\User\User;

class RatingTest extends DomainTestCase
{
    public function test_rerating_changes_status_both_directions_and_history_only_on_transitions(): void
    {
        $actors = [];
        $id = $this->note();
        $service = self::$container->make(RateCommunityNote::class);
        for ($i = 0; $i < 5; $i++) {
            $actors[] = $actor = $this->contributor([Permissions::RATE]);
            $note = $service->handle($actor, $id, ['value' => 'helpful', 'reasons' => ['clear']]);
            $this->assertSame($i < 4 ? Status::NeedsMoreRatings : Status::Helpful, $note->status);
        }
        $this->assertSame(1, $note->history()->count());
        $note = $service->handle($actors[0], $id, ['value' => 'not_helpful', 'reasons' => ['incorrect']]);
        $this->assertSame(0.8, $note->score);
        $this->assertSame(Status::Helpful, $note->status);
        $this->assertSame(1, $note->history()->count());
        $note = $service->handle($actors[1], $id, ['value' => 'not_helpful', 'reasons' => ['incorrect']]);
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertSame(2, $note->history()->count());
        for ($i = 2; $i < 4; $i++) {
            $note = $service->handle($actors[$i], $id, ['value' => 'not_helpful', 'reasons' => ['incorrect']]);
        }
        $this->assertSame(Status::NotHelpful, $note->status);
        $this->assertSame(0.2, $note->score);
        $this->assertSame(5, $note->rating_count);
        $history = $note->history()->get();
        $this->assertSame(['helpful', 'needs_more_ratings', 'not_helpful'], $history->map(fn ($h) => $h->to_status->value)->all());
        $this->assertSame([1.0, 0.6, 0.2], $history->pluck('score')->all());
        $this->assertSame([5, 5, 5], $history->pluck('rating_count')->all());
        foreach ($actors as $actor) {
            $note = $service->handle($actor, $id, ['value' => 'helpful', 'reasons' => ['neutral']]);
        }
        $this->assertSame(Status::Helpful, $note->status);
        $this->assertSame(5, $note->ratings()->count());
        $this->assertSame(5, $note->history()->count());
    }

    public function test_invalid_rerating_preserves_value_reasons_aggregates_and_history(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $service = self::$container->make(RateCommunityNote::class);
        $note = $service->handle($actor, $this->note(), ['value' => 'helpful', 'reasons' => ['clear']]);
        foreach ([['value' => 'not_helpful', 'reasons' => ['clear']], ['value' => 'helpful', 'reasons' => ['clear', 'clear']], ['value' => 'helpful', 'reasons' => []]] as $input) {
            try {
                $service->handle($actor, $note->id, $input);
                $this->fail('无效修改不应写入');
            } catch (\Flarum\Foundation\ValidationException) {
                $this->assertSame('helpful', $note->ratings()->first()->value->value);
                $this->assertSame(['clear'], $note->ratings()->first()->reasons->map(fn ($r) => $r->reason->value)->all());
                $this->assertSame(1.0, $note->fresh()->score);
                $this->assertSame(0, $note->history()->count());
            }
        }
    }

    public function test_failure_after_reasons_and_history_rolls_back_whole_rating_transaction(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $id = $this->note();
        for ($i = 0; $i < 4; $i++) {
            $this->rating($id);
        }
        $service = self::$container->make(RateCommunityNote::class);
        $stopRejecting = $this->failOnDatabaseWrite('update', 'ffans_community_notes_notes', '评分写入失败');
        try {
            $service->handle($actor, $id, ['value' => 'helpful', 'reasons' => ['clear']]);
            $this->fail('应触发回滚');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('评分写入失败', $e->getMessage());
        } finally {
            $stopRejecting();
        }
        $note = CommunityNote::findOrFail($id);
        $this->assertSame(4, $note->ratings()->count());
        $this->assertSame(0, $note->ratings()->where('user_id', $actor->id)->count());
        $this->assertSame(0, $this->db->table('ffans_community_notes_rating_reasons')->count());
        $this->assertSame(0, $note->history()->count());
        $this->assertSame(0, $note->rating_count);
        $this->assertNull($note->score);
        $note = $service->handle($actor, $id, ['value' => 'helpful', 'reasons' => ['clear']]);
        $this->assertSame(Status::Helpful, $note->status);
        $this->assertSame(5, $note->rating_count);
    }

    public function test_reason_write_failure_restores_previous_rating_and_reasons(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $service = self::$container->make(RateCommunityNote::class);
        $note = $service->handle($actor, $this->note(), ['value' => 'helpful', 'reasons' => ['clear', 'neutral']]);
        $stopRejecting = $this->failOnDatabaseWrite('insert into', 'ffans_community_notes_rating_reasons', '理由写入失败', after: 1);
        try {
            $service->handle($actor, $note->id, ['value' => 'not_helpful', 'reasons' => ['incorrect', 'irrelevant']]);
            $this->fail('应触发回滚');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('理由写入失败', $e->getMessage());
        } finally {
            $stopRejecting();
        }
        $rating = $note->ratings()->first();
        $this->assertSame('helpful', $rating->value->value);
        $this->assertSame(['clear', 'neutral'], $rating->reasons->map(fn ($r) => $r->reason->value)->sort()->values()->all());
        $this->assertSame(1.0, $note->fresh()->score);
        $this->assertSame(1, $note->fresh()->rating_count);
    }

    public function test_admin_cannot_rate_own_note_and_deleted_author_note_remains_rateable(): void
    {
        $admin = User::findOrFail(1);
        $id = $this->note(['user_id' => $admin->id]);
        $service = self::$container->make(RateCommunityNote::class);
        try {
            $service->handle($admin, $id, ['value' => 'helpful', 'reasons' => ['clear']]);
            $this->fail('管理员也不能评价自己');
        } catch (PermissionDeniedException) {
            $this->assertSame(0, CommunityNote::findOrFail($id)->ratings()->count());
        }
        $this->db->table('ffans_community_notes_notes')->where('id', $id)->update(['user_id' => null]);
        $this->assertSame(1, $service->handle($admin, $id, ['value' => 'helpful', 'reasons' => ['clear']])->rating_count);
    }

    public function test_first_rating_and_upsert_replace_reasons_without_increasing_count(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $id = $this->note();
        $service = self::$container->make(RateCommunityNote::class);
        $note = $service->handle($actor, $id, ['value' => 'helpful', 'reasons' => ['clear', 'neutral']]);
        $rating = $note->ratings()->first();
        $this->assertSame(1, $note->rating_count);
        $this->assertSame(1, $note->helpful_count);
        $this->assertSame(1.0, $note->score);
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertSame(0, $note->history()->count());
        $this->assertSame($actor->id, $rating->user_id);
        $createdAt = $rating->created_at;
        $note = $service->handle($actor, $id, ['value' => 'somewhat_helpful', 'reasons' => ['partially_helpful']]);
        $this->assertSame(1, $note->ratings()->count());
        $updated = $note->ratings()->first();
        $this->assertSame($rating->id, $updated->id);
        $this->assertTrue($createdAt->equalTo($updated->created_at));
        $this->assertSame(['partially_helpful'], $updated->reasons->map(fn ($r) => $r->reason->value)->all());
        $this->assertSame([1, 0, 1, 0], [$note->rating_count, $note->helpful_count, $note->somewhat_helpful_count, $note->not_helpful_count]);
        $this->assertSame(0.5, $note->score);
        $note = $service->handle($actor, $id, ['value' => 'not_helpful', 'reasons' => ['incorrect']]);
        $this->assertSame(1, $note->rating_count);
        $this->assertSame(1, $note->not_helpful_count);
        $this->assertSame(0.0, $note->score);
    }

    public function test_rating_enforces_permissions_own_note_hidden_note_and_original_visibility(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $note = CommunityNote::findOrFail($this->note());
        $service = self::$container->make(RateCommunityNote::class);
        $input = ['value' => 'helpful', 'reasons' => ['clear']];
        foreach ([new Guest(), User::findOrFail($this->user()), $this->contributor([Permissions::MODERATE])] as $denied) {
            try {
                $service->handle($denied, $note->id, $input);
                $this->fail('无评价权限');
            } catch (PermissionDeniedException) {
                $this->assertSame(0, $note->ratings()->count());
            }
        }
        foreach (['own', 'hidden', 'private_post', 'hidden_post'] as $state) {
            $this->db->table('ffans_community_notes_notes')->where('id', $note->id)->update(['user_id' => $state === 'own' ? $actor->id : null, 'is_hidden' => $state === 'hidden']);
            $this->db->table('posts')->where('id', $note->post_id)->update(['is_private' => $state === 'private_post', 'hidden_at' => $state === 'hidden_post' ? '2026-09-25 12:00:00' : null]);
            try {
                $service->handle($actor, $note->id, $input);
                $this->fail('应拒绝评价');
            } catch (PermissionDeniedException) {
                $this->assertSame(0, $note->ratings()->count());
                $this->assertSame(0, $note->fresh()->rating_count);
            }
        }
    }
}
