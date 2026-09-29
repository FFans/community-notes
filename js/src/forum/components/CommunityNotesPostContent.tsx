import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Post from 'flarum/common/models/Post';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';

import CommunityNotesState from '../states/CommunityNotesState';
import canViewCommunityNotes from '../utils/canViewCommunityNotes';
import CommunityNoteDetailCard from './CommunityNoteDetailCard';
import CommunityNotePostLink from './CommunityNotePostLink';

// 详情路由与帖子菜单弹窗共用同一内容。
export default class CommunityNotesPostContent extends Component<{
  state: CommunityNotesState;
  post?: Post;
  standalone?: boolean;
}> {
  view() {
    const state = this.attrs.state;
    const post = state.notes[0]?.post() || this.attrs.post;

    if (!canViewCommunityNotes()) {
      return (
        <p className="CommunityNotesPage-message" role="status">
          {app.translator.trans('ffans-community-notes.forum.post.permission_denied')}
        </p>
      );
    }

    return (
      <section
        aria-label={app.translator.trans('ffans-community-notes.forum.post.notes_a11y_label', {}, true)}
        aria-busy={state.loading}
      >
        {post && post.discussion() && (
          <div className="CommunityNotesPage-original CommunityNotesPage-entry">
            <CommunityNotePostLink post={post} heading />
            <CommentPost post={post} communityNotesDetail />
          </div>
        )}
        {!!state.notes.length &&
          (this.attrs.standalone ? (
            <h2 className="CommunityNotesPage-sectionTitle">
              {app.translator.trans('ffans-community-notes.forum.post.notes_title')}
            </h2>
          ) : (
            <h3 className="CommunityNotesPage-sectionTitle">
              {app.translator.trans('ffans-community-notes.forum.post.notes_title')}
            </h3>
          ))}
        {state.notes.map((note) => (
          <CommunityNoteDetailCard
            key={note.id()}
            note={note}
            management
            onmoderated={() => {
              void state.load();
            }}
          />
        ))}
        {state.error && (
          <div className="CommunityNotesPage-message" role="alert">
            <p>{state.error}</p>
            <Button className="Button" disabled={state.loading} onclick={() => state.retry()}>
              {app.translator.trans('ffans-community-notes.forum.post.retry_button')}
            </Button>
          </div>
        )}
        {state.loading && !state.hasNext && <LoadingIndicator display="block" />}
        {!state.loading && !state.error && !state.notes.length && (
          <p className="CommunityNotesPage-message" role="status">
            {app.translator.trans('ffans-community-notes.forum.post.empty')}
          </p>
        )}
        {state.hasNext && !state.error && (
          <div className="CommunityNotesPage-pagination">
            <Button
              className="Button"
              loading={state.loading}
              disabled={state.loading}
              onclick={() => state.load(true)}
            >
              {app.translator.trans('ffans-community-notes.forum.post.load_more_button')}
            </Button>
          </div>
        )}
      </section>
    );
  }
}
