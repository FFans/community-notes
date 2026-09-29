<?php

namespace FFans\CommunityNotes\Service;

use Carbon\Carbon;
use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteHistory;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HideCommunityNote
{
    public function __construct(private ConnectionInterface $db, private TranslatorInterface $translator)
    {
    }

    public function handle(User $actor, int $noteId, array $input): CommunityNote
    {
        return $this->db->transaction(function () use ($actor, $noteId, $input) {
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $actor->assertCan('moderate', $note);
            $reason = $input['reason'] ?? null;
            if (!is_string($reason) || !mb_check_encoding($reason, 'UTF-8')
                || ($reason = preg_replace('/^\s+|\s+$/u', '', $reason)) === '') {
                throw new ValidationException(['reason' => $this->translator->trans('ffans-community-notes.validation.hide_reason_required')]);
            }
            if ($note->is_hidden) {
                return $note;
            }
            $note->is_hidden = true;
            $note->hidden_at = Carbon::now();
            $note->hidden_by_user_id = $actor->id;
            $note->hidden_reason = $reason;
            $note->save();

            $history = new CommunityNoteHistory();
            $history->event = CommunityNoteHistoryEvent::Hidden;
            $history->actor_user_id = $actor->id;
            $history->from_status = $note->status;
            $history->to_status = $note->status;
            $history->score = $note->score;
            $history->rating_count = $note->rating_count;
            $history->reason = $reason;
            $note->history()->save($history);

            return $note;
        });
    }
}
