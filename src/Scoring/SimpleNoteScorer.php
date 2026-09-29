<?php

namespace FFans\CommunityNotes\Scoring;

use FFans\CommunityNotes\Enum\CommunityNoteRatingValue as Value;
use FFans\CommunityNotes\Enum\CommunityNoteStatus as Status;
use FFans\CommunityNotes\Model\CommunityNote;
use FFans\CommunityNotes\Settings\ScoringSettings;
use Flarum\Settings\SettingsRepositoryInterface;

class SimpleNoteScorer implements NoteScorer
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function score(CommunityNote $note): ScoreResult
    {
        // 直接聚合真实评价，不读取可能过时的关系缓存或附注计数字段。
        $counts = $note->ratings()->selectRaw('value, COUNT(*) AS total')->groupBy('value')->pluck('total', 'value');
        $helpful = (int)($counts[Value::Helpful->value] ?? 0);
        $somewhat = (int)($counts[Value::SomewhatHelpful->value] ?? 0);
        $notHelpful = (int)($counts[Value::NotHelpful->value] ?? 0);
        $total = $helpful + $somewhat + $notHelpful;
        $score = $total ? ($helpful + $somewhat * Value::SomewhatHelpful->numericValue()) / $total : null;
        $status = Status::NeedsMoreRatings;

        if ($total > 0 && $total >= (int)$this->settings->get(ScoringSettings::MIN_RATINGS)) {
            // 半分单位交叉相乘，避免先除后乘使 28%、58% 等恰好边界偏移。
            $weightedPercent = ($helpful * 2 + $somewhat) * 50;
            if ($weightedPercent >= (float)$this->settings->get(ScoringSettings::HELPFUL_THRESHOLD) * $total) {
                $status = Status::Helpful;
            } elseif ($weightedPercent <= (float)$this->settings->get(ScoringSettings::NOT_HELPFUL_THRESHOLD) * $total) {
                $status = Status::NotHelpful;
            }
        }

        return new ScoreResult($total, $helpful, $somewhat, $notHelpful, $score, $status);
    }
}
