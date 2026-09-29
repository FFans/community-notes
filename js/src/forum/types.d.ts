import 'flarum/common/models/Forum';
import CommunityNote from './models/CommunityNote';
import 'flarum/common/models/Post';
import 'flarum/forum/components/Post';

declare module 'flarum/forum/components/Post' {
  export interface IPostAttrs {
    communityNotesFeed?: boolean;
    communityNotesDetail?: boolean;
  }
}

declare module 'flarum/common/models/Post' {
  export default interface Post {
    communityNote(): CommunityNote | false | undefined;
  }
}

declare module 'flarum/common/models/Forum' {
  export default interface Forum {
    canCreateCommunityNotes(): boolean;
    canRateCommunityNotes(): boolean;
    canModerateCommunityNotes(): boolean;
  }
}
