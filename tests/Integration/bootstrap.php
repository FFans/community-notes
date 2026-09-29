<?php

use Flarum\Foundation\Paths;
use Symfony\Component\Filesystem\Path;

require_once __DIR__.'/../../vendor/autoload.php';

// 初始化和测试必须使用同一目录；相对路径均以扩展根目录为基准。
$root = dirname(__DIR__, 2);
$tmp = getenv('FLARUM_TEST_TMP_DIR_LOCAL') ?: getenv('FLARUM_TEST_TMP_DIR') ?: $root.'/tests/tmp/integration';
$tmp = Path::makeAbsolute($tmp, $root);

return new Paths([
    'base' => $tmp,
    'public' => $tmp.'/public',
    'storage' => $tmp.'/storage',
    'vendor' => $tmp.'/vendor',
]);
