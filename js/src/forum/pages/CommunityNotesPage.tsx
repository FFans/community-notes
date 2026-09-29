import type Mithril from 'mithril';

import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type { IPageAttrs } from 'flarum/common/components/Page';
import Page from 'flarum/common/components/Page';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';

import CommunityNoteDetailCard from '../components/CommunityNoteDetailCard';
import CommunityNotePostLink from '../components/CommunityNotePostLink';
import CommunityNotesLayout from '../components/CommunityNotesLayout';
import CommunityNotesNavigation from '../components/CommunityNotesNavigation';
import CommunityNotesState, { FeedTab } from '../states/CommunityNotesState';
import canModerateCommunityNotes from '../utils/canModerateCommunityNotes';
import canViewCommunityNotes from '../utils/canViewCommunityNotes';
import communityNotesTab from '../utils/communityNotesTab';

interface FeedCache {
  actorId: string;
  helpful: CommunityNotesState;
  latest: CommunityNotesState;
  hidden: CommunityNotesState;
}

export default class CommunityNotesPage extends Page<IPageAttrs, CommunityNotesState> {
  state!: CommunityNotesState;
  tab: FeedTab = 'helpful';

  oninit(vnode: Mithril.Vnode<IPageAttrs, this>) {
    super.oninit(vnode);
    this.bodyClass = 'App--communityNotes';
    this.scrollTopOnCreate = false;
    app.setTitle(app.translator.trans('ffans-community-notes.forum.navigation.title', {}, true));
    app.current.set(
      'titleControlLabel',
      app.translator.trans('ffans-community-notes.forum.navigation.title', {}, true)
    );
    this.selectTab();
  }

  selectTab() {
    this.tab = communityNotesTab();
    const actorId = app.session.user?.id() || '';
    let cache = app.cache.communityNotesFeed as FeedCache | undefined;
    if (!cache || cache.actorId !== actorId) {
      cache = {
        actorId,
        helpful: new CommunityNotesState({ feed: 'helpful' }),
        latest: new CommunityNotesState({ feed: 'latest' }),
        hidden: new CommunityNotesState({ moderation: 'hidden' }),
      };
      app.cache.communityNotesFeed = cache;
    }
    this.state = cache[this.tab];
    app.history.push('communityNotes', app.translator.trans('ffans-community-notes.forum.navigation.title', {}, true));
    const canView = this.tab === 'hidden' ? canModerateCommunityNotes() : canViewCommunityNotes();
    if (canView && !this.state.loaded && !this.state.loading) void this.state.load();
  }

  oncreate(vnode: Mithril.VnodeDOM<IPageAttrs, this>) {
    super.oncreate(vnode);
    window.scrollTo(0, this.state.scrollTop);
  }

  onbeforeremove() {
    this.state.scrollTop = window.scrollY;
  }

  view() {
    const state = this.state;
    const helpful = this.tab === 'helpful';
    const hidden = this.tab === 'hidden';
    const canView = hidden ? canModerateCommunityNotes() : canViewCommunityNotes();

    return (
      <CommunityNotesLayout feed>
        <CommunityNotesNavigation active={this.tab} state={state} />
        <header className="CommunityNotesPage-intro">
          <h2 className="sr-only">
            {hidden
              ? app.translator.trans('ffans-community-notes.forum.list.hidden_heading_a11y_label', {}, true)
              : helpful
                ? app.translator.trans('ffans-community-notes.forum.list.helpful_heading_a11y_label', {}, true)
                : app.translator.trans('ffans-community-notes.forum.list.latest_heading_a11y_label', {}, true)}
          </h2>
          <p>
            {hidden
              ? app.translator.trans('ffans-community-notes.forum.list.hidden_description', {}, true)
              : helpful
                ? app.translator.trans('ffans-community-notes.forum.list.helpful_description', {}, true)
                : app.translator.trans('ffans-community-notes.forum.list.latest_description', {}, true)}
          </p>
        </header>
        {!canView ? (
          <p className="CommunityNotesPage-message" role="status">
            {hidden
              ? app.translator.trans('ffans-community-notes.forum.list.moderation_permission_denied', {}, true)
              : app.translator.trans('ffans-community-notes.forum.list.rating_permission_denied', {}, true)}
          </p>
        ) : (
          <section
            className="CommunityNotesPage-list"
            aria-label={
              hidden
                ? app.translator.trans('ffans-community-notes.forum.list.hidden_a11y_label', {}, true)
                : helpful
                  ? app.translator.trans('ffans-community-notes.forum.list.helpful_a11y_label', {}, true)
                  : app.translator.trans('ffans-community-notes.forum.list.latest_a11y_label', {}, true)
            }
            aria-busy={state.loading}
          >
            {state.notes.map((note) => {
              const post = note.post();
              if (!post || !post.discussion()) return null;
              const otherNoteCount = Math.max(0, (post.attribute<number>('visibleCommunityNoteCount') || 0) - 1);

              return (
                <article className="CommunityNotesPage-entry" key={hidden ? note.id() : post.id()}>
                  <CommunityNotePostLink post={post} heading />
                  <CommentPost post={post} communityNotesFeed={!hidden} communityNotesDetail={hidden} />
                  {hidden && <CommunityNoteDetailCard note={note} readOnly management />}
                  <footer className="CommunityNotesPage-entryFooter">
                    <Link
                      className="CommunityNotesPage-viewNotes"
                      href={app.route('communityNotesPost', { id: post.id(), tab: this.tab })}
                    >
                      {otherNoteCount > 0 && (
                        <span className="CommunityNotesPage-otherNotes">
                          {app.translator.trans('ffans-community-notes.forum.list.other_notes', {
                            count: otherNoteCount,
                          })}
                        </span>
                      )}
                      <span className="CommunityNotesPage-viewNotesLabel">
                        <span>{app.translator.trans('ffans-community-notes.forum.list.view_all_button')}</span>
                        <Icon name="fas fa-angle-right" />
                      </span>
                    </Link>
                  </footer>
                </article>
              );
            })}
            {state.error && (
              <div className="CommunityNotesPage-message" role="alert">
                <p>{state.error}</p>
                <Button className="Button" disabled={state.loading} onclick={() => state.retry()}>
                  {app.translator.trans('ffans-community-notes.forum.list.retry_button')}
                </Button>
              </div>
            )}
            {state.loading && !state.hasNext && <LoadingIndicator display="block" />}
            {!state.loading && !state.error && !state.notes.length && (
              <p className="CommunityNotesPage-message" role="status">
                {hidden
                  ? app.translator.trans('ffans-community-notes.forum.list.hidden_empty', {}, true)
                  : helpful
                    ? app.translator.trans('ffans-community-notes.forum.list.helpful_empty', {}, true)
                    : app.translator.trans('ffans-community-notes.forum.list.latest_empty', {}, true)}
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
                  {app.translator.trans('ffans-community-notes.forum.list.load_more_button')}
                </Button>
              </div>
            )}
          </section>
        )}
      </CommunityNotesLayout>
    );
  }
}
