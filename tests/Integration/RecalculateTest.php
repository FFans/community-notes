<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Scoring\NoteScorer;
use FFans\CommunityNotes\Scoring\SimpleNoteScorer;
use FFans\CommunityNotes\Service\RecalculateCommunityNote;
use FFans\CommunityNotes\Tests\IntegrationTestCase;

class RecalculateTest extends IntegrationTestCase
{
    public function test_transitions_history_and_cached_aggregates(): void
    {
        $this->assertInstanceOf(SimpleNoteScorer::class, self::$container->make(NoteScorer::class));
        $service = self::$container->make(RecalculateCommunityNote::class);
        $id = $this->note(['is_hidden' => true]);
        $note = $service->handle($id);
        $initialTime = $note->status_changed_at;
        $this->assertNull($note->score);
        $this->assertSame(0, $note->rating_count);
        $this->assertSame(0, $note->history()->count());

        for ($i = 0; $i < 5; $i++) {
            $this->rating($id);
        }
        $note = $service->handle($id);
        $this->assertSame(Status::Helpful, $note->status);
        $this->assertSame([5, 5, 0, 0], [$note->rating_count, $note->helpful_count, $note->somewhat_helpful_count, $note->not_helpful_count]);
        $this->assertSame(1.0, $note->score);
        $this->assertTrue($note->is_hidden);
        $this->assertFalse($initialTime->equalTo($note->status_changed_at));
        $history = $note->history()->first();
        $this->assertSame(CommunityNoteHistoryEvent::StatusChanged, $history->event);
        $this->assertSame(Status::NeedsMoreRatings, $history->from_status);
        $this->assertSame(Status::Helpful, $history->to_status);
        $this->assertSame(5, $history->rating_count);
        $this->assertSame(1.0, $history->score);
        $this->assertNull($history->actor_user_id);

        $again = $service->handle($id);
        $this->assertTrue($note->status_changed_at->equalTo($again->status_changed_at));
        $this->assertSame(1, $again->history()->count());
        $ratings = $note->ratings()->pluck('id');
        $this->db->table('ffans_community_notes_note_ratings')->whereIn('id', $ratings->take(2))->update(['value' => 'not_helpful']);
        $note = $service->handle($id);
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertSame(0.6, $note->score);
        $this->assertSame(2, $note->history()->count());
        $this->db->table('ffans_community_notes_note_ratings')->where('note_id', $id)->update(['value' => 'not_helpful']);
        $note = $service->handle($id);
        $this->assertSame(Status::NotHelpful, $note->status);
        $this->assertSame(3, $note->history()->count());
        $this->assertSame(5, $note->not_helpful_count);
    }

    public function test_failure_rolls_back_history_and_note_together(): void
    {
        $id = $this->note();
        for ($i = 0; $i < 5; $i++) {
            $this->rating($id);
        }
        // 在历史写入后让附注更新失败，验证两者属于同一事务。
        $stopRejecting = $this->failOnDatabaseWrite('update', 'ffans_community_notes_notes', '测试回滚');
        try {
            self::$container->make(RecalculateCommunityNote::class)->handle($id);
            $this->fail('应拒绝更新');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('测试回滚', $e->getMessage());
        } finally {
            $stopRejecting();
        }
        $note = CommunityNote::findOrFail($id);
        $this->assertSame(0, $note->rating_count);
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertSame(0, $note->history()->count());
    }
}
