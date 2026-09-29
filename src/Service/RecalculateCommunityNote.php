<?php

namespace FFans\CommunityNotes\Service;

use Carbon\Carbon;
use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteHistory;
use FFans\CommunityNotes\Scoring\NoteScorer;
use Illuminate\Database\ConnectionInterface;

class RecalculateCommunityNote
{
    public function __construct(private ConnectionInterface $db, private NoteScorer $scorer)
    {
    }

    public function handle(int $noteId): CommunityNote
    {
        // 独立重算和评价内嵌重算都持有同一附注锁，外层事务提交前不会释放。
        return $this->db->transaction(function () use ($noteId) {
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $result = $this->scorer->score($note);
            $previous = $note->status;
            $note->rating_count = $result->ratingCount;
            $note->helpful_count = $result->helpfulCount;
            $note->somewhat_helpful_count = $result->somewhatHelpfulCount;
            $note->not_helpful_count = $result->notHelpfulCount;
            $note->score = $result->score;
            $note->status = $result->status;

            if ($previous !== $result->status) {
                $note->status_changed_at = Carbon::now();
                $history = new CommunityNoteHistory();
                $history->note_id = $note->id;
                $history->event = CommunityNoteHistoryEvent::StatusChanged;
                $history->from_status = $previous;
                $history->to_status = $result->status;
                $history->score = $result->score;
                $history->rating_count = $result->ratingCount;
                $history->created_at = $note->status_changed_at;
                $history->save();
            }

            $note->save();

            return $note;
        });
    }
}
