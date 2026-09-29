import type Mithril from 'mithril';

import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';

import CommunityNote from '../models/CommunityNote';
import { requestError } from '../utils/noteInput';
import { noteRequest } from '../utils/noteRequest';
import CommunityNoteDetailCard from './CommunityNoteDetailCard';
import CommunityNotePostLink from './CommunityNotePostLink';

interface Attrs extends IInternalModalAttrs {
  note: CommunityNote;
  editing?: boolean;
}

export default class CommunityNoteRatingModal extends Modal<Attrs> {
  note?: CommunityNote;
  error = '';

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    void this.load();
  }

  className() {
    return 'CommunityNoteRatingModal Modal--large';
  }

  title() {
    return app.translator.trans('ffans-community-notes.forum.rating.title', {}, true);
  }

  async load() {
    this.loading = true;
    this.error = '';
    try {
      this.note = await app.store.find<CommunityNote>('community-notes', this.attrs.note.id()!, noteRequest);
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  content() {
    const note = this.note;
    const post = note?.post();

    return (
      <div className="Modal-body" aria-busy={this.loading}>
        {this.loading && <LoadingIndicator display="block" />}
        {this.error && (
          <div className="CommunityNoteRatingModal-message" role="alert">
            <p>{this.error}</p>
            <Button className="Button" onclick={() => this.load()}>
              {app.translator.trans('ffans-community-notes.forum.rating.retry_button')}
            </Button>
          </div>
        )}
        {note && (
          <>
            {post && post.discussion() && (
              <div className="CommunityNoteRatingModal-original">
                <CommunityNotePostLink post={post} />
                <CommentPost post={post} communityNotesDetail />
              </div>
            )}
            <CommunityNoteDetailCard note={note} editing={this.attrs.editing} />
          </>
        )}
      </div>
    );
  }
}
