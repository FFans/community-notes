<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Tests\IntegrationTestCase;

class HistoryMigrationTest extends IntegrationTestCase
{
    public function test_actor_deletion_preserves_history_and_post_deletion_cascades_all_data(): void
    {
        $post = $this->post();
        $note = $this->note(['post_id' => $post]);
        $actor = $this->user();
        $rating = $this->rating($note);
        $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
        $this->db->table('ffans_community_notes_note_sources')->insert([
            'note_id' => $note, 'url' => 'https://example.test', 'position' => 0, 'created_at' => '2026-09-25 12:00:00',
        ]);
        $history = $this->db->table('ffans_community_notes_note_history')->insertGetId([
            'note_id' => $note, 'event' => 'hidden', 'actor_user_id' => $actor,
            'rating_count' => 1, 'reason' => '测试管理原因', 'created_at' => '2026-09-25 12:00:00',
        ]);
        $this->db->table('users')->where('id', $actor)->delete();
        $record = $this->db->table('ffans_community_notes_note_history')->find($history);
        $this->assertNull($record->actor_user_id);
        $this->assertSame('测试管理原因', $record->reason);
        $this->assertNull($record->score);
        $this->assertNull($record->from_status);
        $this->assertNull($record->to_status);
        $this->db->table('posts')->where('id', $post)->delete();
        foreach (['notes', 'note_sources', 'note_ratings', 'rating_reasons', 'note_history'] as $suffix) {
            $this->assertSame(0, $this->db->table('ffans_community_notes_'.$suffix)->count());
        }
    }

    public function test_migrations_can_roll_back_and_reinstall(): void
    {
        $schema = $this->db->getSchemaBuilder();
        $files = glob(dirname(__DIR__, 2).'/migrations/*.php');
        // MySQL/MariaDB 的 DDL 会隐式提交，迁移完成后再为测试数据开启事务。
        $this->db->rollBack();
        try {
            foreach (array_reverse($files) as $file) {
                (require $file)['down']($schema);
            }
            $this->assertFalse($schema->hasTable('ffans_community_notes_notes'));
            foreach ($files as $file) {
                (require $file)['up']($schema);
            }
        } finally {
            $this->db->beginTransaction();
        }
        $this->assertGreaterThan(0, $this->note());
        $this->assertTrue($schema->hasTable('ffans_community_notes_note_history'));
    }
}
