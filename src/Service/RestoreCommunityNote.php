<?php

namespace FFans\CommunityNotes\Service;

use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteHistory;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class RestoreCommunityNote
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function handle(User $actor, int $noteId): CommunityNote
    {
        return $this->db->transaction(function () use ($actor, $noteId) {
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $actor->assertCan('moderate', $note);
            if (!$note->is_hidden) {
                return $note;
            }
            $note->is_hidden = false;
            $note->hidden_at = null;
            $note->hidden_by_user_id = null;
            $note->hidden_reason = null;
            $note->save();

            $history = new CommunityNoteHistory();
            $history->event = CommunityNoteHistoryEvent::Restored;
            $history->actor_user_id = $actor->id;
            $history->from_status = $note->status;
            $history->to_status = $note->status;
            $history->score = $note->score;
            $history->rating_count = $note->rating_count;
            $note->history()->save($history);

            return $note;
        });
    }
}
