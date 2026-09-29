import app from 'flarum/forum/app';

import type { RatingValue } from '../models/CommunityNote';

export const ratingLabels: Record<RatingValue, string> = {
  helpful: 'ffans-community-notes.forum.rating.values.helpful',
  somewhat_helpful: 'ffans-community-notes.forum.rating.values.somewhat_helpful',
  not_helpful: 'ffans-community-notes.forum.rating.values.not_helpful',
};
export const ratingSummaries: Record<RatingValue, string> = {
  helpful: 'ffans-community-notes.forum.rating.summaries.helpful',
  somewhat_helpful: 'ffans-community-notes.forum.rating.summaries.somewhat_helpful',
  not_helpful: 'ffans-community-notes.forum.rating.summaries.not_helpful',
};
export const ratingReasons: Record<RatingValue, Record<string, string>> = {
  helpful: {
    important_context: 'ffans-community-notes.forum.rating.reasons.helpful.important_context',
    directly_addresses_claim: 'ffans-community-notes.forum.rating.reasons.helpful.directly_addresses_claim',
    clear: 'ffans-community-notes.forum.rating.reasons.helpful.clear',
    reliable_sources: 'ffans-community-notes.forum.rating.reasons.helpful.reliable_sources',
    neutral: 'ffans-community-notes.forum.rating.reasons.helpful.neutral',
    unique_information: 'ffans-community-notes.forum.rating.reasons.helpful.unique_information',
  },
  somewhat_helpful: {
    partially_helpful: 'ffans-community-notes.forum.rating.reasons.somewhat_helpful.partially_helpful',
    needs_more_context: 'ffans-community-notes.forum.rating.reasons.somewhat_helpful.needs_more_context',
    mixed_source_quality: 'ffans-community-notes.forum.rating.reasons.somewhat_helpful.mixed_source_quality',
    wording_needs_improvement: 'ffans-community-notes.forum.rating.reasons.somewhat_helpful.wording_needs_improvement',
  },
  not_helpful: {
    incorrect: 'ffans-community-notes.forum.rating.reasons.not_helpful.incorrect',
    unreliable_or_missing_sources:
      'ffans-community-notes.forum.rating.reasons.not_helpful.unreliable_or_missing_sources',
    not_needed: 'ffans-community-notes.forum.rating.reasons.not_helpful.not_needed',
    irrelevant: 'ffans-community-notes.forum.rating.reasons.not_helpful.irrelevant',
    missing_important_context: 'ffans-community-notes.forum.rating.reasons.not_helpful.missing_important_context',
    opinion_or_speculation: 'ffans-community-notes.forum.rating.reasons.not_helpful.opinion_or_speculation',
    hostile_or_inflammatory: 'ffans-community-notes.forum.rating.reasons.not_helpful.hostile_or_inflammatory',
    unclear: 'ffans-community-notes.forum.rating.reasons.not_helpful.unclear',
    outdated: 'ffans-community-notes.forum.rating.reasons.not_helpful.outdated',
  },
};

export function validateRating(value: RatingValue | '', reasons: string[]): string | null {
  if (!value || !Object.prototype.hasOwnProperty.call(ratingReasons, value))
    return app.translator.trans('ffans-community-notes.forum.rating.errors.value_required', {}, true);
  if (reasons.length < 1 || reasons.length > 3)
    return app.translator.trans('ffans-community-notes.forum.rating.errors.reasons_count', {}, true);
  if (
    new Set(reasons).size !== reasons.length ||
    reasons.some((reason) => !Object.prototype.hasOwnProperty.call(ratingReasons[value], reason))
  )
    return app.translator.trans('ffans-community-notes.forum.rating.errors.reasons_invalid', {}, true);
  return null;
}
