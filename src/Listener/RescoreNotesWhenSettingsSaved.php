<?php

namespace FFans\CommunityNotes\Listener;

use FFans\CommunityNotes\Service\RescoreAllCommunityNotes;
use FFans\CommunityNotes\Settings\ScoringSettings;
use Flarum\Settings\Event\Saved;
use Flarum\Settings\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

class RescoreNotesWhenSettingsSaved
{
    public function __construct(private SettingsRepositoryInterface $settings, private RescoreAllCommunityNotes $rescore)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $changed = false;
        $events->listen(Saving::class, function (Saving $event) use (&$changed) {
            $changed = false;
            foreach ([ScoringSettings::MIN_RATINGS, ScoringSettings::HELPFUL_THRESHOLD, ScoringSettings::NOT_HELPFUL_THRESHOLD] as $key) {
                if (array_key_exists($key, $event->settings) && (float)$event->settings[$key] !== (float)$this->settings->get($key)) {
                    $changed = true;
                }
            }
        });
        $events->listen(Saved::class, function () use (&$changed) {
            $rescore = $changed;
            $changed = false;
            if ($rescore) {
                $this->rescore->handle();
            }
        });
    }
}
