<?php

namespace FFans\CommunityNotes\Service;

use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteRating;
use FFans\CommunityNotes\Model\CommunityNoteRatingReason;
use FFans\CommunityNotes\Validator\CommunityNoteRatingValidator;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class RateCommunityNote
{
    public function __construct(
        private ConnectionInterface          $db,
        private CommunityNoteRatingValidator $validator,
        private RecalculateCommunityNote     $recalculate,
    )
    {
    }

    public function handle(User $actor, int $noteId, array $input): CommunityNote
    {
        return $this->db->transaction(function () use ($actor, $noteId, $input) {
            // 与编辑、删除和重算共用同一行锁，授权也必须发生在取得锁之后。
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $actor->assertCan('rate', $note);
            $data = $this->validator->validate($input);
            $rating = $note->ratings()->where('user_id', $actor->id)->first() ?? new CommunityNoteRating();
            $rating->note_id = $note->id;
            $rating->user_id = $actor->id;
            $rating->value = $data['value'];
            $rating->save();
            $rating->reasons()->delete();
            foreach ($data['reasons'] as $value) {
                $reason = new CommunityNoteRatingReason();
                $reason->reason = $value;
                $rating->reasons()->save($reason);
            }

            return $this->recalculate->handle($noteId);
        });
    }
}
