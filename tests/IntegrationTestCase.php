<?php

namespace FFans\CommunityNotes\Tests;

use Closure;
use Flarum\Foundation\Config;
use Flarum\Foundation\InstalledSite;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class IntegrationTestCase extends TestCase
{
    protected static ?Container $container = null;
    protected ConnectionInterface $db;

    public static function setUpBeforeClass(): void
    {
        if (self::$container !== null) {
            return;
        }

        $paths = require __DIR__.'/Integration/bootstrap.php';
        if (! is_file($paths->base.'/config.php')) {
            throw new \RuntimeException('集成测试环境尚未初始化，请先运行 composer test:setup。');
        }

        $config = new Config(require $paths->base.'/config.php');
        self::$container = (new InstalledSite($paths, $config))->bootApp()->getContainer();
    }

    protected function setUp(): void
    {
        $this->db = self::$container->make(ConnectionInterface::class);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    protected function failOnDatabaseWrite(string $operation, string $table, string $message, int $after = 0): Closure
    {
        // 通过当前驱动的表名引用处理前缀，避免触发器方言和 MySQL DDL 隐式提交。
        $statement = $operation.' '.$this->db->getQueryGrammar()->wrapTable($table).' ';
        $active = true;
        $this->db->beforeExecuting(function (string $query, array $bindings) use ($statement, $message, &$after, &$active): void {
            if (! $active || ! str_starts_with($query, $statement)) {
                return;
            }
            if ($after > 0) {
                $after--;

                return;
            }

            throw new QueryException($this->db->getName(), $query, $bindings, new RuntimeException($message));
        });

        return static function () use (&$active): void {
            $active = false;
        };
    }

    protected function user(): int
    {
        $name = 'user'.bin2hex(random_bytes(5));

        return $this->db->table('users')->insertGetId([
            'username' => $name, 'email' => $name.'@example.test', 'password' => 'test', 'is_email_confirmed' => 1,
        ]);
    }

    protected function post(): int
    {
        $discussion = $this->db->table('discussions')->insertGetId([
            'title' => '测试讨论', 'slug' => 'test', 'created_at' => '2026-09-25 12:00:00',
        ]);

        return $this->db->table('posts')->insertGetId([
            'discussion_id' => $discussion, 'number' => 1, 'type' => 'comment',
            'content' => '测试帖子', 'created_at' => '2026-09-25 12:00:00',
        ]);
    }

    protected function rating(int $note, ?int $user = null): int
    {
        return $this->db->table('ffans_community_notes_note_ratings')->insertGetId([
            'note_id' => $note, 'user_id' => $user, 'value' => 'helpful',
            'created_at' => '2026-09-25 12:00:00', 'updated_at' => '2026-09-25 12:00:00',
        ]);
    }

    protected function note(array $attributes = []): int
    {
        return $this->db->table('ffans_community_notes_notes')->insertGetId(array_merge([
            'post_id' => $this->post(), 'user_id' => $this->user(), 'reason' => 'missing_context',
            'content' => '用于数据库测试的社区附注正文。', 'status_changed_at' => '2026-09-25 12:00:00',
            'created_at' => '2026-09-25 12:00:00', 'updated_at' => '2026-09-25 12:00:00',
        ], $attributes));
    }
}
