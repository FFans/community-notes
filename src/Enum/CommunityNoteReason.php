<?php

namespace FFans\CommunityNotes\Enum;

enum CommunityNoteReason: string
{
    case MissingContext = 'missing_context';
    case OutdatedInformation = 'outdated_information';
    case FactualError = 'factual_error';
    case Misleading = 'misleading';
    case MediaContext = 'media_context';
    case Other = 'other';
}
