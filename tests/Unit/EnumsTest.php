<?php

namespace FFans\CommunityNotes\Tests\Unit;

use FFans\CommunityNotes\Enum\CommunityNoteHistoryEvent;
use FFans\CommunityNotes\Enum\CommunityNoteRatingReason;
use FFans\CommunityNotes\Enum\CommunityNoteRatingValue;
use FFans\CommunityNotes\Enum\CommunityNoteReason;
use FFans\CommunityNotes\Enum\CommunityNoteStatus;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_domain_values_match_requirements(): void
    {
        $this->assertSame(['needs_more_ratings', 'helpful', 'not_helpful'], array_column(CommunityNoteStatus::cases(), 'value'));
        $this->assertSame(['missing_context', 'outdated_information', 'factual_error', 'misleading', 'media_context', 'other'], array_column(CommunityNoteReason::cases(), 'value'));
        $this->assertSame(['status_changed', 'hidden', 'restored'], array_column(CommunityNoteHistoryEvent::cases(), 'value'));
        $this->assertNull(CommunityNoteStatus::tryFrom('deleted'));
        $this->assertNull(CommunityNoteRatingValue::tryFrom('invalid'));
    }

    public function test_rating_weights_and_allowed_reason_sets(): void
    {
        $this->assertSame(1.0, CommunityNoteRatingValue::Helpful->numericValue());
        $this->assertSame(0.5, CommunityNoteRatingValue::SomewhatHelpful->numericValue());
        $this->assertSame(0.0, CommunityNoteRatingValue::NotHelpful->numericValue());
        $expected = [
            'helpful' => ['important_context', 'directly_addresses_claim', 'clear', 'reliable_sources', 'neutral', 'unique_information'],
            'somewhat_helpful' => ['partially_helpful', 'needs_more_context', 'mixed_source_quality', 'wording_needs_improvement'],
            'not_helpful' => ['incorrect', 'unreliable_or_missing_sources', 'not_needed', 'irrelevant', 'missing_important_context', 'opinion_or_speculation', 'hostile_or_inflammatory', 'unclear', 'outdated'],
        ];
        $all = [];
        foreach (CommunityNoteRatingValue::cases() as $value) {
            $reasons = array_column($value->allowedReasons(), 'value');
            $this->assertSame($expected[$value->value], $reasons);
            $all = array_merge($all, $reasons);
        }
        $this->assertCount(count($all), array_unique($all));
        $this->assertSame(array_column(CommunityNoteRatingReason::cases(), 'value'), $all);
    }
}
