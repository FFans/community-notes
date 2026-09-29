<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\Api\Client;
use Flarum\User\Guest;

class PostNoteTest extends DomainTestCase
{
    public function test_management_count_includes_pending_and_hidden_notes_and_updates_after_deletion(): void
    {
        $post = $this->post();
        $moderator = $this->contributor([Permissions::MODERATE]);
        $api = self::$container->make(Client::class);
        $read = fn($actor) => json_decode((string) $api->withActor($actor)->get('/posts/'.$post)->getBody(), true)['data']['attributes'];
        $this->assertSame(0, $read($moderator)['communityNoteCount']);
        $pending = $this->note(['post_id' => $post]);
        $hidden = $this->note(['post_id' => $post, 'is_hidden' => true]);
        $this->assertSame(2, $read($moderator)['communityNoteCount']);
        foreach ([new Guest(), $this->contributor([Permissions::CREATE]), $this->contributor([Permissions::RATE])] as $actor) {
            $this->assertArrayNotHasKey('communityNoteCount', $read($actor));
        }
        foreach ([$pending, $hidden] as $id) {
            $this->assertSame(204, $api->withActor($moderator)->delete('/community-notes/'.$id)->getStatusCode());
        }
        $this->assertSame(0, $read($moderator)['communityNoteCount']);
    }

    public function test_management_count_adds_only_one_batch_query_for_one_or_ten_posts(): void
    {
        $moderator = $this->contributor([Permissions::MODERATE]);
        $post = $this->post();
        $discussion = $this->db->table('posts')->where('id', $post)->value('discussion_id');
        $this->note(['post_id' => $post]);
        for ($number = 2; $number <= 10; $number++) {
            $id = $this->post();
            $this->db->table('posts')->where('id', $id)->update(['discussion_id' => $discussion, 'number' => $number]);
            $this->note(['post_id' => $id, 'is_hidden' => true]);
        }
        $api = self::$container->make(Client::class)->withActor($moderator);
        $api->get('/posts/'.$post);
        foreach ([1, 10] as $limit) {
            $counts = [];
            foreach (['myCommunityNoteId', 'myCommunityNoteId,communityNoteCount'] as $fields) {
                $this->db->flushQueryLog();
                $this->db->enableQueryLog();
                try {
                    $response = $api->withQueryParams([
                        'filter' => ['discussion' => $discussion], 'page' => ['limit' => $limit],
                        'fields' => ['posts' => $fields], 'include' => '',
                    ])->get('/posts');
                    $counts[] = count($this->db->getQueryLog());
                } finally {
                    $this->db->disableQueryLog();
                }
                $this->assertSame(200, $response->getStatusCode());
                $body = json_decode((string) $response->getBody(), true);
                $this->assertCount($limit, $body['data']);
                if (str_contains($fields, 'communityNoteCount')) {
                    foreach ($body['data'] as $row) {
                        $this->assertSame(1, $row['attributes']['communityNoteCount']);
                    }
                }
            }
            $this->assertSame($counts[0] + 1, $counts[1], '管理入口只增加一次批量查询。');
        }
    }

    public function test_public_visibility_matrix_covers_detail_list_and_post_relationship(): void
    {
        $api = self::$container->make(Client::class)->withActor(new Guest());
        foreach ([
            ['helpful', false, false, true],
            ['needs_more_ratings', false, false, false],
            ['not_helpful', false, false, false],
            ['helpful', true, false, false],
            ['helpful', false, true, false],
        ] as [$status, $hidden, $private, $visible]) {
            $post = $this->post();
            $id = $this->note(['post_id' => $post, 'status' => $status, 'is_hidden' => $hidden]);
            $this->db->table('posts')->where('id', $post)->update(['is_private' => $private]);
            $this->assertSame($visible ? 200 : 404, $api->get('/community-notes/'.$id)->getStatusCode());
            $response = $api->withQueryParams(['filter' => ['post' => $post]])->get('/community-notes');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertCount($visible ? 1 : 0, json_decode((string) $response->getBody(), true)['data']);
            $response = $api->get('/posts/'.$post);
            $this->assertSame($private ? 404 : 200, $response->getStatusCode());
            if (!$private) {
                $relation = json_decode((string) $response->getBody(), true)['data']['relationships']['communityNote']['data'];
                $this->assertSame($visible ? (string) $id : null, $relation['id'] ?? null);
            }
        }
    }

    public function test_stream_note_queries_are_batched_for_guests_raters_authors_and_moderators(): void
    {
        $author = $this->contributor([Permissions::CREATE]);
        $rater = $this->contributor([Permissions::RATE]);
        $post = $this->post();
        $discussion = $this->db->table('posts')->where('id', $post)->value('discussion_id');
        $note = $this->note(['post_id' => $post, 'user_id' => $author->id, 'status' => 'helpful']);
        $rating = $this->rating($note, $rater->id);
        $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
        for ($i = 2; $i <= 10; $i++) {
            $id = $this->post();
            $this->db->table('posts')->where('id', $id)->update(['discussion_id' => $discussion, 'number' => $i]);
            $this->note(['post_id' => $id, 'user_id' => $author->id, 'status' => 'helpful']);
        }
        foreach ([new Guest(), $rater, $author, \Flarum\User\User::findOrFail(1)] as $actor) {
            $counts = [];
            foreach ([1, 10] as $limit) {
                $this->db->flushQueryLog();
                $this->db->enableQueryLog();
                $response = self::$container->make(Client::class)->withActor($actor)->withQueryParams([
                    'filter' => ['discussion' => $discussion], 'page' => ['limit' => $limit],
                ])->get('/posts');
                $queries = $this->db->getQueryLog();
                $this->db->disableQueryLog();
                $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
                $body = json_decode((string) $response->getBody(), true);
                $this->assertCount($limit, $body['data']);
                $this->assertCount($limit, array_filter($body['included'], fn ($row) => $row['type'] === 'community-notes'));
                $counts[] = count(array_filter($queries, fn ($q) => str_contains($q['query'], 'ffans_community_notes')));
            }
            $this->assertSame($counts[0], $counts[1], '附注查询不能随帖子数量增长：用户 '.($actor->id ?? 'guest'));
            // 当前用户的评价及理由各增加一次批量查询，不能按帖子逐条读取。
            // 管理者额外读取一次批量计数，用于判断管理入口；普通用户不增加查询。
            $this->assertLessThanOrEqual($actor->isAdmin() ? 6 : 5, $counts[1]);
        }
    }

