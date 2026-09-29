<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Service\CreateCommunityNote;
use FFans\CommunityNotes\Service\UpdateCommunityNote;
use FFans\CommunityNotes\Service\DeleteCommunityNote;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\Guest;
use Flarum\User\User;

class NoteCrudTest extends DomainTestCase
{
    public function test_invalid_create_input_never_writes_notes_or_sources(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $sourceCount = $this->db->table('ffans_community_notes_note_sources')->count();
        foreach ([['reason' => 'invalid'], ['content' => str_repeat('文', 29)], ['content' => str_repeat('文', 1001)], ['sources' => []], ['sources' => ['javascript:alert(1)']], ['sources' => array_fill(0, 6, 'https://example.test')]] as $invalid) {
            try {
                self::$container->make(CreateCommunityNote::class)->handle($actor, $post, $this->input($invalid));
                $this->fail('应拒绝无效创建');
            } catch (\Flarum\Foundation\ValidationException) {
                $this->assertSame(0, CommunityNote::query()->where('post_id', $post)->count());
                $this->assertSame($sourceCount, $this->db->table('ffans_community_notes_note_sources')->count());
            }
        }
    }

    public function test_services_recheck_hidden_and_invisible_state_from_database(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $note = self::$container->make(CreateCommunityNote::class)->handle($actor, $this->post(), $this->input());
        foreach (['hidden_note', 'hidden_post', 'private_post', 'rating_cache'] as $state) {
            $this->db->table('ffans_community_notes_notes')->where('id', $note->id)->update(['is_hidden' => $state === 'hidden_note', 'rating_count' => $state === 'rating_cache' ? 1 : 0]);
            $this->db->table('posts')->where('id', $note->post_id)->update(['hidden_at' => $state === 'hidden_post' ? '2026-09-25 12:00:00' : null, 'is_private' => $state === 'private_post']);
            foreach ([UpdateCommunityNote::class, DeleteCommunityNote::class] as $service) {
                try {
                    self::$container->make($service)->handle($actor, $note->id, $this->input());
                    $this->fail('应重新检查当前状态');
                } catch (PermissionDeniedException) {
                    $this->assertSame($note->content, $note->fresh()->content);
                    $this->assertSame(2, $note->sources()->count());
                }
            }
        }
    }

    public function test_update_cannot_be_used_by_other_authors_or_admins(): void
    {
        $owner = $this->contributor([Permissions::CREATE]);
        $note = self::$container->make(CreateCommunityNote::class)->handle($owner, $this->post(), $this->input());
        foreach ([new Guest(), User::findOrFail(1), $this->contributor([Permissions::CREATE])] as $actor) {
            try {
                self::$container->make(UpdateCommunityNote::class)->handle($actor, $note->id, $this->input(['reason' => 'other']));
                $this->fail('只有作者可编辑');
            } catch (PermissionDeniedException) {
                $this->assertSame('missing_context', $note->fresh()->reason->value);
            }
        }
    }

    public function test_source_failure_rolls_back_create_and_update(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $create = self::$container->make(CreateCommunityNote::class);
        $post = $this->post();
        $note = $create->handle($actor, $post, $this->input());
        $oldSources = $note->sources->toArray();
        // 第一条来源写入成功后，第二条失败，事务必须还原全部数据。
        foreach ([CreateCommunityNote::class, UpdateCommunityNote::class] as $service) {
            $stopRejecting = $this->failOnDatabaseWrite('insert into', 'ffans_community_notes_note_sources', '来源写入失败', after: 1);
            try {
                self::$container->make($service)->handle($actor, $service === CreateCommunityNote::class ? $this->post() : $note->id, $this->input(['content' => str_repeat('修改的正文', 10)]));
                $this->fail('应模拟来源写入失败');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('来源写入失败', $e->getMessage());
                $this->assertSame(1, CommunityNote::query()->where('user_id', $actor->id)->count());
                $this->assertSame($note->content, $note->fresh()->content);
                $this->assertSame($oldSources, $note->fresh()->sources->toArray());
            } finally {
                $stopRejecting();
            }
        }
    }

    public function test_missing_post_cannot_be_used_for_creation(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $this->db->table('posts')->where('id', $post)->delete();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        self::$container->make(CreateCommunityNote::class)->handle($actor, $post, $this->input());
    }

