<?php

// 显式运行：php tests/local-api-smoke.php --localflarum
// 仅使用本地论坛和本次创建的数据；不运行测试安装器，不重置数据库或修改评分设置。
if (($argv[1] ?? '') !== '--localflarum') {
    fwrite(STDERR, "请使用 --localflarum 明确选择本地论坛验证。\n");
    exit(1);
}

$site = require 'D:/phpstudy_pro/WWW/localflarum/flarum/site.php';
$container = $site->bootApp()->getContainer();
$db = $container->make(Illuminate\Database\ConnectionInterface::class);
$settings = $container->make(Flarum\Settings\SettingsRepositoryInterface::class);
$config = $container->make(Flarum\Foundation\Config::class);
if (rtrim($config['url'], '/') !== 'http://localflarum:820'
    || !$container->make(Flarum\Extension\ExtensionManager::class)->isEnabled('ffans-community-notes')) {
    throw new RuntimeException('仅允许在已启用社区附注的 localflarum:820 执行。');
}

$base = 'http://localflarum:820';
$marker = 'cnverify'.bin2hex(random_bytes(4));
$users = $groups = $tokens = [];
$discussionId = $postId = null;
$checks = 0;
$check = function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo $message."：通过\n";
};
$request = function (string $method, string $path, ?string $token = null, ?array $body = null) use ($base): array {
    $curl = curl_init($base.$path);
    $responseHeaders = [];
    $headers = ['Accept: application/vnd.api+json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Token '.$token;
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        }]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($raw === false) {
        throw new RuntimeException('本地 HTTP 请求失败：'.curl_error($curl));
    }
    curl_close($curl);

    return [$status, json_decode($raw, true) ?? [], $responseHeaders];
};
$makeUser = function (array $permissions = []) use (&$users, &$groups, &$tokens, $db, $marker): array {
    $name = $marker.'u'.count($users);
    $id = $db->table('users')->insertGetId(['username' => $name, 'email' => $name.'@example.test',
        'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'is_email_confirmed' => 1,
        'joined_at' => date('Y-m-d H:i:s')]);
    $users[] = $id;
    if ($permissions) {
        $group = $db->table('groups')->insertGetId(['name_singular' => $marker, 'name_plural' => $marker, 'is_hidden' => true]);
        $groups[] = $group;
        $db->table('group_user')->insert(['group_id' => $group, 'user_id' => $id]);
        foreach ($permissions as $permission) {
            $db->table('group_permission')->insert(['group_id' => $group, 'permission' => 'ffans-community-notes.note.'.$permission]);
        }
    }
    $token = Flarum\Http\DeveloperAccessToken::generate($id);
    $tokens[] = $token->id;

    return [$id, $token->token];
};

