import app from 'flarum/forum/app';

export const noteReasons: Record<string, string> = {
  missing_context: 'ffans-community-notes.forum.note_reasons.missing_context',
  outdated_information: 'ffans-community-notes.forum.note_reasons.outdated_information',
  factual_error: 'ffans-community-notes.forum.note_reasons.factual_error',
  misleading: 'ffans-community-notes.forum.note_reasons.misleading',
  media_context: 'ffans-community-notes.forum.note_reasons.media_context',
  other: 'ffans-community-notes.forum.note_reasons.other',
};

export function safeSource(url: string): boolean {
  if (/[\s\u0000-\u001f\u007f\\]/u.test(url)) return false;
  try {
    const parsed = new URL(url);
    return /^https?:\/\//i.test(url) && ['http:', 'https:'].includes(parsed.protocol) && !!parsed.hostname;
  } catch {
    return false;
  }
}

export function validateNote(reason: string, content: string, sources: string[]): string | null {
  if (!Object.prototype.hasOwnProperty.call(noteReasons, reason))
    return app.translator.trans('ffans-community-notes.forum.editor.errors.reason_required', {}, true);
  const length = Array.from(content.trim()).length;
  if (length < 30 || length > 1000)
    return app.translator.trans('ffans-community-notes.forum.editor.errors.content_length', {}, true);
  if (sources.length < 1 || sources.length > 5)
    return app.translator.trans('ffans-community-notes.forum.editor.errors.sources_count', {}, true);
  const trimmed = sources.map((url) => url.trim());
  if (trimmed.some((url) => Array.from(url).length > 2048 || !safeSource(url)))
    return app.translator.trans('ffans-community-notes.forum.editor.errors.source_invalid', {}, true);
  if (new Set(trimmed).size !== trimmed.length)
    return app.translator.trans('ffans-community-notes.forum.editor.errors.source_duplicate', {}, true);
  return null;
}

export function requestError(error: unknown): string {
  const response = error as { status?: number; response?: { errors?: { detail?: string }[] } };
  if (response.status === 403 || response.status === 404)
    return app.translator.trans('ffans-community-notes.forum.errors.inaccessible', {}, true);
  return (
    response.response?.errors
      ?.map((entry) => entry.detail)
      .filter(Boolean)
      .join(app.translator.trans('ffans-community-notes.forum.errors.detail_separator', {}, true)) ||
    app.translator.trans('ffans-community-notes.forum.errors.request_failed', {}, true)
  );
}
