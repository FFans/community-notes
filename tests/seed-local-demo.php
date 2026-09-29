<?php

// 显式创建并保留本地演示数据，不修改既有讨论、用户、权限或评分设置。
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--localflarum') {
    fwrite(STDERR, "请使用 php tests/seed-local-demo.php --localflarum 创建一组本地演示数据。\n");
    exit(1);
}

$site = require 'D:/phpstudy_pro/WWW/localflarum/flarum/site.php';
$container = $site->bootApp()->getContainer();
$config = $container->make(Flarum\Foundation\Config::class);
if (rtrim($config['url'], '/') !== 'http://localflarum:820'
    || !$container->make(Flarum\Extension\ExtensionManager::class)->isEnabled('ffans-community-notes')) {
    throw new RuntimeException('仅允许在已启用社区附注的 localflarum:820 执行。');
}

$db = $container->make(Illuminate\Database\ConnectionInterface::class);
$settings = $container->make(Flarum\Settings\SettingsRepositoryInterface::class);
$minRatings = (int) $settings->get(FFans\CommunityNotes\Settings\ScoringSettings::MIN_RATINGS);
if ($minRatings < 1 || $minRatings > 100) {
    throw new RuntimeException('最少评价人数超出允许范围，未创建测试数据。');
}
$admin = Flarum\User\User::findOrFail(1);
$admin->assertAdmin();
$marker = 'cn-demo-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
$now = Carbon\Carbon::now();
$manifest = $db->transaction(function () use ($db, $container, $admin, $minRatings, $marker, $now) {
    $group = $db->table('groups')->insertGetId([
        'name_singular' => '附注演示贡献者', 'name_plural' => '附注演示贡献者', 'is_hidden' => true,
    ]);
    foreach (['create', 'rate'] as $permission) {
        $db->table('group_permission')->insert(['group_id' => $group, 'permission' => 'ffans-community-notes.note.'.$permission]);
    }
    $users = [];
    $makeUser = function (string $label, bool $contributor = true) use ($db, $group, $marker, $now, &$users) {
        $number = count($users) + 1;
        $id = $db->table('users')->insertGetId([
            'username' => $label.'_'.$number.'_'.substr($marker, -6),
            'email' => $marker.'-'.$number.'@example.test',
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'is_email_confirmed' => true, 'joined_at' => $now,
        ]);
        if ($contributor) {
            $db->table('group_user')->insert(['group_id' => $group, 'user_id' => $id]);
        }
        $users[] = $id;

        return Flarum\User\User::findOrFail($id);
    };
    $postAuthor = $makeUser('附注测试作者', false);
    $noteAuthor = $makeUser('附注测试贡献者甲');
    $otherAuthor = $makeUser('附注测试贡献者乙');
    $raters = [];
    for ($index = 0; $index < $minRatings; $index++) {
        $raters[] = $makeUser('附注测试评价者');
    }
    $container->make(Flarum\Group\PermissionCache::class)->flush();
    $discussion = $db->table('discussions')->insertGetId([
        'title' => '社区附注测试：不同状态与帖子菜单', 'slug' => $marker,
        'user_id' => $postAuthor->id, 'created_at' => $now, 'comment_count' => 6,
        'participant_count' => 1, 'last_post_number' => 6,
        'last_posted_at' => $now, 'last_posted_user_id' => $postAuthor->id,
    ]);
    $scenarios = [
        '无附注' => '这条帖子没有任何附注。管理员打开帖子菜单时，应当看不到“管理附注”。',
        '待评价' => '这条帖子有一条尚未获得评价的附注。正文下方不显示公开卡片，但管理员可以通过帖子菜单管理它。',
        '已展示' => '这条帖子有一条已达到当前评价阈值的附注。正文下方应显示公开卡片，并提供来源链接。',
        '没有帮助' => '这条帖子的附注已被评价为没有帮助。它不会公开展示，管理员仍可在帖子菜单中找到它。',
        '已隐藏' => '这条帖子的附注达到展示条件后被隐藏。公开卡片消失，管理员仍可通过菜单或已隐藏列表恢复它。',
        '同帖多条附注' => '这条帖子同时包含已展示、待评价和已隐藏的附注。公开区域只显示符合条件的一条，管理弹窗应列出全部三条。',
    ];
    $posts = [];
    $formatter = $container->make(Flarum\Formatter\Formatter::class);
    foreach ($scenarios as $label => $content) {
        $number = count($posts) + 1;
        $posts[$label] = $db->table('posts')->insertGetId([
            'discussion_id' => $discussion, 'number' => $number, 'user_id' => $postAuthor->id,
            'type' => 'comment', 'content' => $formatter->parse($label.'：'.$content), 'created_at' => $now,
        ]);
    }
    $db->table('discussions')->where('id', $discussion)->update([
        'first_post_id' => reset($posts), 'last_post_id' => end($posts),
    ]);
    $db->table('users')->where('id', $postAuthor->id)->update(['discussion_count' => 1, 'comment_count' => 6]);
    $create = $container->make(FFans\CommunityNotes\Service\CreateCommunityNote::class);
    $rate = $container->make(FFans\CommunityNotes\Service\RateCommunityNote::class);
    $hide = $container->make(FFans\CommunityNotes\Service\HideCommunityNote::class);
    $notes = [];
    $makeNote = function (string $label, int $post, Flarum\User\User $author, ?string $value = null, bool $hidden = false) use ($create, $rate, $hide, $admin, $raters, &$notes) {
        $note = $create->handle($author, $post, [
            'reason' => 'missing_context',
            'content' => '这是一条用于检查“'.$label.'”展示状态的测试附注。所列来源仅用于验证链接展示，请通过帖子菜单、附注详情和评价列表对照查看。',
            'sources' => ['https://docs.flarum.org/extend/'],
        ]);
        if ($value !== null) {
            foreach ($raters as $rater) {
                $note = $rate->handle($rater, $note->id, ['value' => $value, 'reasons' => [$value === 'helpful' ? 'clear' : 'incorrect']]);
            }
        }
        if ($hidden) {
            $note = $hide->handle($admin, $note->id, ['reason' => '演示隐藏状态，便于测试恢复与管理入口。']);
        }
        $notes[$label] = ['id' => $note->id, 'postId' => $post, 'status' => $note->status->value, 'hidden' => $note->is_hidden];
    };
    $makeNote('待评价', $posts['待评价'], $noteAuthor);
    $makeNote('已展示', $posts['已展示'], $noteAuthor, 'helpful');
    $makeNote('没有帮助', $posts['没有帮助'], $noteAuthor, 'not_helpful');
    $makeNote('已隐藏', $posts['已隐藏'], $noteAuthor, 'helpful', true);
    $makeNote('同帖已展示', $posts['同帖多条附注'], $noteAuthor, 'helpful');
    $makeNote('同帖待评价', $posts['同帖多条附注'], $otherAuthor);
    $makeNote('同帖已隐藏', $posts['同帖多条附注'], $raters[0], null, true);

    // 提交事务前通过真实资源 API 核验菜单数据与公开附注，失败则全部回滚。
    $api = $container->make(Flarum\Api\Client::class)->withActor($admin);
    $expectedCounts = [0, 1, 1, 1, 1, 3];
    foreach (array_values($posts) as $index => $post) {
        $response = $api->get('/posts/'.$post);
        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || ($body['data']['attributes']['communityNoteCount'] ?? null) !== $expectedCounts[$index]) {
            throw new RuntimeException('测试帖子管理入口数据校验失败，已回滚。');
        }
        $public = $body['data']['relationships']['communityNote']['data'];
        if (($public !== null) !== in_array($index, [2, 5], true)) {
            throw new RuntimeException('公开附注状态校验失败，已回滚。');
        }
    }

    return ['marker' => $marker, 'discussionId' => $discussion, 'posts' => $posts, 'notes' => $notes,
        'userIds' => $users, 'groupId' => $group, 'minRatings' => $minRatings,
        'url' => 'http://localflarum:820/d/'.$discussion.'-'.$marker];
});

$directory = __DIR__.'/tmp';
if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$manifestPath = $directory.'/'.$marker.'.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo json_encode(['讨论链接' => $manifest['url'], '帖子数' => count($manifest['posts']),
    '附注数' => count($manifest['notes']), '最少评价人数' => $minRatings, '数据清单' => $manifestPath], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
