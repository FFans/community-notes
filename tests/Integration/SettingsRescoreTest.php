<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Service\RecalculateCommunityNote;
use FFans\CommunityNotes\Service\RescoreAllCommunityNotes;
use FFans\CommunityNotes\Settings\ScoringSettings;
use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Flarum\Api\Client;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;

class SettingsRescoreTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        foreach ([ScoringSettings::MIN_RATINGS, ScoringSettings::HELPFUL_THRESHOLD, ScoringSettings::NOT_HELPFUL_THRESHOLD] as $key) {
            $settings->delete($key);
        }
        parent::tearDown();
    }

    public function test_threshold_save_changes_public_relationship_and_preserves_source_data(): void
    {
        $id = $this->note();
        for ($i = 0; $i < 5; $i++) {
            $rating = $this->rating($id);
        }
        $this->db->table('ffans_community_notes_note_ratings')->where('id', $rating)->update(['value' => 'not_helpful']);
        $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'incorrect']);
        $this->db->table('ffans_community_notes_note_sources')->insert(['note_id' => $id, 'url' => 'https://example.test', 'position' => 0, 'created_at' => '2026-09-25 12:00:00']);
        $note = self::$container->make(RecalculateCommunityNote::class)->handle($id);
        $this->assertSame(Status::Helpful, $note->status);
        $snapshot = [$note->content, $note->ratings()->with('reasons')->get()->toArray(), $note->sources()->get()->toArray()];
        $public = self::$container->make(Client::class)->withActor(new Guest());
        $read = fn () => json_decode((string) $public->get('/posts/'.$note->post_id)->getBody(), true)['data']['relationships']['communityNote']['data'];
        $this->assertSame((string) $id, $read()['id']);
        $admin = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        $response = $admin->withBody([ScoringSettings::HELPFUL_THRESHOLD => 90])->post('/settings');
        $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        $note->refresh();
        $this->assertSame(Status::NeedsMoreRatings, $note->status);
        $this->assertNull($read());
        $this->assertSame($snapshot, [$note->content, $note->ratings()->with('reasons')->get()->toArray(), $note->sources()->get()->toArray()]);
        $history = $note->history()->reorder('id', 'desc')->first();
        $this->assertSame(Status::Helpful, $history->from_status);
        $this->assertSame(Status::NeedsMoreRatings, $history->to_status);
        $this->assertSame(0.8, $history->score);
        $this->assertSame(5, $history->rating_count);
        $this->assertNull($history->actor_user_id);
        // 所有新值落库后只执行一轮重算。
        $this->assertSame(204, $admin->withBody([
            ScoringSettings::HELPFUL_THRESHOLD => 80, ScoringSettings::MIN_RATINGS => 6,
        ])->post('/settings')->getStatusCode());
        $this->assertSame(Status::NeedsMoreRatings, $note->fresh()->status);
        $this->assertSame(2, $note->history()->count());
        $this->assertSame(204, $admin->withBody([ScoringSettings::MIN_RATINGS => 5])->post('/settings')->getStatusCode());
        $this->assertSame((string) $id, $read()['id']);
        $this->assertSame(204, $admin->withBody([
            ScoringSettings::HELPFUL_THRESHOLD => 100, ScoringSettings::NOT_HELPFUL_THRESHOLD => 85,
        ])->post('/settings')->getStatusCode());
        $this->assertSame(Status::NotHelpful, $note->fresh()->status);
    }

    public function test_no_change_unrelated_and_invalid_saves_do_not_rescore(): void
    {
        $id = $this->note(['rating_count' => 99]);
        $admin = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        foreach ([['forum_title' => '测试论坛'], [ScoringSettings::HELPFUL_THRESHOLD => '80'], [ScoringSettings::MIN_RATINGS => '5']] as $body) {
            $this->assertSame(204, $admin->withBody($body)->post('/settings')->getStatusCode());
            $this->assertSame(99, CommunityNote::findOrFail($id)->rating_count);
        }
        $this->assertSame(422, $admin->withBody([ScoringSettings::HELPFUL_THRESHOLD => 0])->post('/settings')->getStatusCode());
        $this->assertSame(99, CommunityNote::findOrFail($id)->rating_count);
    }

    public function test_all_batches_are_recalculated_without_touching_hidden_notes_or_duplicate_history(): void
    {
        $post = $this->post();
        for ($i = 0; $i < 105; $i++) {
            $this->note(['post_id' => $post, 'status' => 'helpful', 'rating_count' => 99]);
        }
        $hidden = $this->note(['is_hidden' => true, 'status' => 'helpful', 'rating_count' => 99]);
        self::$container->make(RescoreAllCommunityNotes::class)->handle();
        $this->assertSame(105, CommunityNote::where('status', Status::NeedsMoreRatings)->where('rating_count', 0)->count());
        $this->assertSame(99, CommunityNote::findOrFail($hidden)->rating_count);
        $this->assertSame(105, $this->db->table('ffans_community_notes_note_history')->count());
        self::$container->make(RescoreAllCommunityNotes::class)->handle();
        $this->assertSame(105, $this->db->table('ffans_community_notes_note_history')->count());
    }
}
