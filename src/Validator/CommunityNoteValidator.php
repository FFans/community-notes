<?php

namespace FFans\CommunityNotes\Validator;

use FFans\CommunityNotes\Enum\CommunityNoteReason;
use Flarum\Foundation\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityNoteValidator
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @return array{reason: string, content: string, sources: list<string>} */
    public function validate(array $input): array
    {
        $errors = [];
        $reason = $input['reason'] ?? null;
        if (!is_string($reason) || CommunityNoteReason::tryFrom($reason) === null) {
            $errors['reason'] = $this->translator->trans('ffans-community-notes.validation.note.reason_invalid');
        }

        $content = $input['content'] ?? null;
        if (is_string($content) && mb_check_encoding($content, 'UTF-8')) {
            $content = preg_replace('/^\s+|\s+$/u', '', $content);
        }
        if (!is_string($content) || !mb_check_encoding($content, 'UTF-8') || mb_strlen($content, 'UTF-8') < 30 || mb_strlen($content, 'UTF-8') > 1000) {
            $errors['content'] = $this->translator->trans('ffans-community-notes.validation.note.content_invalid');
        }

        $sources = $input['sources'] ?? null;
        $normalized = [];
        if (!is_array($sources) || !array_is_list($sources) || count($sources) < 1 || count($sources) > 5) {
            $errors['sources'] = $this->translator->trans('ffans-community-notes.validation.note.sources_count');
        } else {
            foreach ($sources as $index => $url) {
                if (!is_string($url) || !mb_check_encoding($url, 'UTF-8')) {
                    $errors['sources.' . $index] = $this->translator->trans('ffans-community-notes.validation.note.source_invalid');
                    continue;
                }
                $url = trim($url);
                $parts = parse_url($url);
                if (mb_strlen($url, 'UTF-8') > 2048 || $parts === false
                    || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                    || empty($parts['host']) || preg_match('/[\s\x00-\x1f\x7f\\\\]/u', $url)) {
                    $errors['sources.' . $index] = $this->translator->trans('ffans-community-notes.validation.note.source_url_invalid');
                } elseif (in_array($url, $normalized, true)) {
                    $errors['sources.' . $index] = $this->translator->trans('ffans-community-notes.validation.note.source_duplicate');
                }
                $normalized[] = $url;
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return ['reason' => $reason, 'content' => $content, 'sources' => $normalized];
    }
}
