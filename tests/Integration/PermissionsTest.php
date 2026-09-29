<?php

namespace FFans\CommunityNotes\Tests\Integration;

use FFans\CommunityNotes\Access\CommunityNotePermissions;
use FFans\CommunityNotes\Tests\IntegrationTestCase;
use Flarum\Api\Client;
use Flarum\Group\Group;
use Flarum\Group\PermissionCache;
use Flarum\User\Guest;
use Flarum\User\User;

class PermissionsTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        self::$container->make(PermissionCache::class)->flush();
    }

    private const FIELDS = [
        'canCreateCommunityNotes' => CommunityNotePermissions::CREATE,
        'canRateCommunityNotes' => CommunityNotePermissions::RATE,
        'canModerateCommunityNotes' => CommunityNotePermissions::MODERATE,
    ];

    public function test_guest_and_unprivileged_member_have_no_capabilities(): void
    {
        foreach ([new Guest(), User::findOrFail($this->user())] as $actor) {
            $this->assertCapabilities($actor, []);
        }
    }

    public function test_administrator_has_all_capabilities_without_explicit_grants(): void
    {
        $this->assertCapabilities(User::findOrFail(1), array_values(self::FIELDS));
    }

    public function test_each_permission_can_be_granted_and_revoked_independently(): void
    {
        $user = $this->user();
        $client = self::$container->make(Client::class)->withActor(User::findOrFail(1));
        foreach (array_values(self::FIELDS) as $permission) {
            $this->assertSame(204, $client->withBody(['permission' => $permission, 'groupIds' => [Group::MEMBER_ID]])->post('/permission')->getStatusCode());
            $this->assertCapabilities(User::findOrFail($user), [$permission]);
            $this->assertSame(204, $client->withBody(['permission' => $permission, 'groupIds' => []])->post('/permission')->getStatusCode());
            $this->assertCapabilities(User::findOrFail($user), []);
        }
    }

    public function test_guest_is_denied_even_if_guest_group_is_misconfigured(): void
    {
        foreach (array_values(self::FIELDS) as $permission) {
            $this->db->table('group_permission')->insert(['group_id' => Group::GUEST_ID, 'permission' => $permission]);
        }
        self::$container->make(PermissionCache::class)->flush();
        $this->assertCapabilities(new Guest(), []);
    }

    private function assertCapabilities(User $actor, array $allowed): void
    {
        $response = self::$container->make(Client::class)->withActor($actor)->get('/');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $data = json_decode((string) $response->getBody(), true)['data']['attributes'];
        foreach (self::FIELDS as $attribute => $permission) {
            $expected = in_array($permission, $allowed, true);
            $this->assertSame($expected, CommunityNotePermissions::allows($actor, $permission));
            $this->assertSame($expected, $data[$attribute]);
        }
    }
}
