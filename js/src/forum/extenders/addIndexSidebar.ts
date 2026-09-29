import LinkButton from 'flarum/common/components/LinkButton';
import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';

import canViewCommunityNotes from '../utils/canViewCommunityNotes';

export default function addIndexSidebar() {
  extend(IndexSidebar.prototype, 'navItems', (items) => {
    if (canViewCommunityNotes())
      items.add(
        'communityNotes',
        LinkButton.component(
          {
            href: app.route('communityNotes'),
            icon: 'far fa-note-sticky',
            active: ['communityNotes', 'communityNotesPost'].includes(app.current.get('routeName')),
          },
          app.translator.trans('ffans-community-notes.forum.navigation.title', {}, true)
        ),
        -9
      );
  });
}
