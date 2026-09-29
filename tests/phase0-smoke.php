<?php

// 使用独立 SQLite 论坛检查骨架，不读取或修改本机论坛配置。
namespace FFans\CommunityNotes\Tests;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Config;
use Flarum\Foundation\InstalledSite;
use Flarum\Foundation\Paths;
use Flarum\Testing\integration\Setup\SetupScript;
use Laminas\Diactoros\ServerRequest;
use RuntimeException;

$root = dirname(__DIR__);
$loader = require $root.'/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo $message."：通过\n";
}

check(isset($loader->getPrefixesPsr4()['FFans\\CommunityNotes\\']), 'Composer PSR-4 注册');

// 每次使用新目录，不清理已有文件或数据库。
$tmp = __DIR__.'/tmp/phase0-'.bin2hex(random_bytes(6));
mkdir($tmp, 0777, true);
touch($tmp.'/database.sqlite');
putenv('FLARUM_TEST_TMP_DIR_LOCAL='.$tmp);
putenv('FLARUM_TEST_VENDOR_PATH='.$root.'/vendor');
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE='.$tmp.'/database.sqlite');
putenv('DB_PASSWORD=');
putenv('DB_PREFIX=');
(new SetupScript())->run();

// 独立 Composer 清单让原生管理器发现根包；资产副本也仅写入测试目录。
$vendor = $tmp.'/vendor';
$files = new \Illuminate\Filesystem\Filesystem();
foreach (['fortawesome/font-awesome/css'] as $packageName) {
    $files->copyDirectory($root.'/vendor/'.$packageName, $vendor.'/'.$packageName);
}
$manifest = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true);
$package = json_decode(file_get_contents($root.'/composer.json'), true);
$package['version'] = '0.0.0';
$package['install-path'] = '../../../../../';
$manifest['packages'][] = $package;
file_put_contents($vendor.'/composer/installed.json', json_encode($manifest));
$paths = new Paths([
    'base' => $tmp,
    'public' => $tmp.'/public',
    'storage' => $tmp.'/storage',
    'vendor' => $vendor,
]);
$config = new Config(require $tmp.'/config.php');

$boot = fn () => (new InstalledSite($paths, $config))->bootApp();
$app = $boot();
$manager = $app->getContainer()->make(ExtensionManager::class);
check($manager->getExtension('ffans-community-notes') !== null, '扩展 ID 识别');
check(! $manager->isEnabled('ffans-community-notes'), '启用前启动');
$manager->enable('ffans-community-notes');
check($manager->isEnabled('ffans-community-notes'), '扩展启用');

$app = $boot();
$container = $app->getContainer();
check($container->make(ExtensionManager::class)->isEnabled('ffans-community-notes'), '启用状态持久化与重新启动');
foreach (['forum', 'admin'] as $frontend) {
    $assets = $container->make('flarum.assets.'.$frontend);
    $js = $assets->makeJs();
    $js->commit();
    check(str_contains($assets->getAssetsDir()->get($js->getFilename()), 'ffans-community-notes'), $frontend.' 扩展 JS 已纳入资产');
    $localeJs = $assets->makeLocaleJs('zh-Hans');
    $localeJs->commit();
    $localeContent = $assets->getAssetsDir()->get($localeJs->getFilename());
    $key = $frontend === 'forum' ? 'forum.note.manage_a11y_label' : 'admin.permissions.moderate';
    check(str_contains($localeContent, 'ffans-community-notes.'.$key), $frontend.' 中文词条已纳入原生语言资产');
    check(!str_contains($localeContent, '=> ffans-community-notes.'), $frontend.' 中文翻译引用已解析');
    $assets->makeCss()->commit();
    echo $frontend." 原生 JS/LESS 编译：通过\n";
}
$response = $app->getRequestHandler()->handle(new ServerRequest([], [], 'http://localhost/', 'GET'));
check($response->getStatusCode() === 200, '启用后论坛 HTTP 200');
$response = $app->getRequestHandler()->handle(new ServerRequest([], [], 'http://localhost/community-notes', 'GET'));
check($response->getStatusCode() === 200, '社区附注前端直达路由 HTTP 200');
$response = $app->getRequestHandler()->handle(new ServerRequest([], [], 'http://localhost/community-notes?tab=hidden', 'GET'));
check($response->getStatusCode() === 200, '社区附注已隐藏页签直达 HTTP 200');
$response = $app->getRequestHandler()->handle(new ServerRequest([], [], 'http://localhost/community-notes/posts/1', 'GET'));
check($response->getStatusCode() === 200, '社区附注评价页直达路由 HTTP 200');
$container->make(ExtensionManager::class)->disable('ffans-community-notes');
check(! $container->make(ExtensionManager::class)->isEnabled('ffans-community-notes'), '扩展禁用');
$app = $boot();
check(! $app->getContainer()->make(ExtensionManager::class)->isEnabled('ffans-community-notes'), '禁用状态持久化与重新启动');
$response = $app->getRequestHandler()->handle(new ServerRequest([], [], 'http://localhost/', 'GET'));
check($response->getStatusCode() === 200, '禁用后论坛 HTTP 200');
echo '隔离测试目录：'.$tmp."\n";
