import type Mithril from 'mithril';

import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import PageStructure from 'flarum/forum/components/PageStructure';

export default class CommunityNotesLayout extends Component<{ feed?: boolean }> {
  view(vnode: Mithril.Vnode) {
    return (
      <PageStructure
        className={
          'CommunityNotesPage CommunityNotesPage--cards' +
          (this.attrs.feed ? ' CommunityNotesPage--feed' : ' CommunityNotesPage--detail')
        }
        sidebar={() => <IndexSidebar />}
      >
        <div className="CommunityNotesPage-stream">
          {this.attrs.feed && (
            <header className="CommunityNotesPage-heading">
              <h1>{app.translator.trans('ffans-community-notes.forum.navigation.title')}</h1>
              <p>{app.translator.trans('ffans-community-notes.forum.navigation.description')}</p>
            </header>
          )}
          {vnode.children}
        </div>
      </PageStructure>
    );
  }
}
