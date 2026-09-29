import type Mithril from 'mithril';

import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Post from 'flarum/common/models/Post';
import app from 'flarum/forum/app';

import CommunityNotesState from '../states/CommunityNotesState';
import canViewCommunityNotes from '../utils/canViewCommunityNotes';
import CommunityNotesPostContent from './CommunityNotesPostContent';

interface Attrs extends IInternalModalAttrs {
  post: Post;
}

export default class CommunityNotesPostModal extends Modal<Attrs> {
  notesState!: CommunityNotesState;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    this.notesState = new CommunityNotesState({ post: this.attrs.post.id()! });
    if (canViewCommunityNotes()) void this.notesState.load();
  }

  className() {
    return 'CommunityNotesPostModal Modal--large';
  }

  title() {
    return app.translator.trans('ffans-community-notes.forum.post.modal_title', {}, true);
  }

  content() {
    return (
      <div className="Modal-body CommunityNotesPage CommunityNotesPage--cards CommunityNotesPage--detail">
        <CommunityNotesPostContent state={this.notesState} post={this.attrs.post} />
      </div>
    );
  }
}
