<?php

namespace FFans\CommunityNotes\Service;

use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Model\CommunityNoteSource;
use FFans\CommunityNotes\Validator\CommunityNoteValidator;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class UpdateCommunityNote
{
    public function __construct(private ConnectionInterface $db, private CommunityNoteValidator $validator)
    {
    }

    public function handle(User $actor, int $noteId, array $input): CommunityNote
    {
        return $this->db->transaction(function () use ($actor, $noteId, $input) {
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $actor->assertCan('update', $note);
            $data = $this->validator->validate($input);
            $note->reason = $data['reason'];
            $note->content = $data['content'];
            $note->save();
            $note->sources()->delete();
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
