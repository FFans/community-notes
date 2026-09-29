<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Settings\ScoringSettings;
use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Api\Client;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        foreach ([ScoringSettings::MIN_RATINGS, ScoringSettings::HELPFUL_THRESHOLD, ScoringSettings::NOT_HELPFUL_THRESHOLD] as $key) {
            $settings->delete($key);
        }
        parent::tearDown();
    }

    public function test_defaults_do_not_overwrite_saved_settings(): void
    {
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        $this->assertEquals(5, $settings->get(ScoringSettings::MIN_RATINGS));
        $this->assertEquals(80, $settings->get(ScoringSettings::HELPFUL_THRESHOLD));
        $this->assertEquals(20, $settings->get(ScoringSettings::NOT_HELPFUL_THRESHOLD));
        $settings->set(ScoringSettings::MIN_RATINGS, '12');
        $this->assertSame('12', $settings->get(ScoringSettings::MIN_RATINGS));
        $settings->delete(ScoringSettings::MIN_RATINGS);
        $this->assertEquals(5, $settings->get(ScoringSettings::MIN_RATINGS));
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_api_rejects_entire_update(array $values): void
    {
        $client = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        $original = array_intersect_key($settings->all(), $values);
        $response = $client->withBody($values)->post('/settings');
        $this->assertSame(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame($original, array_intersect_key($settings->all(), $values));
    }

    public static function invalidSettings(): iterable
    {
        foreach ([0, 101, -1, 1.5, '2.5', '', null, true, [], 'five'] as $value) {
            yield [[ScoringSettings::MIN_RATINGS => $value]];
        }
        foreach ([ScoringSettings::HELPFUL_THRESHOLD => 80, ScoringSettings::NOT_HELPFUL_THRESHOLD => 20] as $key => $valid) {
            foreach ([-1, 101, $valid + 0.5, $valid . '.5', $valid . '.0', $valid . 'e0', '', null, true, [], 'invalid'] as $value) {
                yield [[$key => $value]];
            }
        }
        yield [[ScoringSettings::MIN_RATINGS => 8, ScoringSettings::HELPFUL_THRESHOLD => 20]];
        yield [[ScoringSettings::NOT_HELPFUL_THRESHOLD => 80]];
        yield [[ScoringSettings::HELPFUL_THRESHOLD => 10, ScoringSettings::NOT_HELPFUL_THRESHOLD => 90]];
    }

    public function test_valid_boundaries_and_partial_updates(): void
    {
        $client = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        foreach ([1, 100] as $minimum) {
            $response = $client->withBody([
                ScoringSettings::MIN_RATINGS => (string) $minimum,
                ScoringSettings::HELPFUL_THRESHOLD => '100',
                ScoringSettings::NOT_HELPFUL_THRESHOLD => '0',
            ])->post('/settings');
            $this->assertSame(204, $response->getStatusCode(), (string) $response->getBody());
        }
        $this->assertSame(204, $client->withBody([ScoringSettings::HELPFUL_THRESHOLD => '1'])->post('/settings')->getStatusCode());
        $this->assertSame(422, $client->withBody([ScoringSettings::NOT_HELPFUL_THRESHOLD => '1'])->post('/settings')->getStatusCode());
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        $this->assertSame('0', $settings->get(ScoringSettings::NOT_HELPFUL_THRESHOLD));
        $this->assertSame('1', $settings->get(ScoringSettings::HELPFUL_THRESHOLD));
    }

    public function test_member_cannot_change_settings(): void
    {
        $client = self::$container->make(Client::class)->withActor(User::findOrFail($this->user()));
        $this->assertSame(403, $client->withBody([ScoringSettings::MIN_RATINGS => 10])->post('/settings')->getStatusCode());
    }

    public function test_unrelated_settings_are_not_intercepted(): void
    {
        $client = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        $this->assertSame(204, $client->withBody(['forum_title' => $settings->get('forum_title')])->post('/settings')->getStatusCode());
    }
}
