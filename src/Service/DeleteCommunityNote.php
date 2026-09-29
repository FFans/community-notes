<?php

namespace FFans\CommunityNotes\Service;

use FFans\CommunityNotes\Model\CommunityNote;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class DeleteCommunityNote
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function handle(User $actor, int $noteId): void
    {
        $this->db->transaction(function () use ($actor, $noteId) {
            $note = CommunityNote::query()->lockForUpdate()->findOrFail($noteId);
            $actor->assertCan('delete', $note);
            $note->delete();
        });
    }
}