    public function test_public_card_only_receives_the_current_raters_rating(): void
    {
        $rater = $this->contributor([Permissions::RATE]);
        $other = $this->contributor([Permissions::RATE]);
        $reader = $this->contributor([]);
        $post = $this->post();
        $note = $this->note(['post_id' => $post, 'status' => 'helpful']);
        $rating = $this->rating($note, $rater->id);
        $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
        $read = function ($actor) use ($post) {
            $response = self::$container->make(Client::class)->withActor($actor)->get('/posts/'.$post);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $note = array_values(array_filter($body['included'], fn ($row) => $row['type'] === 'community-notes'))[0];
            $this->assertArrayNotHasKey('ratings', $note['attributes']);
            $this->assertArrayNotHasKey('user', $note['relationships']);

            return $note['attributes'];
        };
        $this->assertSame(['value' => 'helpful', 'reasons' => ['clear']], $read($rater)['myRating']);
        $this->assertNull($read($other)['myRating']);
        $this->assertArrayNotHasKey('myRating', $read(new Guest()));
        $this->assertArrayNotHasKey('myRating', $read($reader));
    }

    public function test_public_relationship_selects_one_winner_and_explicit_null_after_removal(): void
    {
        $post = $this->post();
        $first = $this->note(['post_id' => $post, 'status' => 'helpful', 'score' => 0.8, 'rating_count' => 5]);
        $winner = $this->note(['post_id' => $post, 'status' => 'helpful', 'score' => 1, 'rating_count' => 6]);
        $earlier = $this->note(['post_id' => $post, 'status' => 'helpful', 'score' => 1, 'rating_count' => 6]);
        $this->note(['post_id' => $post, 'status' => 'helpful', 'score' => 1, 'rating_count' => 100, 'is_hidden' => true]);
        $api = self::$container->make(Client::class)->withActor(new Guest());
        $read = function () use ($api, $post) {
            $response = $api->get('/posts/'.$post);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            return json_decode((string) $response->getBody(), true);
        };
        $body = $read();
        $this->assertSame((string) $winner, $body['data']['relationships']['communityNote']['data']['id']);
        $notes = array_values(array_filter($body['included'], fn ($row) => $row['type'] === 'community-notes'));
        $this->assertCount(1, $notes);
        $this->assertArrayNotHasKey('user', $notes[0]['relationships']);
        $this->db->table('ffans_community_notes_notes')->where('id', $earlier)->update(['status_changed_at' => '2026-09-24 12:00:00']);
        $this->assertSame((string) $earlier, $read()['data']['relationships']['communityNote']['data']['id']);
        $this->db->table('ffans_community_notes_notes')->where('id', $first)->update(['score' => 1, 'rating_count' => 7]);
        $this->assertSame((string) $first, $read()['data']['relationships']['communityNote']['data']['id']);
        $discussion = $this->db->table('posts')->where('id', $post)->value('discussion_id');
        $this->db->table('discussions')->where('id', $discussion)->update(['first_post_id' => $post, 'last_post_id' => $post]);
        $response = $api->get('/discussions/'.$discussion);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(1, array_filter($body['included'], fn ($row) => $row['type'] === 'community-notes'), json_encode($body));
        $this->db->table('ffans_community_notes_notes')->where('post_id', $post)->update(['status' => 'needs_more_ratings']);
        $this->assertNull($read()['data']['relationships']['communityNote']['data']);
    }

    public function test_hidden_post_has_no_public_note_even_for_admin(): void
    {
        $post = $this->post();
        $this->note(['post_id' => $post, 'status' => 'helpful']);
        $this->db->table('posts')->where('id', $post)->update(['hidden_at' => '2026-09-25 12:00:00']);
        $response = self::$container->make(Client::class)->withActor(\Flarum\User\User::findOrFail(1))->get('/posts/'.$post);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull(json_decode((string) $response->getBody(), true)['data']['relationships']['communityNote']['data']);
    }

    public function test_post_only_exposes_current_authors_note_id(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $api = self::$container->make(Client::class);
        $read = fn ($user) => json_decode((string) $api->withActor($user)->get('/posts/'.$post)->getBody(), true)['data']['attributes'];
        $this->assertArrayNotHasKey('myCommunityNoteId', $read(new Guest()));
        $this->note(['post_id' => $post]);
        $this->assertNull($read($actor)['myCommunityNoteId']);
        $own = $this->note(['post_id' => $post, 'user_id' => $actor->id, 'is_hidden' => true]);
        $this->assertSame($own, $read($actor)['myCommunityNoteId']);
    }
}
