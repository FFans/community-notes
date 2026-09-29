<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Illuminate\Database\QueryException;

class NotesMigrationTest extends IntegrationTestCase
{
    public function test_defaults_and_nullable_user_deletion(): void
    {
        $user = $this->user();
        $id = $this->note(['user_id' => $user, 'hidden_by_user_id' => $user]);
        $note = $this->db->table('ffans_community_notes_notes')->find($id);
        $this->assertSame('needs_more_ratings', $note->status);
        foreach (['rating_count', 'helpful_count', 'somewhat_helpful_count', 'not_helpful_count', 'is_hidden'] as $column) {
            $this->assertEquals(0, $note->$column);
        }
        $this->assertNull($note->score);
        $this->assertNotNull($note->created_at);
        $this->assertNotNull($note->updated_at);
        $this->db->table('users')->where('id', $user)->delete();
        $note = $this->db->table('ffans_community_notes_notes')->find($id);
        $this->assertNull($note->user_id);
        $this->assertNull($note->hidden_by_user_id);
    }

    public function test_post_delete_cascades(): void
    {
        $post = $this->post();
        $this->note(['post_id' => $post]);
        $this->db->table('posts')->where('id', $post)->delete();
        $this->assertSame(0, $this->db->table('ffans_community_notes_notes')->count());
    }

    public function test_duplicate_user_post_is_rejected(): void
    {
        $attributes = ['post_id' => $this->post(), 'user_id' => $this->user()];
        $this->note($attributes);
        $this->expectException(QueryException::class);
        $this->note($attributes);
    }

    public function test_multiple_deleted_authors_are_allowed(): void
    {
        $attributes = ['post_id' => $this->post(), 'user_id' => null];
        $this->note($attributes);
        $this->note($attributes);
        $this->assertSame(2, $this->db->table('ffans_community_notes_notes')->count());
    }

    public function test_required_indexes_exist(): void
    {
        $indexes = $this->db->getSchemaBuilder()->getIndexes('ffans_community_notes_notes');
        $columns = array_column($indexes, 'columns');
        foreach ([['post_id'], ['user_id'], ['status'], ['is_hidden'], ['post_id', 'status', 'is_hidden']] as $expected) {
            $this->assertContains($expected, $columns);
        }
    }
}
