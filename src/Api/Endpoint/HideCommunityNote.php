<?php

namespace FFans\CommunityNotes\Api\Endpoint;

use FFans\CommunityNotes\Service\HideCommunityNote as HideService;
use Flarum\Api\Context;
use Flarum\Api\Endpoint\Endpoint;

class HideCommunityNote extends Endpoint
{
    protected function setUp(): void
    {
        $this->route('POST', '/{id}/hide')->authenticated()
            ->action(fn(Context $context) => $context->api->getContainer()->make(HideService::class)
                ->handle($context->getActor(), (int)$context->model->id, $context->body() ?? []));
    }
}
