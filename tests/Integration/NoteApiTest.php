<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\Api\Client;
use Flarum\User\Guest;
use Flarum\User\User;

class NoteApiTest extends DomainTestCase
{
    protected function api(?User $actor = null): Client
    {
        return self::$container->make(Client::class)->withActor($actor ?? new Guest());
    }

    public function test_moderation_list_includes_all_notes_and_filters_hidden_before_pagination(): void
    {
        $moderator = $this->contributor([Permissions::MODERATE]);
        $visible = $this->note();
        $hidden = $this->note(['is_hidden' => true]);
        $newest = $this->note(['is_hidden' => true, 'status' => 'helpful', 'created_at' => '2026-09-28 12:00:00']);
        $privatePost = $this->post();
        $this->db->table('posts')->where('id', $privatePost)->update(['is_private' => true]);
        $this->note(['is_hidden' => true, 'post_id' => $privatePost]);
        $query = ['filter' => ['moderation' => 'all'], 'page' => ['limit' => 1]];
        $response = $this->api($moderator)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(3, $body['meta']['page']['total']);
        $this->assertSame([(string) $newest], array_column($body['data'], 'id'));
        $query['filter']['moderation'] = 'hidden';
        $query['page']['offset'] = 1;
        $body = json_decode((string) $this->api($moderator)->withQueryParams($query)->get('/community-notes')->getBody(), true);
        $this->assertSame(2, $body['meta']['page']['total']);
        $this->assertSame([(string) $hidden], array_column($body['data'], 'id'));
        $this->assertTrue($body['data'][0]['attributes']['isHidden']);
        $this->assertSame(200, $this->api(User::findOrFail(1))->withQueryParams($query)->get('/community-notes')->getStatusCode());
        foreach ([new Guest(), $this->contributor([Permissions::RATE]), $this->contributor([Permissions::CREATE])] as $actor) {
            $this->assertSame(403, $this->api($actor)->withQueryParams($query)->get('/community-notes')->getStatusCode());
        }
        foreach ([['moderation' => 'bad'], ['moderation' => []], ['moderation' => 'hidden', 'feed' => 'helpful']] as $filter) {
            $this->assertSame(400, $this->api($moderator)->withQueryParams(['filter' => $filter])->get('/community-notes')->getStatusCode());
        }
    }

