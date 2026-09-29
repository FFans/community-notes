<?php

// 创建独立浏览器验收论坛；不读取或修改 localflarum。仅监听本机回环地址。
require dirname(__DIR__).'/vendor/autoload.php';

use FFans\CommunityNotes\Access\CommunityNotePermissions as Permissions;
use FFans\CommunityNotes\Tests\DomainTestCase;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

$fixture = new class('浏览器验收') extends DomainTestCase
{
    public function create(): void
    {
        self::setUpBeforeClass();
        $this->db = self::$container->make(ConnectionInterface::class);
        $path = self::$container->make(Paths::class)->base;
        $config = require $path.'/config.php';
        $config['url'] = 'http://127.0.0.1:18206';
        file_put_contents($path.'/config.php', "<?php return ".var_export($config, true).";\n");
        $settings = self::$container->make(SettingsRepositoryInterface::class);
        $settings->set('forum_title', '社区附注验收论坛');
        $settings->set('color_scheme', 'auto');
        $users = [];
        foreach (['Alice' => [], 'Bob' => [Permissions::CREATE], 'Carol' => [Permissions::RATE],
            'Dave' => [Permissions::RATE], 'Erin' => [Permissions::RATE], 'Frank' => [Permissions::RATE],
            'Grace' => [Permissions::RATE], 'Henry' => [Permissions::RATE], 'Moderator' => [Permissions::MODERATE]] as $name => $permissions) {
            $actor = $this->contributor($permissions);
            $actor->username = $name;
            $actor->password = 'CommunityNotes-Test-2026!';
            $actor->save();
            $users[$name] = $actor->id;
        }
        $post = $this->post();
        $this->db->table('posts')->where('id', $post)->update([
            'user_id' => $users['Alice'], 'content' => '<r>本帖用于验收社区附注。请为这段内容补充可核对的来源与背景。</r>',
        ]);
        $discussion = $this->db->table('posts')->where('id', $post)->value('discussion_id');
        $this->db->table('discussions')->where('id', $discussion)->update([
            'title' => '社区附注完整流程验收', 'slug' => 'community-notes-test', 'user_id' => $users['Alice'],
            'first_post_id' => $post, 'last_post_id' => $post, 'comment_count' => 1,
        ]);
        file_put_contents($path.'/browser-fixture.json', json_encode(['users' => $users, 'post' => $post, 'discussion' => $discussion]));
        if (in_array('--notes', $_SERVER['argv'], true)) {
            $helpful = $this->note([
                'post_id' => $post, 'user_id' => $users['Bob'], 'status' => 'helpful', 'rating_count' => 5, 'score' => 1,
                'content' => '这里补充一条已经被评价为有帮助的附注。阅读结论之前，也可以查看原始资料，核对信息的适用范围与发布时间。',
            ]);
            foreach (['Dave', 'Erin', 'Frank', 'Grace', 'Henry'] as $name) {
                $rating = $this->rating($helpful, $users[$name]);
                $this->db->table('ffans_community_notes_rating_reasons')->insert(['rating_id' => $rating, 'reason' => 'clear']);
            }
            $this->db->table('ffans_community_notes_note_sources')->insert([
                'note_id' => $helpful, 'url' => 'https://docs.flarum.org/extend/', 'position' => 0,
                'created_at' => '2026-09-25 12:00:00',
            ]);
            $pending = $this->note([
                'post_id' => $post, 'user_id' => $users['Dave'],
                'content' => '另一条附注提供了不同角度的背景。它仍在等待社区评价，同一条帖子下的附注应当一起显示，方便读者对照来源。',
            ]);
            $this->db->table('ffans_community_notes_note_sources')->insert([
                'note_id' => $pending, 'url' => 'https://docs.flarum.org/extend/', 'position' => 0,
                'created_at' => '2026-09-25 12:00:00',
            ]);
            $secondPost = $this->post();
            $this->db->table('posts')->where('id', $secondPost)->update([
                'user_id' => $users['Alice'], 'content' => '<r>这是另一条带有附注的帖子。列表应显示完整的原生帖子内容，并在底部提供查看所有附注的入口。</r>',
            ]);
            $this->db->table('discussions')->where('id', $this->db->table('posts')->where('id', $secondPost)->value('discussion_id'))->update([
                'user_id' => $users['Alice'], 'first_post_id' => $secondPost, 'last_post_id' => $secondPost, 'comment_count' => 1,
            ]);
            $this->note([
                'post_id' => $secondPost, 'user_id' => $users['Bob'], 'created_at' => '2026-09-26 12:00:00',
                'content' => '这条较新的附注用于验证最新页签。它还没有被评价为有帮助，因此不会出现在有帮助的帖子流中。',
            ]);
        }
        $autoload = var_export(dirname(__DIR__).'/vendor/autoload.php', true);
        file_put_contents($path.'/router.php', str_replace('__AUTOLOAD__', $autoload, <<<'PHP'
<?php
$asset = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($asset, '/assets/') && is_file(__DIR__.'/public'.$asset)) {
    return false;
}
require __AUTOLOAD__;
$site = Flarum\Foundation\Site::fromPaths([
    'base' => __DIR__, 'public' => __DIR__.'/public', 'storage' => __DIR__.'/storage', 'vendor' => __DIR__.'/vendor',
]);
(new Flarum\Http\Server($site))->listen();

PHP));
        echo "浏览器测试目录：$path\n";
        echo "账号：Alice、Bob、Carol、Dave、Erin、Frank、Grace、Moderator\n";
        echo "仅供本机测试的密码：CommunityNotes-Test-2026!\n";
        echo "启动：php -S 127.0.0.1:18206 -t $path/public $path/router.php\n";
    }
};

$fixture->create();
