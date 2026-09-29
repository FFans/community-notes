<?php

namespace FFans\CommunityNotes\Enum;

enum CommunityNoteRatingValue: string
{
    case Helpful = 'helpful';
    case SomewhatHelpful = 'somewhat_helpful';
    case NotHelpful = 'not_helpful';

    public function numericValue(): float
    {
        return match ($this) {
            self::Helpful => 1.0,
            self::SomewhatHelpful => 0.5,
            self::NotHelpful => 0.0,
        };
    }

    /** @return list<CommunityNoteRatingReason> */
    public function allowedReasons(): array
    {
        return match ($this) {
            self::Helpful => [
                CommunityNoteRatingReason::ImportantContext,
                CommunityNoteRatingReason::DirectlyAddressesClaim,
                CommunityNoteRatingReason::Clear,
                CommunityNoteRatingReason::ReliableSources,
                CommunityNoteRatingReason::Neutral,
                CommunityNoteRatingReason::UniqueInformation,
            ],
            self::SomewhatHelpful => [
                CommunityNoteRatingReason::PartiallyHelpful,
                CommunityNoteRatingReason::NeedsMoreContext,
                CommunityNoteRatingReason::MixedSourceQuality,
                CommunityNoteRatingReason::WordingNeedsImprovement,
            ],
            self::NotHelpful => [
                CommunityNoteRatingReason::Incorrect,
                CommunityNoteRatingReason::UnreliableOrMissingSources,
                CommunityNoteRatingReason::NotNeeded,
                CommunityNoteRatingReason::Irrelevant,
                CommunityNoteRatingReason::MissingImportantContext,
                CommunityNoteRatingReason::OpinionOrSpeculation,
                CommunityNoteRatingReason::HostileOrInflammatory,
                CommunityNoteRatingReason::Unclear,
                CommunityNoteRatingReason::Outdated,
            ],
        };
    }
}
