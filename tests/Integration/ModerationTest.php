<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Service\HideCommunityNote;
use FFans\CommunityNotes\Service\RestoreCommunityNote;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;

class ModerationTest extends DomainTestCase
{
    public function test_hide_preserves_score_and_ratings_and_records_snapshot_once(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        $id = $this->note(['status' => 'helpful', 'score' => 1, 'rating_count' => 1]);
        $this->rating($id);
        $service = self::$container->make(HideCommunityNote::class);
        $note = $service->handle($actor, $id, ['reason' => '  来源内容违规  ']);
        $this->assertTrue($note->is_hidden);
        $this->assertSame((int) $actor->id, $note->hidden_by_user_id);
        $this->assertNotNull($note->hidden_at);
        $this->assertSame('来源内容违规', $note->hidden_reason);
        $this->assertSame('helpful', $note->status->value);
        $this->assertSame(1.0, $note->score);
        $this->assertSame(1, $note->ratings()->count());
        $entry = $note->history()->firstOrFail();
        $this->assertSame('hidden', $entry->event->value);
        $this->assertSame('helpful', $entry->from_status->value);
        $this->assertSame($entry->from_status, $entry->to_status);
        $this->assertSame(1.0, $entry->score);
        $this->assertSame(1, $entry->rating_count);
        $this->assertSame((int) $actor->id, $entry->actor_user_id);
        $service->handle($actor, $id, ['reason' => '重复请求']);
        $this->assertSame(1, $note->history()->count());
        $this->assertSame('来源内容违规', $note->fresh()->hidden_reason);
    }

    public function test_hide_requires_permission_visible_post_and_nonempty_reason(): void
    {
        $service = self::$container->make(HideCommunityNote::class);
        $id = $this->note();
        try {
            $service->handle(User::findOrFail($this->user()), $id, ['reason' => '违规']);
            $this->fail('普通成员不应能够隐藏附注。');
        } catch (PermissionDeniedException) {
            $this->assertFalse(CommunityNote::findOrFail($id)->is_hidden);
        }
        $actor = $this->contributor([Permissions::MODERATE]);
        foreach ([null, '', '   ', "\u{3000}", [], true] as $reason) {
            try {
                $service->handle($actor, $id, ['reason' => $reason]);
                $this->fail('无效理由不应保存。');
            } catch (ValidationException) {
                $this->assertFalse(CommunityNote::findOrFail($id)->is_hidden);
            }
        }
        $this->db->table('posts')->where('id', CommunityNote::findOrFail($id)->post_id)->update(['is_private' => true]);
        $this->expectException(PermissionDeniedException::class);
        $service->handle($actor, $id, ['reason' => '不可见']);
    }

    public function test_restore_clears_current_fields_and_preserves_original_history(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        $id = $this->note(['status' => 'helpful', 'score' => 0.9, 'rating_count' => 5]);
        $hidden = self::$container->make(HideCommunityNote::class)->handle($actor, $id, ['reason' => '隐藏原因']);
        $original = $hidden->history()->firstOrFail()->getAttributes();
        $service = self::$container->make(RestoreCommunityNote::class);
        $restored = $service->handle($actor, $id);
        $this->assertFalse($restored->is_hidden);
        foreach (['hidden_at', 'hidden_by_user_id', 'hidden_reason'] as $field) {
            $this->assertNull($restored->$field);
        }
        $this->assertSame($hidden->status, $restored->status);
        $this->assertEquals($hidden->status_changed_at, $restored->status_changed_at);
        $this->assertSame($original, $restored->history()->firstOrFail()->getAttributes());
        $history = $restored->history()->get();
        $this->assertCount(2, $history);
        $this->assertSame('restored', $history[1]->event->value);
        $this->assertSame((int) $actor->id, $history[1]->actor_user_id);
        $this->assertSame(0.9, $history[1]->score);
        $this->assertSame(5, $history[1]->rating_count);
        $service->handle($actor, $id);
        $this->assertSame(2, $restored->history()->count());
        $this->expectException(PermissionDeniedException::class);
        $service->handle(User::findOrFail($this->user()), $id);
    }

    public function test_history_failure_rolls_back_both_hide_and_restore(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        $id = $this->note();
        foreach ([false, true] as $hidden) {
            if ($hidden) {
                self::$container->make(HideCommunityNote::class)->handle($actor, $id, ['reason' => '原理由']);
            }
            $before = CommunityNote::findOrFail($id)->getAttributes();
            $stopRejecting = $this->failOnDatabaseWrite('insert into', 'ffans_community_notes_note_history', '历史写入失败');
            try {
                if ($hidden) {
                    self::$container->make(RestoreCommunityNote::class)->handle($actor, $id);
                } else {
                    self::$container->make(HideCommunityNote::class)->handle($actor, $id, ['reason' => '隐藏理由']);
                }
                $this->fail('历史失败应回滚。');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertStringContainsString('历史写入失败', $exception->getMessage());
            } finally {
                $stopRejecting();
            }
            $this->assertSame($before, CommunityNote::findOrFail($id)->getAttributes());
            $this->assertSame($hidden ? 1 : 0, CommunityNote::findOrFail($id)->history()->count());
        }
    }
}
