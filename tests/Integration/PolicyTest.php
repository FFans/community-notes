<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\User\Guest;
use Flarum\User\User;

class PolicyTest extends DomainTestCase
{
    public function test_guest_and_unprivileged_users_are_denied_every_ability(): void
    {
        $note = CommunityNote::findOrFail($this->note());
        foreach ([new Guest(), User::findOrFail($this->user())] as $actor) {
            foreach (['create', 'update', 'delete', 'rate', 'viewCandidate', 'moderate'] as $ability) {
                $this->assertFalse($actor->can($ability, $note), $ability);
            }
        }
    }

    public function test_create_requires_visible_comment_not_own_and_no_existing_note(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $draft = new CommunityNote();
        $draft->post_id = $this->post();
        $this->assertTrue($actor->can('create', $draft));
        $this->assertTrue(User::findOrFail(1)->can('create', $draft));
        foreach ([['user_id' => $actor->id], ['hidden_at' => '2026-09-25 12:00:00'], ['type' => 'discussionRenamed'], ['is_private' => true]] as $change) {
            $this->db->table('posts')->where('id', $draft->post_id)->update($change);
            $this->assertFalse($actor->can('create', $draft));
            $this->db->table('posts')->where('id', $draft->post_id)->update(['user_id' => null, 'hidden_at' => null, 'type' => 'comment', 'is_private' => false]);
        }
        $this->note(['post_id' => $draft->post_id, 'user_id' => $actor->id]);
        $this->assertFalse($actor->can('create', $draft));
    }

    public function test_author_edit_and_delete_require_unrated_note_but_moderator_can_delete(): void
    {
        $actor = $this->contributor([Permissions::CREATE]);
        $note = CommunityNote::findOrFail($this->note(['user_id' => $actor->id]));
        foreach (['update', 'delete'] as $ability) {
            $this->assertTrue($actor->can($ability, $note));
            $this->assertSame($ability === 'delete', User::findOrFail(1)->can($ability, $note));
            $note->is_hidden = true;
            $this->assertFalse($actor->can($ability, $note));
            $note->is_hidden = false;
            $note->rating_count = 1;
            $this->assertFalse($actor->can($ability, $note));
            $note->rating_count = 0;
        }
        $this->rating($note->id);
        $this->assertFalse($actor->can('update', $note));
        $this->assertFalse($actor->can('delete', $note));
    }

    public function test_rate_and_candidate_require_separate_permissions_and_hide_author_candidates(): void
    {
        $rater = $this->contributor([Permissions::RATE]);
        $moderator = $this->contributor([Permissions::MODERATE]);
        $note = CommunityNote::findOrFail($this->note());
        $this->assertTrue($rater->can('rate', $note));
        $this->assertTrue($rater->can('viewCandidate', $note));
        $this->assertFalse($rater->can('moderate', $note));
        $this->assertFalse($moderator->can('rate', $note));
        $this->assertTrue($moderator->can('viewCandidate', $note));
        $this->assertTrue($moderator->can('moderate', $note));
        $note->user_id = $rater->id;
        $this->assertFalse($rater->can('rate', $note));
        $this->assertFalse($rater->can('viewCandidate', $note));
        $note->user_id = null;
        $this->assertTrue($rater->can('rate', $note));
        $note->is_hidden = true;
        $this->assertFalse($rater->can('rate', $note));
        $this->assertFalse($rater->can('viewCandidate', $note));
        $this->assertTrue($moderator->can('viewCandidate', $note));
        $this->assertTrue($moderator->can('moderate', $note));
    }

    public function test_post_and_discussion_visibility_apply_to_all_abilities(): void
    {
        $actor = $this->contributor([Permissions::CREATE, Permissions::RATE, Permissions::MODERATE]);
        $note = CommunityNote::findOrFail($this->note(['user_id' => $actor->id]));
        foreach (['posts', 'discussions'] as $table) {
            $id = $table === 'posts' ? $note->post_id : $note->post->discussion_id;
            $this->db->table($table)->where('id', $id)->update(['is_private' => true]);
            foreach (['create', 'update', 'delete', 'rate', 'viewCandidate', 'moderate'] as $ability) {
                $this->assertFalse($actor->can($ability, $note), $table.' '.$ability);
            }
            $this->db->table($table)->where('id', $id)->update(['is_private' => false]);
        }
        $this->db->table('posts')->where('id', $note->post_id)->update(['hidden_at' => '2026-09-25 12:00:00']);
        foreach (['create', 'update', 'delete', 'rate'] as $ability) {
            $this->assertFalse($actor->can($ability, $note));
        }
    }
}
