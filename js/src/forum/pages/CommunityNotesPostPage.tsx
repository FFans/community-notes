import type Mithril from 'mithril';

import LinkButton from 'flarum/common/components/LinkButton';
import Page, { IPageAttrs } from 'flarum/common/components/Page';
import app from 'flarum/forum/app';

import CommunityNotesLayout from '../components/CommunityNotesLayout';
import CommunityNotesPostContent from '../components/CommunityNotesPostContent';
import CommunityNotesState from '../states/CommunityNotesState';
import canViewCommunityNotes from '../utils/canViewCommunityNotes';
import communityNotesTab from '../utils/communityNotesTab';

export default class CommunityNotesPostPage extends Page<IPageAttrs, CommunityNotesState> {
  state!: CommunityNotesState;

  oninit(vnode: Mithril.Vnode<IPageAttrs, this>) {
    super.oninit(vnode);
    this.bodyClass = 'App--communityNotes';
    app.setTitle(app.translator.trans('ffans-community-notes.forum.post.page_title', {}, true));
    app.current.set(
      'titleControlLabel',
      app.translator.trans('ffans-community-notes.forum.navigation.title', {}, true)
    );
    app.history.push(
      'communityNotesPost',
      app.translator.trans('ffans-community-notes.forum.post.page_title', {}, true)
    );
    this.state = new CommunityNotesState({ post: m.route.param('id') });
    if (canViewCommunityNotes()) void this.state.load();
  }

  view() {
    const state = this.state;

    return (
      <CommunityNotesLayout>
        <header className="CommunityNotesPage-detailHeader CommunityNotesPage-heading">
          <LinkButton
            className="Button Button--icon Button--link"
            icon="fas fa-arrow-left"
            href={app.route('communityNotes', { tab: communityNotesTab() })}
            aria-label={app.translator.trans('ffans-community-notes.forum.post.back_a11y_label', {}, true)}
            title={app.translator.trans('ffans-community-notes.forum.post.back_a11y_label', {}, true)}
          />
          <div>
            <h1>{app.translator.trans('ffans-community-notes.forum.post.title')}</h1>
            <p>{app.translator.trans('ffans-community-notes.forum.post.description')}</p>
          </div>
        </header>
        <CommunityNotesPostContent state={state} standalone />
      </CommunityNotesLayout>
    );
  }
}
