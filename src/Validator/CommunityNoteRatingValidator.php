<?php

namespace FFans\CommunityNotes\Validator;

use FFans\CommunityNotes\Enum\CommunityNoteRatingReason;
use FFans\CommunityNotes\Enum\CommunityNoteRatingValue;
use Flarum\Foundation\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityNoteRatingValidator
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @return array{value: CommunityNoteRatingValue, reasons: list<CommunityNoteRatingReason>} */
    public function validate(array $input): array
    {
        $errors = [];
        $value = is_string($input['value'] ?? null) ? CommunityNoteRatingValue::tryFrom($input['value']) : null;
        if ($value === null) {
            $errors['value'] = $this->translator->trans('ffans-community-notes.validation.rating.value_invalid');
        }
        $reasons = $input['reasons'] ?? null;
        $validated = [];
        if (!is_array($reasons) || !array_is_list($reasons) || count($reasons) < 1 || count($reasons) > 3) {
            $errors['reasons'] = $this->translator->trans('ffans-community-notes.validation.rating.reasons_count');
        } else {
            foreach ($reasons as $index => $reason) {
                $reason = is_string($reason) ? CommunityNoteRatingReason::tryFrom($reason) : null;
                if ($reason === null || $value === null || !in_array($reason, $value->allowedReasons(), true)) {
                    $errors['reasons.' . $index] = $this->translator->trans('ffans-community-notes.validation.rating.reason_mismatch');
                } elseif (in_array($reason, $validated, true)) {
                    $errors['reasons.' . $index] = $this->translator->trans('ffans-community-notes.validation.rating.reason_duplicate');
                } else {
                    $validated[] = $reason;
                }
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return ['value' => $value, 'reasons' => $validated];
    }
}
