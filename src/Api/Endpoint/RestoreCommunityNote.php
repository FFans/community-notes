<?php

namespace FFans\CommunityNotes\Api\Endpoint;

use FFans\CommunityNotes\Service\RestoreCommunityNote as RestoreService;
use Flarum\Api\Context;
use Flarum\Api\Endpoint\Endpoint;

class RestoreCommunityNote extends Endpoint
{
    protected function setUp(): void
    {
        $this->route('POST', '/{id}/restore')->authenticated()
            ->action(fn(Context $context) => $context->api->getContainer()->make(RestoreService::class)
                ->handle($context->getActor(), (int)$context->model->id));
    }
}
