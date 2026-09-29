<?php

namespace FFans\CommunityNotes\Enum;

enum CommunityNoteRatingReason: string
{
    case ImportantContext = 'important_context';
    case DirectlyAddressesClaim = 'directly_addresses_claim';
    case Clear = 'clear';
    case ReliableSources = 'reliable_sources';
    case Neutral = 'neutral';
    case UniqueInformation = 'unique_information';
    case PartiallyHelpful = 'partially_helpful';
    case NeedsMoreContext = 'needs_more_context';
    case MixedSourceQuality = 'mixed_source_quality';
    case WordingNeedsImprovement = 'wording_needs_improvement';
    case Incorrect = 'incorrect';
    case UnreliableOrMissingSources = 'unreliable_or_missing_sources';
    case NotNeeded = 'not_needed';
    case Irrelevant = 'irrelevant';
    case MissingImportantContext = 'missing_important_context';
    case OpinionOrSpeculation = 'opinion_or_speculation';
    case HostileOrInflammatory = 'hostile_or_inflammatory';
    case Unclear = 'unclear';
    case Outdated = 'outdated';
}
