<?php

namespace FFans\CommunityNotes\Tests\Unit;

use FFans\CommunityNotes\Validator\CommunityNoteValidator;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\LocaleManager;
use Flarum\Locale\PrefixedYamlFileLoader;
use Flarum\Locale\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommunityNoteValidatorTest extends TestCase
{
    private Translator $translator;

    protected function setUp(): void
    {
        $this->translator = new Translator('zh-Hans');
        $this->translator->addLoader('prefixed_yaml', new PrefixedYamlFileLoader());
        (new LocaleManager($this->translator))->addTranslations('zh-Hans', __DIR__.'/../../locale/zh-Hans.yml');
    }

    public function test_unicode_boundaries_trim_and_preserve_plain_text_and_urls(): void
    {
        foreach ([30, 1000] as $length) {
            $text = str_repeat('𠮷', $length);
            $url = 'HTTPS://Example.com/path?b=2&a=1#section';
            $result = (new CommunityNoteValidator($this->translator))->validate(['reason' => 'other', 'content' => "　\n$text \t", 'sources' => [" $url "]]);
            $this->assertSame($text, $result['content']);
            $this->assertSame([$url], $result['sources']);
        }
        $text = str_repeat('<b>纯文本</b> **原样** ', 5);
        $this->assertSame(trim($text), (new CommunityNoteValidator($this->translator))->validate(['reason' => 'other', 'content' => $text, 'sources' => ['http://example.test']])['content']);
        $sources = array_map(fn ($i) => 'https://example.test/'.$i, range(1, 5));
        $this->assertSame($sources, (new CommunityNoteValidator($this->translator))->validate(['reason' => 'other', 'content' => str_repeat('文', 30), 'sources' => $sources])['sources']);
        $url = 'https://example.test/'.str_repeat('a', 2027);
        $this->assertSame(2048, strlen($url));
        $this->assertSame([$url], (new CommunityNoteValidator($this->translator))->validate(['reason' => 'other', 'content' => str_repeat('文', 30), 'sources' => [$url]])['sources']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(array $override, string $field): void
    {
        try {
            (new CommunityNoteValidator($this->translator))->validate(array_replace(['reason' => 'other', 'content' => str_repeat('文', 30), 'sources' => ['https://example.test']], $override));
            $this->fail('应拒绝无效输入');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->getAttributes());
        }
    }

    public static function invalidInputs(): iterable
    {
        foreach ([null, [], 1, 'unknown', ''] as $reason) {
            yield [['reason' => $reason], 'reason'];
        }
        foreach ([null, [], 1, '', '   ', str_repeat('文', 29), str_repeat('文', 1001), "\xff"] as $content) {
            yield [['content' => $content], 'content'];
        }
        foreach ([null, [], 'https://example.test', ['url' => 'https://example.test'], array_fill(0, 6, 'https://example.test')] as $sources) {
            yield [['sources' => $sources], 'sources'];
        }
        foreach ([null, [], 1, '', 'javascript:alert(1)', 'data:text/plain,foo', '//example.test', 'ftp://example.test', 'https:///path', 'https://', 'https://example.test/a b', "https://example.test/\nfoo", 'https://example.test/\\foo', 'https://example.test/'.str_repeat('a', 2029)] as $url) {
            yield [['sources' => [$url]], 'sources.0'];
        }
        yield [['sources' => ['https://example.test', ' https://example.test ']], 'sources.1'];
    }
}
