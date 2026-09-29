<?php

namespace FFans\CommunityNotes\Tests\Integration;

use Carbon\CarbonInterface;
use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Enum\CommunityNoteRatingReason as RatingReason;
use FFans\CommunityNotes\Enum\CommunityNoteRatingValue;
use FFans\CommunityNotes\Enum\CommunityNoteReason;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteHistory;
use FFans\CommunityNotes\Model\CommunityNoteRating;
use FFans\CommunityNotes\Model\CommunityNoteRatingReason;
use FFans\CommunityNotes\Model\CommunityNoteSource;
use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Flarum\Post\CommentPost;

class ModelsTest extends IntegrationTestCase
{
    public function test_complete_model_graph_casts_order_and_nullable_relationships(): void
    {
        $user = $this->user();
        $note = new CommunityNote();
        $note->post_id = $this->post();
        $note->user_id = $user;
        $note->hidden_by_user_id = $user;
        $note->reason = CommunityNoteReason::MissingContext;
        $note->content = '社区附注测试正文。';
        $note->status_changed_at = '2026-09-25 12:00:00';
        $note->save();
        $note->refresh();

        $sourceIds = [];
        foreach ([2, 1, 1] as $position) {
            $source = new CommunityNoteSource();
            $source->url = 'https://example.test/'.$position;
            $source->position = $position;
            $note->sources()->save($source);
            $sourceIds[] = $source->id;
        }
        $rating = new CommunityNoteRating();
        $rating->user_id = $user;
        $rating->value = CommunityNoteRatingValue::Helpful;
        $note->ratings()->save($rating);
        $reason = new CommunityNoteRatingReason();
        $reason->reason = RatingReason::Clear;
        $rating->reasons()->save($reason);
        $history = new CommunityNoteHistory();
        $history->event = CommunityNoteHistoryEvent::Hidden;
        $history->actor_user_id = $user;
        $history->rating_count = 0;
        $history->score = 0.5;
        $note->history()->save($history);

        $note = CommunityNote::with(['post.communityNotes', 'user', 'hiddenBy', 'sources.note', 'ratings.reasons.rating', 'ratings.user', 'history.actor'])->findOrFail($note->id);
        $this->assertInstanceOf(CommentPost::class, $note->post);
        $this->assertSame($note->id, $note->post->communityNotes->sole()->id);
        $this->assertSame($user, $note->user->id);
        $this->assertSame($user, $note->hiddenBy->id);
        $this->assertSame([$sourceIds[1], $sourceIds[2], $sourceIds[0]], $note->sources->modelKeys());
        $this->assertSame($note->id, $note->sources->first()->note->id);
        $this->assertSame($note->id, $note->ratings->sole()->note->id);
        $this->assertSame($rating->id, $note->ratings->sole()->reasons->sole()->rating->id);
        $this->assertSame($note->id, $note->history->sole()->note->id);
        $this->assertSame($user, $note->history->sole()->actor->id);
        $this->assertSame(false, $note->is_hidden);
        $this->assertSame(CommunityNoteReason::MissingContext, $note->reason);
        $this->assertSame(CommunityNoteStatus::NeedsMoreRatings, $note->status);
        $this->assertSame(CommunityNoteRatingValue::Helpful, $note->ratings->sole()->value);
        $this->assertSame(RatingReason::Clear, $note->ratings->sole()->reasons->sole()->reason);
        $this->assertSame(CommunityNoteHistoryEvent::Hidden, $note->history->sole()->event);
        $this->assertNull($note->history->sole()->from_status);
        $this->assertSame(0, $note->rating_count);
        $this->assertNull($note->score);
        $this->assertSame(0.5, $note->history->sole()->score);
        foreach ([$note->created_at, $note->updated_at, $note->status_changed_at, $note->sources->first()->created_at, $rating->created_at, $rating->updated_at, $history->created_at] as $date) {
            $this->assertInstanceOf(CarbonInterface::class, $date);
        }

        $this->db->table('users')->where('id', $user)->delete();
        $note->refresh();
        $this->assertNull($note->user);
        $this->assertNull($note->hiddenBy);
        $this->assertNull($note->ratings->sole()->user);
        $this->assertNull($note->history->sole()->actor);
    }
}
