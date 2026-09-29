<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Tests\IntegrationTestCase;

class SourcesMigrationTest extends IntegrationTestCase
{
    public function test_source_storage_index_and_cascade(): void
    {
        $note = $this->note();
        $url = 'https://example.test/'.str_repeat('a', 2027);
        $id = $this->db->table('ffans_community_notes_note_sources')->insertGetId([
            'note_id' => $note, 'url' => $url, 'position' => 0, 'created_at' => '2026-09-25 12:00:00',
        ]);
        $this->assertSame($url, $this->db->table('ffans_community_notes_note_sources')->find($id)->url);
        $indexes = $this->db->getSchemaBuilder()->getIndexes('ffans_community_notes_note_sources');
        $this->assertContains(['note_id', 'position', 'id'], array_column($indexes, 'columns'));
        $this->db->table('ffans_community_notes_notes')->where('id', $note)->delete();
        $this->assertSame(0, $this->db->table('ffans_community_notes_note_sources')->count());
    }
}
