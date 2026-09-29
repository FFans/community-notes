<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Illuminate\Database\QueryException;

class RatingsMigrationTest extends IntegrationTestCase
{
    public function test_deleted_users_preserve_multiple_anonymous_ratings(): void
    {
        $note = $this->note();
        foreach ([$this->user(), $this->user()] as $user) {
            $this->rating($note, $user);
            $this->db->table('users')->where('id', $user)->delete();
        }
        $this->assertSame(2, $this->db->table('ffans_community_notes_note_ratings')->whereNull('user_id')->count());
        $this->db->table('ffans_community_notes_notes')->where('id', $note)->delete();
        $this->assertSame(0, $this->db->table('ffans_community_notes_note_ratings')->count());
    }

    public function test_duplicate_rating_is_rejected(): void
    {
        $note = $this->note();
        $user = $this->user();
        $this->rating($note, $user);
        $this->expectException(QueryException::class);
        $this->rating($note, $user);
    }
}
