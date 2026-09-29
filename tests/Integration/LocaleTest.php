<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Settings\ScoringSettings;
use FFans\CommunityNotes\Tests\DomainTestCase;
use FFans\CommunityNotes\Validator\CommunityNoteValidator;
use Flarum\Api\Client;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\LocaleManager;
use Flarum\User\User;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleTest extends DomainTestCase
{
    private string $originalLocale;

    protected function setUp(): void
    {
        parent::setUp();
        $locales = self::$container->make(LocaleManager::class);
        $this->originalLocale = $locales->getLocale();
        $locales->setLocale('zh-Hans');
    }

    protected function tearDown(): void
    {
        self::$container->make(LocaleManager::class)->setLocale($this->originalLocale);
        parent::tearDown();
    }

    public function test_registered_locale_and_injected_translator_follow_current_language(): void
    {
        $locales = self::$container->make(LocaleManager::class);
        $translator = $locales->getTranslator();
        $this->assertSame($translator, self::$container->make(TranslatorInterface::class));
        $this->assertSame('社区附注', $translator->trans('ffans-community-notes.forum.navigation.title'));

        $validator = self::$container->make(CommunityNoteValidator::class);
        $key = 'ffans-community-notes.validation.note.reason_invalid';
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [$key => '另一种语言的校验提示'], 'fr', 'messages+intl-icu');
        foreach (['zh-Hans' => '请选择有效的附注原因。', 'fr' => '另一种语言的校验提示'] as $locale => $message) {
            $locales->setLocale($locale);
            try {
                $validator->validate(['reason' => 'invalid', 'content' => str_repeat('文', 30), 'sources' => ['https://example.test']]);
                $this->fail('应拒绝无效的附注原因。');
            } catch (ValidationException $exception) {
                $this->assertSame($message, $exception->getAttributes()['reason']);
            }
        }
    }

    public function test_form_validation_responses_use_registered_chinese_translations(): void
    {
        $api = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        $note = $this->note();
        $requests = [
            ['post', '/community-notes', [], '请提交有效的社区附注数据，仅允许修改原因、正文和来源。'],
            ['post', '/community-notes', ['data' => [
                'type' => 'community-notes', 'attributes' => [],
                'relationships' => ['post' => ['data' => ['type' => 'posts', 'id' => (string) $this->post()]]],
            ]], '请选择有效的附注原因。'],
            ['put', '/community-notes/'.$note.'/rating', ['value' => 'helpful', 'reasons' => []], '请选择 1 至 3 个评价理由。'],
            ['post', '/community-notes/'.$note.'/hide', ['reason' => ''], '请填写隐藏此社区附注的原因。'],
            ['post', '/settings', [ScoringSettings::HELPFUL_THRESHOLD => 101], '有帮助阈值必须是 0 至 100 之间的整数。'],
        ];
        foreach ($requests as [$method, $url, $body, $message]) {
            $response = $api->withBody($body)->{$method}($url);
            $this->assertSame(422, $response->getStatusCode(), (string) $response->getBody());
            $errors = json_decode((string) $response->getBody(), true)['errors'];
            $this->assertContains($message, array_column($errors, 'detail'));
        }
    }

    public function test_query_errors_remain_english_when_current_locale_is_chinese(): void
    {
        $api = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        foreach ([
            [['page' => 'invalid'], 'Invalid pagination parameters.'],
            [['page' => ['offset' => -1]], 'Pagination parameters must be valid integers.'],
            [['filter' => ['invalid' => 'value']], 'Unsupported community note filter or sort.'],
            [['filter' => ['moderation' => 'invalid']], 'Invalid moderation filter.'],
            [['filter' => ['post' => 0]], 'Invalid post ID.'],
            [['filter' => ['status' => 'invalid']], 'Invalid community note status.'],
            [['filter' => ['queue' => 'invalid']], 'Invalid rating queue.'],
            [['filter' => ['feed' => 'invalid']], 'Invalid post feed filter.'],
        ] as [$query, $message]) {
            $response = $api->withQueryParams($query)->get('/community-notes');
            $this->assertSame(400, $response->getStatusCode(), (string) $response->getBody());
            $errors = json_decode((string) $response->getBody(), true)['errors'];
            $this->assertContains($message, array_column($errors, 'detail'));
        }
    }
}
