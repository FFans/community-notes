<?php

namespace FFans\CommunityNotes\Service;

use FFans\CommunityNotes\Model\CommunityNote;
use Illuminate\Database\ConnectionInterface;

class RescoreAllCommunityNotes
{
    public function __construct(private ConnectionInterface $db, private RecalculateCommunityNote $recalculate)
    {
    }

    public function handle(): void
    {
        CommunityNote::query()->where('is_hidden', false)->select('id')->chunkById(100, function ($notes) {
            foreach ($notes as $candidate) {
                $this->db->transaction(function () use ($candidate) {
                    // 重新检查批次读取后发生的删除或隐藏，与评价共用同一行锁。
                    $note = CommunityNote::query()->lockForUpdate()->find($candidate->id);
                    if ($note && !$note->is_hidden) {
                        $this->recalculate->handle($note->id);
                    }
                });
            }
        });
    }
}