    public function test_admin_and_moderator_can_permanently_delete_rated_and_hidden_notes(): void
    {
        foreach ([User::findOrFail(1), $this->contributor([Permissions::MODERATE])] as $actor) {
            $id = $this->note(['status' => 'helpful', 'score' => 1, 'rating_count' => 1]);
            $rating = $this->rating($id);
            $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
            $this->db->table('ffans_community_notes_note_sources')->insert([
                'note_id' => $id, 'url' => 'https://example.test', 'position' => 0,
                'created_at' => '2026-09-28 12:00:00',
            ]);
            $url = '/community-notes/'.$id;
            $this->assertSame(200, $this->api($actor)->withBody(['reason' => '测试隐藏'])->post($url.'/hide')->getStatusCode());
            $this->assertSame(1, $this->db->table('ffans_community_notes_note_history')->where('note_id', $id)->count());
            if ($actor->id === 1) {
                $this->assertSame(200, $this->api($actor)->post($url.'/restore')->getStatusCode());
            }
            $response = $this->api($actor)->delete($url);
            $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());
            $this->assertFalse($this->db->table('ffans_community_notes_notes')->where('id', $id)->exists());
            foreach (['note_sources', 'note_ratings', 'note_history'] as $table) {
                $this->assertSame(0, $this->db->table('ffans_community_notes_'.$table)->where('note_id', $id)->count());
            }
            $this->assertSame(0, $this->db->table('ffans_community_notes_rating_reasons')->where('rating_id', $rating)->count());
        }
        $post = $this->post();
        $id = $this->note(['post_id' => $post]);
        $this->db->table('posts')->where('id', $post)->update(['is_private' => true]);
        $this->assertSame(404, $this->api($actor)->delete('/community-notes/'.$id)->getStatusCode());
        $this->assertTrue($this->db->table('ffans_community_notes_notes')->where('id', $id)->exists());
    }

    public function test_show_and_list_apply_visibility_before_pagination(): void
    {
        $public = $this->note(['status' => 'helpful']);
        $private = $this->note();
        $hidden = $this->note(['status' => 'helpful', 'is_hidden' => true]);
        $post = $this->post();
        $this->db->table('posts')->where('id', $post)->update(['is_private' => true]);
        $invisible = $this->note(['status' => 'helpful', 'post_id' => $post]);
        foreach ([$private, $hidden, $invisible] as $id) {
            $response = $this->api()->get('/community-notes/'.$id);
            $this->assertSame(404, $response->getStatusCode(), (string) $response->getBody());
        }
        $response = $this->api()->withQueryParams(['page' => ['limit' => 1]])->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $public], array_column($body['data'], 'id'));
        $this->assertSame(1, $body['meta']['page']['total']);
        $this->assertArrayNotHasKey('user', $body['data'][0]['relationships']);
        $this->assertArrayNotHasKey('score', $body['data'][0]['attributes']);
    }

    public function test_own_unrated_note_list_query_count_does_not_grow_with_page_size(): void
    {
        $author = $this->contributor([Permissions::CREATE]);
        for ($i = 0; $i < 10; $i++) {
            $this->note(['user_id' => $author->id]);
        }
        $api = $this->api($author);
        $this->assertSame(200, $api->get('/community-notes')->getStatusCode());
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
                $this->assertTrue($note['attributes']['isMine']);
                $this->assertSame(0, $note['attributes']['ratingCount']);
                foreach (['canRate', 'canEdit', 'canDelete'] as $field) {
                    $this->assertArrayNotHasKey($field, $note['attributes']);
                }
            }
        }
        $this->assertSame($counts[0], $counts[1], '附注列表不应随条数增加逐条权限查询');
    }

    public function test_queue_order_filters_and_permissions(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $helpful = $this->note(['status' => 'helpful']);
        $rated = $this->note();
        $this->rating($rated, $actor->id);
        $unrated = $this->note();
        $this->note(['user_id' => $actor->id]);
        $this->note(['is_hidden' => true]);
        $query = ['filter' => ['queue' => 'rating'], 'page' => ['limit' => 2]];
        $response = $this->api($actor)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $unrated, (string) $rated], array_column($body['data'], 'id'));
        $this->assertSame(3, $body['meta']['page']['total']);
        $query['page']['offset'] = 2;
        $body = json_decode((string) $this->api($actor)->withQueryParams($query)->get('/community-notes')->getBody(), true);
        $this->assertSame([(string) $helpful], array_column($body['data'], 'id'));
        $this->assertSame(403, $this->api()->withQueryParams($query)->get('/community-notes')->getStatusCode());
        foreach ([['status' => 'bad'], ['queue' => 'bad'], ['post' => []], ['unknown' => 'x']] as $filter) {
            $this->assertSame(400, $this->api($actor)->withQueryParams(['filter' => $filter])->get('/community-notes')->getStatusCode());
        }
    }

    public function test_feed_groups_posts_before_pagination_and_orders_latest_first(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $post = $this->post();
        $older = $this->note(['post_id' => $post, 'status' => 'helpful']);
        $newer = $this->note(['post_id' => $post, 'created_at' => '2026-09-26 12:00:00']);
        $other = $this->note(['status' => 'helpful', 'created_at' => '2026-09-26 13:00:00']);
        $pendingPost = $this->post();
        $this->note(['post_id' => $pendingPost]);
        $pending = $this->note(['post_id' => $pendingPost, 'created_at' => '2026-09-26 14:00:00']);
        $notHelpful = $this->note(['status' => 'not_helpful', 'created_at' => '2026-09-26 15:00:00']);
        $this->note(['post_id' => $post, 'is_hidden' => true, 'created_at' => '2026-09-27 12:00:00']);
        $query = ['filter' => ['feed' => 'latest'], 'page' => ['limit' => 1],
            'include' => 'post,post.user,post.user.groups,post.discussion,post.discussion.user,post.communityNote,user'];
        $response = $this->api($actor)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $notHelpful], array_column($body['data'], 'id'));
        $this->assertSame(2, $body['meta']['page']['total']);
        $query['page']['offset'] = 1;
        $body = json_decode((string) $this->api($actor)->withQueryParams($query)->get('/community-notes')->getBody(), true);
        $this->assertSame([(string) $pending], array_column($body['data'], 'id'));
        $query['filter']['feed'] = 'helpful';
        $body = json_decode((string) $this->api($actor)->withQueryParams($query)->get('/community-notes')->getBody(), true);
        $this->assertSame([(string) $older], array_column($body['data'], 'id'));

        // 详情仍返回同帖所有可见附注，包含作者自己的附注。
        $response = $this->api($actor)->withQueryParams([
            'filter' => ['post' => $post], 'include' => 'post,post.communityNote',
        ])->get('/community-notes');
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $older, (string) $newer], array_column($body['data'], 'id'));
        $includedPost = array_values(array_filter($body['included'], fn ($item) => $item['type'] === 'posts'))[0];
        $this->assertSame((string) $older, $includedPost['relationships']['communityNote']['data']['id']);
        $this->assertSame(403, $this->api()->withQueryParams($query)->get('/community-notes')->getStatusCode());
        foreach ([['feed' => 'bad'], ['feed' => []], ['feed' => 'latest', 'queue' => 'rating']] as $filter) {
            $this->assertSame(400, $this->api($actor)->withQueryParams(['filter' => $filter])->get('/community-notes')->getStatusCode());
        }
    }

    public function test_visible_note_count_matches_detail_permissions_and_keeps_moderator_total(): void
    {
        $post = $this->post();
        $this->note(['post_id' => $post, 'status' => 'helpful']);
        $this->note(['post_id' => $post]);
        $this->note(['post_id' => $post, 'is_hidden' => true]);
        $cases = [
            [$this->contributor([Permissions::RATE]), 2, false],
            [$this->contributor([Permissions::MODERATE]), 3, true],
            [User::findOrFail(1), 3, true],
            [$this->contributor([Permissions::CREATE]), null, false],
            [new Guest(), null, false],
        ];
        foreach ($cases as [$actor, $visibleCount, $moderator]) {
            $query = [
                'filter' => ['post' => $post],
                'include' => 'post',
                'fields' => ['posts' => 'number,communityNoteCount,visibleCommunityNoteCount'],
            ];
            $response = $this->api($actor)->withQueryParams($query)->get('/community-notes');
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $included = array_values(array_filter($body['included'], fn ($item) => $item['type'] === 'posts'));
            $this->assertCount(1, $included);
            $attributes = $included[0]['attributes'];
            if ($visibleCount === null) {
                $this->assertArrayNotHasKey('visibleCommunityNoteCount', $attributes);
            } else {
                $this->assertSame($visibleCount, $attributes['visibleCommunityNoteCount']);
                $this->assertCount($visibleCount, $body['data']);
            }
            if ($moderator) {
                $this->assertSame(3, $attributes['communityNoteCount']);
            } else {
                $this->assertArrayNotHasKey('communityNoteCount', $attributes);
            }

            // 普通帖子接口不暴露用于附注详情的计数，即便显式请求该字段。
            $response = $this->api($actor)->withQueryParams(['fields' => $query['fields']])->get('/posts/'.$post);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $this->assertArrayNotHasKey('visibleCommunityNoteCount', $body['data']['attributes']);
        }
    }

    public function test_feed_excludes_hidden_posts_and_notes_even_for_moderators(): void
    {
        $actor = $this->contributor([Permissions::MODERATE]);
        $visible = $this->note(['user_id' => $actor->id, 'status' => 'helpful']);
        $pending = $this->note(['user_id' => $actor->id]);
        $this->note(['is_hidden' => true, 'status' => 'helpful']);
        $hiddenPost = $this->post();
        $this->note(['post_id' => $hiddenPost, 'status' => 'helpful']);
        $this->db->table('posts')->where('id', $hiddenPost)->update(['hidden_at' => '2026-09-26 12:00:00']);
        foreach (['helpful', 'latest'] as $feed) {
            $response = $this->api($actor)->withQueryParams(['filter' => ['feed' => $feed]])->get('/community-notes');
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $this->assertSame([(string) ($feed === 'helpful' ? $visible : $pending)], array_column($body['data'], 'id'));
        }
    }

    public function test_latest_feed_uses_public_display_state_and_reincludes_posts_when_the_note_is_hidden(): void
    {
        $actor = $this->contributor([Permissions::RATE]);
        $post = $this->post();
        $public = $this->note(['post_id' => $post, 'status' => 'helpful']);
        $pending = $this->note(['post_id' => $post, 'created_at' => '2026-09-26 12:00:00']);
        $query = ['filter' => ['feed' => 'latest'], 'page' => ['limit' => 1]];
        $response = $this->api($actor)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['page']['total']);

        $this->db->table('ffans_community_notes_notes')->where('id', $public)->update(['is_hidden' => true]);
        $response = $this->api($actor)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $pending], array_column($body['data'], 'id'));
        $this->assertSame(1, $body['meta']['page']['total']);
    }

    public function test_crud_uses_services_and_rejects_protected_fields(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $input = ['data' => ['type' => 'community-notes', 'attributes' => [
            'reason' => 'missing_context', 'content' => str_repeat('测试正文', 10), 'sources' => ['https://example.test/source'],
        ], 'relationships' => ['post' => ['data' => ['type' => 'posts', 'id' => (string) $this->post()]]]]];
        $response = $this->api($actor)->withBody($input)->post('/community-notes');
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $note = json_decode((string) $response->getBody(), true)['data'];
        $url = '/community-notes/'.$note['id'];
        $this->assertTrue($note['attributes']['isMine']);
        $this->assertSame(0, $note['attributes']['ratingCount']);
        foreach (['canRate', 'canEdit', 'canDelete'] as $field) {
            $this->assertArrayNotHasKey($field, $note['attributes']);
        }
        $this->assertSame(200, $this->api($actor)->get($url)->getStatusCode());
        $this->assertSame(404, $this->api()->get($url)->getStatusCode());
        unset($input['data']['relationships']);
        $input['data']['id'] = $note['id'];
        $input['data']['attributes']['content'] = str_repeat('修改后的正文', 8);
        $response = $this->api($actor)->withBody($input)->patch($url);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame($input['data']['attributes']['content'], json_decode((string) $response->getBody(), true)['data']['attributes']['content']);
        $input['data']['attributes']['status'] = 'helpful';
        $this->assertSame(422, $this->api($actor)->withBody($input)->patch($url)->getStatusCode());
        $this->assertSame(403, $this->api($this->contributor([Permissions::RATE]))->delete($url)->getStatusCode());
        $this->assertSame(204, $this->api($actor)->delete($url)->getStatusCode());
        $this->assertSame(0, $this->db->table('ffans_community_notes_note_sources')->where('note_id', $note['id'])->count());
    }

    public function test_rating_upsert_returns_canonical_note_and_locks_content(): void
    {
        $author = $this->contributor([Permissions::CREATE, Permissions::RATE]);
        $rater = $this->contributor([Permissions::RATE]);
        $id = $this->note(['user_id' => $author->id]);
        $url = '/community-notes/'.$id;
        $input = ['value' => 'helpful', 'reasons' => ['clear']];
        $this->assertSame(403, $this->api($author)->withBody($input)->put($url.'/rating')->getStatusCode());
        $this->assertSame(404, $this->api()->withBody($input)->put($url.'/rating')->getStatusCode());
        $response = $this->api($rater)->withBody($input)->put($url.'/rating');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $attributes = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertSame(1, $attributes['ratingCount']);
        $this->assertSame($input, $attributes['myRating']);
        $input = ['value' => 'not_helpful', 'reasons' => ['incorrect']];
        $response = $this->api($rater)->withBody($input)->put($url.'/rating');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $attributes = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertSame(1, $attributes['ratingCount']);
        $this->assertEquals(0, $attributes['score']);
        $this->assertSame($input, $attributes['myRating']);
        $this->assertSame(422, $this->api($rater)->withBody(['value' => 'helpful', 'reasons' => ['incorrect']])->put($url.'/rating')->getStatusCode());
        $this->assertSame(403, $this->api($author)->delete($url)->getStatusCode());
        $this->assertSame(1, $this->db->table('ffans_community_notes_note_ratings')->where('note_id', $id)->count());
    }

    public function test_rating_response_refreshes_the_actual_public_note_relationship(): void
    {
        $post = $this->post();
        $id = $this->note(['post_id' => $post]);
        $actors = [];
        for ($i = 0; $i < 5; $i++) {
            $actors[] = $actor = $this->contributor([Permissions::RATE]);
            if ($i < 4) {
                $this->rating($id, $actor->id);
            }
        }
        foreach ([[4, 'helpful', 'clear', (string) $id], [4, 'not_helpful', 'incorrect', (string) $id], [0, 'not_helpful', 'incorrect', null]] as [$actor, $value, $reason, $publicId]) {
            $response = $this->api($actors[$actor])->withQueryParams(['include' => 'post.communityNote'])
                ->withBody(['value' => $value, 'reasons' => [$reason]])->put('/community-notes/'.$id.'/rating');
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $includedPost = array_values(array_filter($body['included'], fn ($row) => $row['type'] === 'posts'))[0];
            $this->assertSame($publicId, $includedPost['relationships']['communityNote']['data']['id'] ?? null);
            $this->assertSame($value, $body['data']['attributes']['myRating']['value']);
        }
    }

    public function test_serialization_does_not_leak_author_or_other_ratings_even_with_includes(): void
    {
        $rater = $this->contributor([Permissions::RATE]);
        $moderator = $this->contributor([Permissions::MODERATE]);
        $author = $this->contributor([Permissions::CREATE]);
        $id = $this->note(['status' => 'helpful', 'user_id' => $author->id]);
        $this->rating($id, $rater->id);
        $this->rating($id, $moderator->id);
        $url = '/community-notes/'.$id;
        foreach ([new Guest(), $author, $rater] as $actor) {
            $response = $this->api($actor)->withQueryParams(['include' => 'user,hiddenBy', 'fields' => [
                'community-notes' => 'user,hiddenBy,ratings,history,hiddenReason,myRating,isMine',
            ]])->get($url);
            $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
            $body = json_decode((string) $response->getBody(), true);
            $this->assertEmpty($body['included'] ?? []);
            $this->assertArrayNotHasKey('user', $body['data']['relationships'] ?? []);
            foreach (['ratings', 'history', 'hiddenReason'] as $field) {
                $this->assertArrayNotHasKey($field, $body['data']['attributes']);
            }
            $this->assertSame($actor->id === $author->id, $body['data']['attributes']['isMine']);
        }
        $response = $this->api($moderator)->withQueryParams(['include' => 'user'])->get($url);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame((string) $author->id, $body['data']['relationships']['user']['data']['id']);
        $this->assertCount(2, $body['data']['attributes']['ratings']);
        $this->assertArrayHasKey('history', $body['data']['attributes']);
        $this->db->table('users')->where('id', $author->id)->delete();
        $response = $this->api($moderator)->withQueryParams(['include' => 'user'])->get($url);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull(json_decode((string) $response->getBody(), true)['data']['relationships']['user']['data']);
    }

    public function test_moderation_endpoints_hide_and_restore_without_changing_score(): void
    {
        $moderator = $this->contributor([Permissions::MODERATE]);
        $rater = $this->contributor([Permissions::RATE]);
        $id = $this->note(['status' => 'helpful', 'score' => 1, 'rating_count' => 1]);
        $this->rating($id, $rater->id);
        $url = '/community-notes/'.$id;
        $this->assertSame(403, $this->api($rater)->withBody(['reason' => '违规'])->post($url.'/hide')->getStatusCode());
        $this->assertSame(422, $this->api($moderator)->post($url.'/hide')->getStatusCode());
        $response = $this->api($moderator)->withBody(['reason' => '违规来源'])->post($url.'/hide');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $attributes = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertTrue($attributes['isHidden']);
        $this->assertSame('helpful', $attributes['status']);
        $this->assertCount(1, $attributes['ratings']);
        $this->assertSame('违规来源', $attributes['history'][0]['reason']);
        $this->assertSame(404, $this->api()->get($url)->getStatusCode());
        $this->assertSame(404, $this->api($rater)->get($url)->getStatusCode());
        foreach ([$rater, $moderator] as $actor) {
            $response = $this->api($actor)->withQueryParams(['filter' => ['queue' => 'rating']])->get('/community-notes');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame([], json_decode((string) $response->getBody(), true)['data']);
        }
        $this->assertSame(404, $this->api($rater)->withBody(['value' => 'helpful', 'reasons' => ['clear']])->put($url.'/rating')->getStatusCode());
        $this->assertSame(404, $this->api($rater)->post($url.'/restore')->getStatusCode());
        $response = $this->api($moderator)->post($url.'/restore');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $attributes = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertFalse($attributes['isHidden']);
        $this->assertNull($attributes['hiddenReason']);
        $this->assertCount(2, $attributes['history']);
        $this->assertSame('违规来源', $attributes['history'][0]['reason']);
        $this->assertSame(200, $this->api()->get($url)->getStatusCode());
    }

    public function test_deleted_users_leave_readable_anonymous_notes_ratings_and_history(): void
    {
        $author = $this->contributor([Permissions::CREATE]);
        $rater = $this->contributor([Permissions::RATE]);
        $moderator = $this->contributor([Permissions::MODERATE]);
        $id = $this->note(['user_id' => $author->id, 'status' => 'helpful']);
        $rating = $this->rating($id, $rater->id);
        $url = '/community-notes/'.$id;
        $this->assertSame(200, $this->api($moderator)->withBody(['reason' => '测试隐藏'])->post($url.'/hide')->getStatusCode());
        foreach ([$author, $rater, $moderator] as $deleted) {
            $this->db->table('users')->where('id', $deleted->id)->delete();
        }
        $note = $this->db->table('ffans_community_notes_notes')->find($id);
        $this->assertNull($note->user_id);
        $this->assertNull($note->hidden_by_user_id);
        $this->assertNull($this->db->table('ffans_community_notes_note_ratings')->find($rating)->user_id);
        $this->assertNull($this->db->table('ffans_community_notes_note_history')->where('note_id', $id)->sole()->actor_user_id);
        $response = $this->api(User::findOrFail(1))->withQueryParams(['include' => 'user,hiddenBy'])->get($url);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $data = json_decode((string) $response->getBody(), true)['data'];
        $this->assertNull($data['relationships']['user']['data']);
        $this->assertNull($data['relationships']['hiddenBy']['data']);
        $this->assertCount(1, $data['attributes']['ratings']);
        $this->assertCount(1, $data['attributes']['history']);
        $this->assertSame(200, $this->api(User::findOrFail(1))->post($url.'/restore')->getStatusCode());
        foreach ([new Guest(), $this->contributor([Permissions::RATE])] as $actor) {
            $response = $this->api($actor)->get($url);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertFalse(json_decode((string) $response->getBody(), true)['data']['attributes']['isMine']);
            $this->assertSame(200, $this->api($actor)->get('/posts/'.$note->post_id)->getStatusCode());
        }
    }

    public function test_direct_api_permission_bypasses_and_invalid_payloads_are_rejected(): void
    {
        $member = User::findOrFail($this->user());
        $creator = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $input = ['data' => ['type' => 'community-notes', 'attributes' => [
            'reason' => 'missing_context', 'content' => str_repeat('有效正文', 10), 'sources' => ['https://example.test'],
        ], 'relationships' => ['post' => ['data' => ['type' => 'posts', 'id' => (string) $post]]]]];
        foreach ([new Guest(), $member] as $actor) {
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->withBody($input)->post('/community-notes')->getStatusCode());
        }
        foreach ([['reason' => 'bad'], ['content' => '短'], ['sources' => ['javascript:alert(1)']], ['sources' => []]] as $bad) {
            $invalid = $input;
            $invalid['data']['attributes'] = array_replace($input['data']['attributes'], $bad);
            $this->assertSame(422, $this->api($creator)->withBody($invalid)->post('/community-notes')->getStatusCode());
        }
        foreach ([['user_id' => $creator->id], ['hidden_at' => '2026-09-25 12:00:00'], ['is_private' => true], ['type' => 'discussionRenamed']] as $bad) {
            $this->db->table('posts')->where('id', $post)->update($bad);
            $this->assertSame(403, $this->api($creator)->withBody($input)->post('/community-notes')->getStatusCode());
            $this->db->table('posts')->where('id', $post)->update(['user_id' => null, 'hidden_at' => null, 'is_private' => false, 'type' => 'comment']);
        }
        $id = $this->note(['post_id' => $post, 'user_id' => $creator->id, 'status' => 'helpful']);
        $url = '/community-notes/'.$id;
        unset($input['data']['relationships']);
        foreach ([$member, new Guest()] as $actor) {
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->withBody($input)->patch($url)->getStatusCode());
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->delete($url)->getStatusCode());
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->withBody(['value' => 'helpful', 'reasons' => ['clear']])->put($url.'/rating')->getStatusCode());
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->withBody(['reason' => '违规'])->post($url.'/hide')->getStatusCode());
            $this->assertSame($actor->isGuest() ? 401 : 403, $this->api($actor)->post($url.'/restore')->getStatusCode());
        }
        $input['data']['id'] = [];
        $this->assertSame(422, $this->api($creator)->withBody($input)->patch($url)->getStatusCode());
    }

    public function test_valid_filters_pagination_bounds_and_invisible_discussion(): void
    {
        $rater = $this->contributor([Permissions::RATE]);
        $post = $this->post();
        $id = $this->note(['post_id' => $post]);
        $this->note();
        $query = ['filter' => ['post' => (string) $post, 'status' => 'needs_more_ratings'], 'page' => ['limit' => 1000]];
        $response = $this->api($rater)->withQueryParams($query)->get('/community-notes');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame([(string) $id], array_column($body['data'], 'id'));
        $this->assertSame(50, $body['meta']['page']['limit']);
        foreach ([['offset' => -1], ['limit' => 0], ['limit' => []], ['offset' => 1.2], 'bad'] as $page) {
            $this->assertSame(400, $this->api($rater)->withQueryParams(['page' => $page])->get('/community-notes')->getStatusCode());
        }
        $discussion = $this->db->table('posts')->where('id', $post)->value('discussion_id');
        $this->db->table('discussions')->where('id', $discussion)->update(['is_private' => true]);
        $this->assertSame(404, $this->api($rater)->withQueryParams(['include' => 'post,post.user'])->get('/community-notes/'.$id)->getStatusCode());
        $response = $this->api($rater)->withQueryParams($query)->get('/community-notes');
        $this->assertSame([], json_decode((string) $response->getBody(), true)['data']);
    }
}