    public function test_author_delete_cascades_sources_and_rejects_rated_note(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $note = self::$container->make(CreateCommunityNote::class)->handle($actor, $this->post(), $this->input());
        $delete = self::$container->make(DeleteCommunityNote::class);
        foreach ([new Guest(), $this->contributor([Permissions::CREATE])] as $other) {
            try {
                $delete->handle($other, $note->id);
                $this->fail('只有作者可删除');
            } catch (PermissionDeniedException) {
                $this->assertNotNull($note->fresh());
            }
        }
        $delete->handle($actor, $note->id);
        $this->assertNull($note->fresh());
        $this->assertSame(0, $this->db->table('ffans_community_notes_note_sources')->where('note_id', $note->id)->count());
        $replacement = self::$container->make(CreateCommunityNote::class)->handle($actor, $note->post_id, $this->input());
        $this->rating($replacement->id);
        try {
            $delete->handle($actor, $replacement->id);
            $this->fail('已评价附注不可删除');
        } catch (PermissionDeniedException) {
            $this->assertNotNull($replacement->fresh());
            $this->assertSame(1, $replacement->ratings()->count());
            $this->assertSame(2, $replacement->sources()->count());
        }
    }

    public function test_update_replaces_sources_and_locks_after_first_rating(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $note = self::$container->make(CreateCommunityNote::class)->handle($actor, $this->post(), $this->input());
        $oldIds = $note->sources->pluck('id')->all();
        $update = self::$container->make(UpdateCommunityNote::class);
        $changed = $this->input(['reason' => 'other', 'content' => str_repeat('新背景。', 10), 'sources' => ['https://example.test/new']]);
        $note = $update->handle($actor, $note->id, $changed);
        $this->assertSame($changed['content'], $note->content);
        $this->assertSame('other', $note->reason->value);
        $this->assertSame($changed['sources'], $note->sources->pluck('url')->all());
        $this->assertSame(0, $this->db->table('ffans_community_notes_note_sources')->whereIn('id', $oldIds)->count());
        $this->rating($note->id);
        try {
            $update->handle($actor, $note->id, $this->input());
            $this->fail('收到评价后应锁定');
        } catch (PermissionDeniedException) {
            $this->assertSame($changed['content'], $note->fresh()->content);
            $this->assertSame($changed['sources'], $note->fresh()->sources->pluck('url')->all());
        }
    }

    public function test_update_revalidates_all_fields_without_partial_mutation(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $note = self::$container->make(CreateCommunityNote::class)->handle($actor, $this->post(), $this->input());
        foreach ([[], ['reason' => 'wrong'], ['content' => '短'], ['sources' => []]] as $input) {
            try {
                self::$container->make(UpdateCommunityNote::class)->handle($actor, $note->id, $input);
                $this->fail('应重新验证全部输入');
            } catch (\Flarum\Foundation\ValidationException) {
                $this->assertSame($note->content, $note->fresh()->content);
                $this->assertSame($note->sources->pluck('url')->all(), $note->fresh()->sources->pluck('url')->all());
            }
        }
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['reason' => 'missing_context', 'content' => str_repeat('补充背景。', 10), 'sources' => ['https://example.test/a', 'http://example.test/b']], $overrides);
    }

    public function test_create_saves_note_and_ordered_sources_and_ignores_protected_attributes(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $note = self::$container->make(CreateCommunityNote::class)->handle($actor, $post, $this->input(['status' => 'helpful', 'user_id' => 1, 'rating_count' => 10]));
        $this->assertSame($post, $note->post_id);
        $this->assertSame($actor->id, $note->user_id);
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertSame(0, $note->rating_count);
        $this->assertNull($note->score);
        $this->assertFalse($note->is_hidden);
        $this->assertSame($this->input()['sources'], $note->sources->pluck('url')->all());
        $this->assertSame([0, 1], $note->sources->pluck('position')->all());
        $this->assertSame(0, $note->history()->count());
        try {
            self::$container->make(CreateCommunityNote::class)->handle($actor, $post, $this->input());
            $this->fail('不能重复创建');
        } catch (PermissionDeniedException) {
            $this->assertSame(1, CommunityNote::query()->where('post_id', $post)->count());
        }
    }

    public function test_create_denials_leave_no_records(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $post = $this->post();
        $service = self::$container->make(CreateCommunityNote::class);
        foreach ([new Guest(), User::findOrFail($this->user())] as $denied) {
            try {
                $service->handle($denied, $post, $this->input());
                $this->fail('应拒绝无权限创建');
            } catch (PermissionDeniedException) {
                $this->assertSame(0, CommunityNote::query()->where('post_id', $post)->count());
            }
        }
        foreach ([['user_id' => $actor->id], ['type' => 'discussionRenamed'], ['hidden_at' => '2026-09-25 12:00:00'], ['is_private' => true]] as $change) {
            $this->db->table('posts')->where('id', $post)->update($change);
            try {
                $service->handle($actor, $post, $this->input());
                $this->fail('应拒绝目标帖子');
            } catch (PermissionDeniedException) {
                $this->assertSame(0, CommunityNote::query()->where('post_id', $post)->count());
            }
            $this->db->table('posts')->where('id', $post)->update(['user_id' => null, 'type' => 'comment', 'hidden_at' => null, 'is_private' => false]);
        }
    }
}
