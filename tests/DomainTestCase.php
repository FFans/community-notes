<?php

namespace FFans\CommunityNotes\Tests;

use Flarum\Group\PermissionCache;
use Flarum\User\User;

abstract class DomainTestCase extends IntegrationTestCase
{
    protected function contributor(array $permissions): User
    {
        $group = $this->db->table('groups')->insertGetId(['name_singular' => '贡献者', 'name_plural' => '贡献者']);
        $user = $this->user();
        $this->db->table('group_user')->insert(['group_id' => $group, 'user_id' => $user]);
        foreach ($permissions as $permission) {
            $this->db->table('group_permission')->insert(['group_id' => $group, 'permission' => $permission]);
        }
        self::$container->make(PermissionCache::class)->flush();

        return User::findOrFail($user);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$container->make(PermissionCache::class)->flush();
    }
}
