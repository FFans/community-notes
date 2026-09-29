<?php

namespace FFans\CommunityNotes\Listener;

use FFans\CommunityNotes\Settings\ScoringSettings;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidateScoringSettings
{
    public function __construct(private SettingsRepositoryInterface $settings, private TranslatorInterface $translator)
    {
    }

    public function __invoke(Saving $event): void
    {
        $keys = [ScoringSettings::MIN_RATINGS, ScoringSettings::HELPFUL_THRESHOLD, ScoringSettings::NOT_HELPFUL_THRESHOLD];
        if (!array_intersect($keys, array_keys($event->settings))) {
            return;
        }

        // 合并尚未提交的当前值，确保只修改一个阈值时也不能破坏约束。
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = array_key_exists($key, $event->settings) ? $event->settings[$key] : $this->settings->get($key);
        }

        $errors = [];
        $minimum = $values[ScoringSettings::MIN_RATINGS];
        if ((!is_int($minimum) && !(is_string($minimum) && preg_match('/^[0-9]+$/D', $minimum))) || $minimum < 1 || $minimum > 100) {
            $errors[ScoringSettings::MIN_RATINGS] = $this->translator->trans('ffans-community-notes.validation.settings.min_ratings_invalid');
        }

        foreach ([
                     ScoringSettings::HELPFUL_THRESHOLD => 'ffans-community-notes.validation.settings.helpful_threshold_invalid',
                     ScoringSettings::NOT_HELPFUL_THRESHOLD => 'ffans-community-notes.validation.settings.not_helpful_threshold_invalid',
                 ] as $key => $message) {
            $value = $values[$key];
            if ((!is_int($value) && !(is_string($value) && preg_match('/^[0-9]+$/D', $value))) || $value < 0 || $value > 100) {
                $errors[$key] = $this->translator->trans($message);
            }
        }

        if (!isset($errors[ScoringSettings::HELPFUL_THRESHOLD])
            && !isset($errors[ScoringSettings::NOT_HELPFUL_THRESHOLD])
            && $values[ScoringSettings::NOT_HELPFUL_THRESHOLD] >= $values[ScoringSettings::HELPFUL_THRESHOLD]) {
            $errors[ScoringSettings::NOT_HELPFUL_THRESHOLD] = $this->translator->trans('ffans-community-notes.validation.settings.thresholds_invalid');
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }
}
