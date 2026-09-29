import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import canRateCommunityNote from '../utils/canRateCommunityNote';
import CommunityNoteBody from './CommunityNoteBody';
import CommunityNoteRatingButton from './CommunityNoteRatingButton';
import CommunityNoteRatingModal from './CommunityNoteRatingModal';

export default class CommunityNotePublicCard extends Component<{ note: CommunityNote }> {
  view() {
    const note = this.attrs.note;
    const canRate = canRateCommunityNote(note);
    const rating = canRate ? note.myRating() : null;

    return (
      <aside
        className="CommunityNotePublicCard"
        aria-label={app.translator.trans('ffans-community-notes.forum.public_note.a11y_label', {}, true)}
      >
        <h3 className="CommunityNotePublicCard-heading">
          <Icon name="fas fa-users"></Icon>
          {app.translator.trans('ffans-community-notes.forum.public_note.title')}
        </h3>
        <CommunityNoteBody note={note} className="CommunityNotePublicCard-body" />
        {canRate && (
          <div className="CommunityNotePublicCard-footer">
            <CommunityNoteRatingButton
              note={note}
              onclick={() => app.modal.show(CommunityNoteRatingModal, { note, editing: !!rating })}
            ></CommunityNoteRatingButton>
          </div>
        )}
      </aside>
    );
  }
}