try {
    $check($db->getDriverName() === 'mysql', '本地 MySQL 连接');
    [$http] = $request('GET', '/');
    $check($http === 200, '本地论坛首页 HTTP 200');
    [$http] = $request('GET', '/api/ffans/community-notes');
    $check($http === 404, '旧前缀路由不再注册');
    [$postAuthor, $memberToken] = $makeUser();
    [$authorId, $authorToken] = $makeUser(['create', 'rate']);
    [$moderatorId, $moderatorToken] = $makeUser(['moderate']);
    $raters = [];
    for ($i = 0; $i < (int) $settings->get('ffans-community-notes.min_ratings'); $i++) {
        $raters[] = $makeUser(['rate']);
    }
    $discussionId = $db->table('discussions')->insertGetId(['title' => '社区附注本地接口验证 '.$marker,
        'slug' => $marker, 'user_id' => $postAuthor, 'created_at' => date('Y-m-d H:i:s'), 'comment_count' => 1]);
    $postId = $db->table('posts')->insertGetId(['discussion_id' => $discussionId, 'number' => 1,
        'user_id' => $postAuthor, 'type' => 'comment', 'content' => '<r>社区附注本地接口验证原帖。</r>',
        'created_at' => date('Y-m-d H:i:s')]);
    $db->table('discussions')->where('id', $discussionId)->update(['first_post_id' => $postId, 'last_post_id' => $postId]);
    $payload = ['data' => ['type' => 'community-notes', 'attributes' => [
        'reason' => 'missing_context', 'content' => str_repeat('本地接口验证附注正文。', 4), 'sources' => ['https://example.test/source'],
    ], 'relationships' => ['post' => ['data' => ['type' => 'posts', 'id' => (string) $postId]]]]];
    [$http] = $request('POST', '/api/community-notes', $memberToken, $payload);
    $check($http === 403, '无创建权限的实际 HTTP 请求返回 403');
    [$http, $body, $headers] = $request('POST', '/api/community-notes', $authorToken, $payload);
    $check($http === 201, '实际 HTTP 创建附注返回 201（实际 '.$http.'）');
    $id = $body['data']['id'];
    $url = '/api/community-notes/'.$id;
    $check($body['data']['attributes']['status'] === 'needs_more_ratings', '初始状态等待评价');
    $check($body['data']['links']['self'] === '/community-notes/'.$id, '资源 self 保持 Core 原生 API 内路径');
    $check(($headers['location'] ?? '') === $body['data']['links']['self'], '原生 Location 与 self 一致');
    [$http] = $request('GET', $url);
    $check($http === 404, '未公开附注对访客不可见');
    unset($payload['data']['relationships']);
    $payload['data']['attributes']['content'] = str_repeat('本地接口验证修改后的附注正文。', 3);
    [$http] = $request('PATCH', $url, $authorToken, $payload);
    $check($http === 200, '零评价作者编辑');
    [$http, $body] = $request('GET', '/api/community-notes?filter[queue]=rating&filter[post]='.$postId, $raters[0][1]);
    $check($http === 200 && count($body['data']) === 1, '候选队列包含本次附注');
    $check(!isset($body['data'][0]['relationships']['user']), '评价队列不泄露附注作者');
    [$http, $page] = $request('GET', '/api/community-notes?filter[post]='.$postId.'&page[limit]=1&page[offset]=1', $raters[0][1]);
    $check($http === 200 && $page['data'] === [] && $page['meta']['page']['total'] === 1, '原生 Index 分页及总数');
    $check(parse_url($page['links']['prev'] ?? '', PHP_URL_PATH) === '/community-notes', '分页链接保持 Core 原生 API 内路径');
    [$http] = $request('GET', '/api/community-notes?page[limit][]=1', $raters[0][1]);
    $check($http === 400, '非法分页输入通过原生 before 钩子拒绝');
    $rating = ['value' => 'helpful', 'reasons' => ['clear']];
    [$http] = $request('PUT', $url.'/rating', $authorToken, $rating);
    $check($http === 403, '作者不能评价自己的附注');
    foreach ($raters as [, $token]) {
        [$http, $body] = $request('PUT', $url.'/rating', $token, $rating);
        $check($http === 200, '贡献者评价请求');
    }
    $check($body['data']['attributes']['status'] === 'helpful', '达到阈值后实际 API 返回 helpful');
    [$http, $body] = $request('GET', $url.'?include=user');
    $check($http === 200 && !isset($body['data']['relationships']['user']), '公开读取不泄露作者');
    [$http] = $request('PATCH', $url, $authorToken, $payload);
    $check($http === 403, '获得评价后禁止编辑');
    [$http] = $request('DELETE', $url, $authorToken);
    $check($http === 403, '获得评价后禁止删除');
    [$http] = $request('POST', $url.'/hide', $memberToken, ['reason' => '测试']);
    $check($http === 403, '无管理权限不能隐藏');
    [$http] = $request('POST', $url.'/hide', $moderatorToken, ['reason' => ' ']);
    $check($http === 422, '隐藏必须填写原因');
    [$http, $body] = $request('POST', $url.'/hide', $moderatorToken, ['reason' => '本地测试隐藏原因']);
    $check($http === 200 && $body['data']['attributes']['isHidden'], '管理隐藏实际 HTTP 成功');
    $check($body['data']['attributes']['status'] === 'helpful' && count($body['data']['attributes']['ratings']) === count($raters), '隐藏保留评分和评价');
    [$http] = $request('GET', $url);
    $check($http === 404, '隐藏后公开详情不可访问');
    [$http, $body] = $request('GET', '/api/community-notes?filter[queue]=rating&filter[post]='.$postId, $moderatorToken);
    $check($http === 200 && $body['data'] === [], '隐藏后管理者评价队列也排除附注');
    [$http, $body] = $request('POST', $url.'/restore', $moderatorToken);
    $check($http === 200 && !$body['data']['attributes']['isHidden'] && $body['data']['attributes']['hiddenReason'] === null, '恢复清空当前隐藏字段');
    $history = $body['data']['attributes']['history'];
    $check(in_array('本地测试隐藏原因', array_column($history, 'reason'), true), '恢复保留原隐藏历史');
    $check($body['data']['relationships']['user']['data']['id'] === (string) $authorId, '管理者可读取作者');
    [$http] = $request('GET', $url);
    $check($http === 200, '恢复后重新公开');
    foreach ($raters as [, $token]) {
        [$http, $body] = $request('PUT', $url.'/rating', $token, ['value' => 'not_helpful', 'reasons' => ['incorrect']]);
        $check($http === 200, '已有评价修改请求');
    }
    $check($body['data']['attributes']['status'] === 'not_helpful' && $body['data']['attributes']['ratingCount'] === count($raters), '修改评价重算且人数不增加');
    [$http] = $request('GET', $url);
    $check($http === 404, '失去 helpful 状态后公开 API 移除');
    [, $secondAuthorToken] = $makeUser(['create']);
    $payload['data']['relationships'] = ['post' => ['data' => ['type' => 'posts', 'id' => (string) $postId]]];
    [$http, $body] = $request('POST', '/api/community-notes', $secondAuthorToken, $payload);
    $check($http === 201, '新路径创建零评价附注');
    [$http] = $request('DELETE', '/api/community-notes/'.$body['data']['id'], $secondAuthorToken);
    $check($http === 204, '新路径删除零评价附注');
    echo '本地 HTTP/API 共 '.$checks." 项检查通过。\n";
} finally {
    // 按本次创建的明确主键逐条清理，子表由已存在的外键级联处理。
    if ($discussionId !== null) {
        $db->table('discussions')->where('id', $discussionId)->update(['first_post_id' => null, 'last_post_id' => null]);
    }
    if ($postId !== null) {
        $db->table('posts')->where('id', $postId)->delete();
    }
    if ($discussionId !== null) {
        $db->table('discussions')->where('id', $discussionId)->delete();
    }
    foreach ($tokens as $tokenId) {
        $db->table('access_tokens')->where('id', $tokenId)->delete();
    }
    foreach ($users as $userId) {
        $db->table('users')->where('id', $userId)->delete();
    }
    foreach ($groups as $groupId) {
        $db->table('groups')->where('id', $groupId)->delete();
    }
    echo "本次本地验证数据已清理：$marker\n";
}
