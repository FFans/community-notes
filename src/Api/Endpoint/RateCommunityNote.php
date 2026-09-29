<?php

namespace FFans\CommunityNotes\Api\Endpoint;

use FFans\CommunityNotes\Service\RateCommunityNote as RateService;
use Flarum\Api\Context;
use Flarum\Api\Endpoint\Endpoint;

class RateCommunityNote extends Endpoint
{
    protected function setUp(): void
    {
        $this->route('PUT', '/{id}/rating')->authenticated()
            ->action(fn(Context $context) => $context->api->getContainer()->make(RateService::class)
                ->handle($context->getActor(), (int)$context->model->id, $context->body() ?? []));
    }
}
