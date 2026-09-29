import type Mithril from 'mithril';

import Component from 'flarum/common/Component';
import Alert from 'flarum/common/components/Alert';
import Button from 'flarum/common/components/Button';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import app from 'flarum/forum/app';

import CommunityNote, { RatingValue } from '../models/CommunityNote';
import canRateCommunityNote from '../utils/canRateCommunityNote';
import { requestError } from '../utils/noteInput';
import { noteRequest } from '../utils/noteRequest';
import { ratingLabels, ratingReasons, validateRating } from '../utils/ratingInput';
import CommunityNoteRatingButton from './CommunityNoteRatingButton';

interface Attrs {
  note: CommunityNote;
  editing?: boolean;
  onrated?: () => void;
}

export default class CommunityNoteRatingForm extends Component<Attrs> {
  value: RatingValue | '' = '';
  reasons: string[] = [];
  loading = false;
  error = '';
  saved = false;
  editing = false;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    this.editing = !!this.attrs.editing;
    this.fillRating();
  }

  fillRating() {
    const rating = this.attrs.note.myRating();
    this.value = rating?.value || '';
    this.reasons = [...(rating?.reasons || [])];
  }

  edit() {
    this.fillRating();
    this.editing = true;
    this.error = '';
    this.saved = false;
  }

  cancel() {
    this.fillRating();
    this.editing = false;
    this.error = '';
    this.saved = false;
  }

  select(value: RatingValue) {
    if (this.value !== value) {
      this.value = value;
      this.reasons = [];
      this.saved = false;
      this.error = '';
    }
  }

  view() {
    const note = this.attrs.note;
    if (!canRateCommunityNote(note))
      return (
        <p className="CommunityNoteRatingForm-unavailable">
          {app.translator.trans('ffans-community-notes.forum.rating.unavailable')}
        </p>
      );
    const rating = note.myRating();
    if (rating && !this.editing) {
      return (
        <div className="CommunityNoteRatingSummary">
          <CommunityNoteRatingButton note={note} onclick={() => this.edit()} />
        </div>
      );
    }

    return (
      <form className="CommunityNoteRatingForm" aria-busy={this.loading} onsubmit={(e: SubmitEvent) => this.submit(e)}>
        <fieldset className="CommunityNoteRatingForm-values" disabled={this.loading}>
          <legend>{app.translator.trans('ffans-community-notes.forum.rating.question')}</legend>
          {Object.entries(ratingLabels).map(([value, label]) => (
            <label className="CommunityNoteRatingForm-option">
              <input
                type="radio"
                name={`communityNoteRating-${note.id()}`}
                value={value}
                checked={this.value === value}
                onchange={() => this.select(value as RatingValue)}
              />{' '}
              {app.translator.trans(label)}
            </label>
          ))}
        </fieldset>

        {this.value && (
          <fieldset className="CommunityNoteRatingForm-reasons" disabled={this.loading}>
            <legend>{app.translator.trans('ffans-community-notes.forum.rating.reasons_label')}</legend>
            {Object.entries(ratingReasons[this.value]).map(([reason, label]) => (
              <label className="CommunityNoteRatingForm-option">
                <input
                  type="checkbox"
                  checked={this.reasons.includes(reason)}
                  disabled={this.reasons.length >= 3 && !this.reasons.includes(reason)}
                  onchange={(e: Event) => {
                    this.reasons = (e.target as HTMLInputElement).checked
                      ? [...this.reasons, reason]
                      : this.reasons.filter((item) => item !== reason);
                    this.saved = false;
                  }}
                />{' '}
                {app.translator.trans(label)}
              </label>
            ))}
          </fieldset>
        )}

        {this.error && (
          <Alert type="error" role="alert" dismissible={false}>
            {this.error}
          </Alert>
        )}

        {this.value && (
          <div className="CommunityNoteRatingForm-actions">
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={this.loading}>
              {rating
                ? app.translator.trans('ffans-community-notes.forum.rating.update_button', {}, true)
                : app.translator.trans('ffans-community-notes.forum.rating.submit_button', {}, true)}
            </Button>
            <Button type="button" className="Button Button--link" disabled={this.loading} onclick={() => this.cancel()}>
              {app.translator.trans('ffans-community-notes.forum.rating.cancel_button')}
            </Button>
          </div>
        )}
      </form>
    );
  }

  async submit(e: SubmitEvent) {
    e.preventDefault();
    if (this.loading || !canRateCommunityNote(this.attrs.note)) return;
    this.error = validateRating(this.value, this.reasons) || '';
    if (this.error) return;
    this.loading = true;
    this.saved = false;
    try {
      const payload = await app.request<ApiPayloadSingle>({
        method: 'PUT',
        url: `${app.forum.attribute('apiUrl')}/community-notes/${this.attrs.note.id()}/rating`,
        params: { ...noteRequest, include: 'post.communityNote' },
        body: { value: this.value, reasons: this.reasons },
      });
      app.store.pushPayload(payload);
      this.saved = true;
      this.editing = false;
      this.attrs.onrated?.();
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}
