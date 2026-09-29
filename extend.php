<?php

namespace FFans\CommunityNotes;

use FFans\CommunityNotes\Settings\ScoringSettings;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\PostResource;
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\Settings\Event\Saving;

return [
    // Assets
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),
    new Extend\Locales(__DIR__ . '/locale'),

    // Settings
    (new Extend\Settings())
        ->default(ScoringSettings::MIN_RATINGS, 5)
        ->default(ScoringSettings::HELPFUL_THRESHOLD, 80)
        ->default(ScoringSettings::NOT_HELPFUL_THRESHOLD, 20),

    // ServiceProvider
    (new Extend\ServiceProvider())
        ->register(Provider\ScoringServiceProvider::class),

    // Policy
    (new Extend\Policy())
        ->modelPolicy(Model\CommunityNote::class, Access\CommunityNotePolicy::class),

    // Event
    (new Extend\Event())
        ->listen(Saving::class, Listener\ValidateScoringSettings::class)
        ->subscribe(Listener\RescoreNotesWhenSettingsSaved::class),

    // Ext Model

    // Core Model
    (new Extend\Model(Post::class))
        ->relationship('communityNote', Api\PublicCommunityNoteRelation::class)
        ->hasMany('communityNotes', Model\CommunityNote::class, 'post_id'),

    // Routes
    (new Extend\Frontend('forum'))
        ->route('/community-notes', 'communityNotes')
        ->route('/community-notes/posts/{id}', 'communityNotesPost'),

    // ApiResources
    new Extend\ApiResource(Api\Resource\CommunityNoteResource::class),

    // Core ApiResources
    (new Extend\ApiResource(PostResource::class))
        ->fields(Api\CommunityNotePostFields::class)
        ->endpoint(['index', 'show', 'create', 'update'], fn($endpoint) => $endpoint->addDefaultInclude(['communityNote'])),
    (new Extend\ApiResource(DiscussionResource::class))
        ->endpoint('show', fn($endpoint) => $endpoint->addDefaultInclude(['firstPost.communityNote', 'lastPost.communityNote'])),
    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\CommunityNoteForumFields::class),

    // GDPR
];
