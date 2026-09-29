<?php

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Config;
use Flarum\Foundation\InstalledSite;
use Flarum\Testing\integration\Setup\SetupScript;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

$paths = require __DIR__.'/bootstrap.php';
$root = dirname(__DIR__, 2);

if (! is_dir($paths->base)) {
    mkdir($paths->base, 0777, true);
}

putenv('FLARUM_TEST_TMP_DIR_LOCAL='.$paths->base);
putenv('FLARUM_TEST_VENDOR_PATH='.$root.'/vendor');
$driver = getenv('DB_DRIVER') ?: 'sqlite';
putenv('DB_DRIVER='.$driver);
if (getenv('DB_PREFIX') === false) {
    putenv('DB_PREFIX=cn_test_');
}
if ($driver === 'sqlite') {
    // SQLite 固定在隔离目录内；服务端数据库使用调用者显式提供的 DB_* 配置。
    touch($paths->base.'/database.sqlite');
    putenv('DB_DATABASE='.$paths->base.'/database.sqlite');
    putenv('DB_PASSWORD=');
} elseif (! getenv('DB_DATABASE')) {
    throw new RuntimeException('请通过 DB_DATABASE 显式指定独立测试数据库，不得使用真实论坛数据库。');
}

(new SetupScript())->run();

// 保留原生扩展发现和资产编译所需的隔离 Composer 清单及资源。
(new Filesystem())->copyDirectory(
    $root.'/vendor/fortawesome/font-awesome/css',
    $paths->vendor.'/fortawesome/font-awesome/css'
);
$manifest = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
$package = json_decode(file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$package['version'] = '0.0.0';
$package['install-path'] = Path::makeRelative($root, $paths->vendor.'/composer');
$manifest['packages'][] = $package;
file_put_contents($paths->vendor.'/composer/installed.json', json_encode($manifest, JSON_THROW_ON_ERROR));

$config = new Config(require $paths->base.'/config.php');
$app = (new InstalledSite($paths, $config))->bootApp();
$app->getContainer()->make(ExtensionManager::class)->enable('ffans-community-notes');

echo '集成测试环境已准备：'.$paths->base."\n";
