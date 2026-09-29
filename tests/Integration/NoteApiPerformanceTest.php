<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Service\HideCommunityNote;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\Api\Client;

class NoteApiPerformanceTest extends DomainTestCase
{
    public function test_moderator_list_batches_ratings_reasons_and_history(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        for ($i = 0; $i < 10; $i++) {
            $id = $this->note();
            $rating = $this->rating($id);
            $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
            self::$container->make(HideCommunityNote::class)->handle($actor, $id, ['reason' => '用于验证管理历史']);
        }
        $api = self::$container->make(Client::class)->withActor($actor);
        $api->get('/community-notes');
        $counts = [];
        foreach ([1, 10] as $limit) {
            $this->db->flushQueryLog();
            $this->db->enableQueryLog();
            try {
                $response = $api->withQueryParams(['page' => ['limit' => $limit]])->get('/community-notes');
                $counts[] = count($this->db->getQueryLog());
            } finally {
                $this->db->disableQueryLog();
            }
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $this->assertCount($limit, $body['data']);
            foreach ($body['data'] as $note) {
                $this->assertSame(['clear'], $note['attributes']['ratings'][0]['reasons']);
                $this->assertSame('用于验证管理历史', $note['attributes']['history'][0]['reason']);
            }
        }
        $this->assertSame($counts[0], $counts[1], '管理者列表的查询数量不应随附注数量增长。');
    }

    public function test_sparse_list_does_not_read_unused_moderation_collections(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        $this->note();
        $api = self::$container->make(Client::class)->withActor($actor);
        $this->db->flushQueryLog();
        $this->db->enableQueryLog();
        try {
            $response = $api->withQueryParams(['fields' => ['community-notes' => 'content,status,isHidden']])->get('/community-notes');
            $queries = $this->db->getQueryLog();
        } finally {
            $this->db->disableQueryLog();
        }
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayNotHasKey('ratings', $body['data'][0]['attributes']);
        $this->assertArrayNotHasKey('history', $body['data'][0]['attributes']);
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('note_ratings', $query['query']);
            $this->assertStringNotContainsString('note_history', $query['query']);
        }
    }

    public function test_feed_batches_visible_note_counts_for_raters_and_moderators(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $post = $this->post();
            $this->note(['post_id' => $post, 'status' => 'helpful']);
            $this->note(['post_id' => $post]);
            $this->note(['post_id' => $post, 'is_hidden' => true]);
        }
        foreach ([Permissions::RATE => 2, Permissions::MODERATE => 3] as $permission => $visibleCount) {
            $api = self::$container->make(Client::class)->withActor($this->contributor([$permission]));
            $query = [
                'filter' => ['feed' => 'helpful'],
                'include' => 'post',
                'fields' => [
                    'community-notes' => 'content,post',
                    'posts' => 'number,communityNoteCount,visibleCommunityNoteCount',
                ],
            ];
            $this->assertSame(200, $api->withQueryParams($query)->get('/community-notes')->getStatusCode());
            $counts = [];
            foreach ([1, 10] as $limit) {
                $query['page'] = ['limit' => $limit];
                $this->db->flushQueryLog();
                $this->db->enableQueryLog();
                try {
                    $response = $api->withQueryParams($query)->get('/community-notes');
                    $counts[] = count($this->db->getQueryLog());
                } finally {
                    $this->db->disableQueryLog();
                }
                $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
                $body = json_decode((string) $response->getBody(), true);
                $posts = array_values(array_filter($body['included'], fn ($item) => $item['type'] === 'posts'));
                $this->assertCount($limit, $posts);
                foreach ($posts as $post) {
                    $this->assertSame($visibleCount, $post['attributes']['visibleCommunityNoteCount']);
                }
            }
            $this->assertSame($counts[0], $counts[1], '同楼层附注计数不应随卡片数量增加逐条查询。');
        }
    }
}
