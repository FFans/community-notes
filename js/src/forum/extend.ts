import Extend from 'flarum/common/extenders';
import Forum from 'flarum/common/models/Forum';
import Post from 'flarum/common/models/Post';

import commonExtend from '../common/extend';
import CommunityNote from './models/CommunityNote';
import CommunityNotesPage from './pages/CommunityNotesPage';
import CommunityNotesPostPage from './pages/CommunityNotesPostPage';

export default [
  ...commonExtend,

  new Extend.Store().add('community-notes', CommunityNote),
  new Extend.Model(Post).hasOne<CommunityNote>('communityNote'),
  new Extend.Routes()
    .add('communityNotes', '/community-notes', CommunityNotesPage)
    .add('communityNotesPost', '/community-notes/posts/:id', CommunityNotesPostPage),
  new Extend.Model(Forum)
    .attribute<boolean>('canCreateCommunityNotes')
    .attribute<boolean>('canRateCommunityNotes')
    .attribute<boolean>('canModerateCommunityNotes'),
];
