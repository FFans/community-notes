import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';

import type { NoteStatus } from '../models/CommunityNote';

const labels: Record<NoteStatus, string> = {
  needs_more_ratings: 'ffans-community-notes.forum.status.needs_more_ratings',
  helpful: 'ffans-community-notes.forum.status.helpful',
  not_helpful: 'ffans-community-notes.forum.status.not_helpful',
};

export default class CommunityNoteStatusBadge extends Component<{ status: NoteStatus }> {
  view() {
    return (
      <span className="CommunityNoteStatusBadge" role="status">
        {app.translator.trans(labels[this.attrs.status] || 'ffans-community-notes.forum.status.unknown')}
      </span>
    );
  }
}
