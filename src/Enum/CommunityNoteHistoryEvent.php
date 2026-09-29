<?php

namespace FFans\CommunityNotes\Enum;

enum CommunityNoteHistoryEvent: string
{
    case StatusChanged = 'status_changed';
    case Hidden = 'hidden';
    case Restored = 'restored';
}
