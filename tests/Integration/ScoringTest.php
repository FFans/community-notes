<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Scoring\SimpleNoteScorer;
use FFans\CommunityNotes\Settings\ScoringSettings;
use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class ScoringTest extends IntegrationTestCase
{
    #[DataProvider('scores')]
    public function test_scores_from_real_ratings(int $yes, int $somewhat, int $no, ?float $score, Status $status, int $minimum = 5, int $high = 80, int $low = 20): void
    {
        $note = CommunityNote::findOrFail($this->note(['rating_count' => 999, 'score' => 1]));
        $note->load('ratings');
        foreach (['helpful' => $yes, 'somewhat_helpful' => $somewhat, 'not_helpful' => $no] as $value => $count) {
            for ($i = 0; $i < $count; $i++) {
                $id = $this->rating($note->id);
                $this->db->table('ffans_community_notes_note_ratings')->where('id', $id)->update(['value' => $value]);
            }
        }
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnMap([
            [ScoringSettings::MIN_RATINGS, null, $minimum],
            [ScoringSettings::HELPFUL_THRESHOLD, null, $high],
            [ScoringSettings::NOT_HELPFUL_THRESHOLD, null, $low],
        ]);
        $result = (new SimpleNoteScorer($settings))->score($note);
        $this->assertSame($yes + $somewhat + $no, $result->ratingCount);
        $this->assertSame([$yes, $somewhat, $no], [$result->helpfulCount, $result->somewhatHelpfulCount, $result->notHelpfulCount]);
        $this->assertSame($score, $result->score);
        $this->assertSame($status, $result->status);
        $this->assertSame(999, $note->fresh()->rating_count);
    }

    public static function scores(): iterable
    {
        yield '零评价' => [0, 0, 0, null, Status::NeedsMoreRatings];
        yield '未达人数' => [4, 0, 0, 1.0, Status::NeedsMoreRatings];
        yield '百分之八十' => [4, 0, 1, 0.8, Status::Helpful];
        yield '百分之二十' => [1, 0, 4, 0.2, Status::NotHelpful];
        yield '部分有帮助' => [0, 5, 0, 0.5, Status::NeedsMoreRatings];
        yield '混合' => [2, 2, 1, 0.6, Status::NeedsMoreRatings];
        yield '低于上界' => [3, 1, 1, 0.7, Status::NeedsMoreRatings];
        yield '高于下界' => [1, 1, 3, 0.3, Status::NeedsMoreRatings];
        yield '全部肯定' => [1, 0, 0, 1.0, Status::Helpful, 1, 100, 0];
        yield '全部否定' => [0, 0, 1, 0.0, Status::NotHelpful, 1, 100, 0];
        yield '小数评分高于整数阈值' => [1, 1, 2, 0.375, Status::Helpful, 1, 37, 20];
        yield '小数评分低于整数阈值' => [1, 1, 2, 0.375, Status::NeedsMoreRatings, 1, 38, 20];
        yield '浮点下界不应越界' => [7, 0, 18, 0.28, Status::NotHelpful, 5, 80, 28];
        yield '浮点上界不应漏判' => [29, 0, 21, 0.58, Status::Helpful, 5, 58, 20];
    }
}
