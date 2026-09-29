<?php

namespace FFans\CommunityNotes\Api;

use FFans\CommunityNotes\Service;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Foundation\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoteWriteEndpoints
{
    public function __invoke(): array
    {
        return [
            Endpoint\Create::make()->authenticated()->action(function (Context $context) {
                $data = $this->data($context, true);

                return $context->api->getContainer()->make(Service\CreateCommunityNote::class)
                    ->handle($context->getActor(), (int)$data['relationships']['post']['data']['id'], $data['attributes']);
            }),
            Endpoint\Update::make()->authenticated()->action(function (Context $context) {
                $data = $this->data($context, false);

                return $context->api->getContainer()->make(Service\UpdateCommunityNote::class)
                    ->handle($context->getActor(), (int)$context->model->id, $data['attributes']);
            }),
            Endpoint\Delete::make()->authenticated()->action(function (Context $context) {
                $context->api->getContainer()->make(Service\DeleteCommunityNote::class)
                    ->handle($context->getActor(), (int)$context->model->id);
            }),
        ];
    }

    private function data(Context $context, bool $creating): array
    {
        $translator = $context->api->getContainer()->make(TranslatorInterface::class);
        $data = $context->body()['data'] ?? null;
        if (!is_array($data) || ($data['type'] ?? null) !== 'community-notes'
            || !is_array($data['attributes'] ?? null)
            || array_diff(array_keys($data['attributes']), ['reason', 'content', 'sources'])
            || (isset($data['id']) && (!is_scalar($data['id']) || (!$creating && (string)$data['id'] !== (string)$context->model->id)))
            || ($creating && isset($data['id']))) {
            throw new ValidationException(['data' => $translator->trans('ffans-community-notes.validation.data_invalid')]);
        }
        $relationships = $data['relationships'] ?? [];
        if (!is_array($relationships) || array_diff(array_keys($relationships), $creating ? ['post'] : [])) {
            throw new ValidationException(['relationships' => $translator->trans('ffans-community-notes.validation.relationships_invalid')]);
        }
        if ($creating) {
            $post = $relationships['post']['data'] ?? null;
            if (!is_array($post) || ($post['type'] ?? null) !== 'posts'
                || !is_scalar($post['id'] ?? null) || !ctype_digit((string)$post['id']) || (int)$post['id'] < 1) {
                throw new ValidationException(['post' => $translator->trans('ffans-community-notes.validation.post_invalid')]);
            }
        }

        return $data;
    }
}
