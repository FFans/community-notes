<?php

namespace FFans\CommunityNotes\Tests\Unit;

use FFans\CommunityNotes\Enum\CommunityNoteRatingValue;
use FFans\CommunityNotes\Validator\CommunityNoteRatingValidator;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\LocaleManager;
use Flarum\Locale\PrefixedYamlFileLoader;
use Flarum\Locale\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommunityNoteRatingValidatorTest extends TestCase
{
    private Translator $translator;

    protected function setUp(): void
    {
        $this->translator = new Translator('zh-Hans');
        $this->translator->addLoader('prefixed_yaml', new PrefixedYamlFileLoader());
        (new LocaleManager($this->translator))->addTranslations('zh-Hans', __DIR__.'/../../locale/zh-Hans.yml');
    }

    public function test_all_values_and_allowed_reasons_and_three_reason_boundary(): void
    {
        $validator = new CommunityNoteRatingValidator($this->translator);
        foreach (CommunityNoteRatingValue::cases() as $value) {
            foreach ($value->allowedReasons() as $reason) {
                $result = $validator->validate(['value' => $value->value, 'reasons' => [$reason->value]]);
                $this->assertSame($value, $result['value']);
                $this->assertSame([$reason], $result['reasons']);
            }
            $reasons = array_slice($value->allowedReasons(), 0, 3);
            $this->assertSame($reasons, $validator->validate(['value' => $value->value, 'reasons' => array_column($reasons, 'value')])['reasons']);
        }
    }

    #[DataProvider('invalidRatings')]
    public function test_invalid_ratings_are_rejected(array $data): void
    {
        $this->expectException(ValidationException::class);
        (new CommunityNoteRatingValidator($this->translator))->validate($data);
    }

    public static function invalidRatings(): iterable
    {
        yield [[]];
        foreach ([null, [], 1, 'invalid', ''] as $value) {
            yield [['value' => $value, 'reasons' => ['clear']]];
        }
        foreach ([null, [], 'clear', ['a' => 'clear'], ['clear', 'neutral', 'reliable_sources', 'important_context'], ['clear', 'clear'], ['incorrect'], ['invalid'], [null], [[]], [1]] as $reasons) {
            yield [['value' => 'helpful', 'reasons' => $reasons]];
        }
        foreach (CommunityNoteRatingValue::cases() as $value) {
            foreach (CommunityNoteRatingValue::cases() as $other) {
                if ($value !== $other) {
                    yield [['value' => $value->value, 'reasons' => [$other->allowedReasons()[0]->value]]];
                }
            }
        }
    }
}
