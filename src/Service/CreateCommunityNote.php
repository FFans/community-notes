<?php

namespace FFans\CommunityNotes\Service;

use Carbon\Carbon;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteSource;
use FFans\CommunityNotes\Validator\CommunityNoteValidator;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class CreateCommunityNote
{
    public function __construct(private ConnectionInterface $db, private CommunityNoteValidator $validator)
    {
    }

    public function handle(User $actor, int $postId, array $input): CommunityNote
    {
        return $this->db->transaction(function () use ($actor, $postId, $input) {
            $data = $this->validator->validate($input);
            // 创建时尚无附注行可锁，锁原帖使重复创建检查与插入串行化。
            Post::query()->lockForUpdate()->findOrFail($postId);
            $note = new CommunityNote();
            $note->post_id = $postId;
            $actor->assertCan('create', $note);
            $note->user_id = $actor->id;
            $note->reason = $data['reason'];
            $note->content = $data['content'];
            $note->status = CommunityNoteStatus::NeedsMoreRatings;
            $note->status_changed_at = Carbon::now();
            $note->rating_count = 0;
            $note->helpful_count = 0;
            $note->somewhat_helpful_count = 0;
            $note->not_helpful_count = 0;
            $note->score = null;
            $note->is_hidden = false;
            $note->save();

            foreach ($data['sources'] as $position => $url) {
                $source = new CommunityNoteSource();
                $source->url = $url;
                $source->position = $position;
                $note->sources()->save($source);
            }

            return $note->load('sources');
        });
    }
}
