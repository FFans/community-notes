import type Mithril from 'mithril';

import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import { noteReasons } from '../utils/noteInput';
import CommunityNoteStatusBadge from './CommunityNoteStatusBadge';

export default class CommunityNoteMeta extends Component<{ note: CommunityNote }> {
  view(vnode: Mithril.Vnode<{ note: CommunityNote }, this>) {
    const note = this.attrs.note;
    const noteAuthor = app.forum.canModerateCommunityNotes() && note.user();
    const post = note.post();
    const publicNote = post && post.communityNote();
    const isPublic =
      note.status() === 'helpful' &&
      !note.isHidden() &&
      post &&
      !post.isHidden() &&
      publicNote &&
      publicNote.id() === note.id();
    const authorLabel = app.forum.canModerateCommunityNotes()
      ? noteAuthor
        ? noteAuthor.displayName()
        : app.translator.trans('ffans-community-notes.forum.note.deleted_user', {}, true)
      : app.translator.trans('ffans-community-notes.forum.note.contributor', {}, true);

    return (
      <ul
        className="CommunityNoteDetailCard-meta"
        aria-label={app.translator.trans('ffans-community-notes.forum.note.meta_a11y_label', {}, true)}
      >
        <li>
          <Icon
            name={
              note.status() === 'helpful'
                ? 'far fa-smile'
                : note.status() === 'not_helpful'
                  ? 'far fa-frown'
                  : 'far fa-meh'
            }
          />
          <span>
            {note.status() !== 'needs_more_ratings' ? (
              app.translator.trans('ffans-community-notes.forum.note.rated_as', {
                status: () => <CommunityNoteStatusBadge status={note.status()} />,
              })
            ) : (
              <CommunityNoteStatusBadge status={note.status()} />
            )}
          </span>
        </li>
        <li>
          <Icon name={isPublic ? 'far fa-eye' : 'far fa-eye-slash'} />
          <span>
            {note.isHidden()
              ? app.translator.trans('ffans-community-notes.forum.note.hidden', {}, true)
              : isPublic
                ? app.translator.trans('ffans-community-notes.forum.note.public', {}, true)
                : app.translator.trans('ffans-community-notes.forum.note.not_public', {}, true)}
          </span>
        </li>
        <li>
          <Icon name="far fa-user" />
          <span>{app.translator.trans('ffans-community-notes.forum.note.drafted_by', { author: authorLabel })}</span>
        </li>
        <li>
          <Icon name="far fa-question-circle" />
          <span>
            {app.translator.trans('ffans-community-notes.forum.note.reason', {
              reason: app.translator.trans(
                noteReasons[note.reason()] || 'ffans-community-notes.forum.note_reasons.other',
                {},
                true
              ),
            })}
          </span>
        </li>

        {vnode.children}
      </ul>
    );
  }
}
