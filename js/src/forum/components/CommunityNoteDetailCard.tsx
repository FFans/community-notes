import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import canModerateCommunityNotes from '../utils/canModerateCommunityNotes';
import CommunityNoteBody from './CommunityNoteBody';
import CommunityNoteMeta from './CommunityNoteMeta';
import CommunityNoteModerationModal from './CommunityNoteModerationModal';
import CommunityNoteRatingForm from './CommunityNoteRatingForm';

export default class CommunityNoteDetailCard extends Component<{
  note: CommunityNote;
  editing?: boolean;
  onrated?: () => void;
  readOnly?: boolean;
  management?: boolean;
  onmoderated?: () => void;
}> {
  view() {
    const note = this.attrs.note;

    return (
      <article
        className="CommunityNoteDetailCard"
        aria-label={app.translator.trans('ffans-community-notes.forum.note.a11y_label', {}, true)}
      >
        <CommunityNoteMeta note={note}>
          {this.attrs.management && canModerateCommunityNotes() && (
            <li className="CommunityNoteDetailCard-actions">
              <Button
                className="Button Button--icon Button--link"
                icon="fas fa-ellipsis-h"
                aria-label={app.translator.trans('ffans-community-notes.forum.note.manage_a11y_label', {}, true)}
                title={app.translator.trans('ffans-community-notes.forum.note.manage_a11y_label', {}, true)}
                onclick={() =>
                  app.modal.show(CommunityNoteModerationModal, { note, onchanged: this.attrs.onmoderated }, true)
                }
              />
            </li>
          )}
        </CommunityNoteMeta>

        <CommunityNoteBody note={note} className="CommunityNoteDetailCard-body" />

        {!this.attrs.readOnly && (
          <div className="CommunityNoteDetailCard-footer">
            <CommunityNoteRatingForm note={note} editing={this.attrs.editing} onrated={this.attrs.onrated} />
          </div>
        )}
      </article>
    );
  }
}
