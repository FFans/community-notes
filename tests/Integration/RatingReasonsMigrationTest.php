<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Illuminate\Database\QueryException;

class RatingReasonsMigrationTest extends IntegrationTestCase
{
    public function test_rating_delete_cascades(): void
    {
        $rating = $this->rating($this->note());
        $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
        $this->db->table('ffans_community_notes_note_ratings')->where('id', $rating)->delete();
        $this->assertSame(0, $this->db->table('ffans_community_notes_rating_reasons')->count());
    }

    public function test_duplicate_reason_is_rejected(): void
    {
        $attributes = ['rating_id' => $this->rating($this->note()), 'reason' => 'clear'];
        $this->db->table('ffans_community_notes_rating_reasons')->insert($attributes);
        $this->expectException(QueryException::class);
        $this->db->table('ffans_community_notes_rating_reasons')->insert($attributes);
    }
}
