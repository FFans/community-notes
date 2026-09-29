import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';

import CommunityNotePublicCard from '../components/CommunityNotePublicCard';

export default function addPublicNote() {
  extend(CommentPost.prototype, 'oninit', function () {
    this.subtree.check(() => {
      const note = this.attrs.post.communityNote();
      return note && note.freshness;
    });
  });

  extend(CommentPost.prototype, 'content', function (content) {
    if (this.attrs.communityNotesDetail) return;
    const note = this.attrs.post.communityNote();
    if (note && note.status() === 'helpful' && !note.isHidden() && !this.attrs.post.isHidden()) {
      // Core 2 的 content() 在 Comment 后返回数组，追加内容位于正文后、操作栏前。
      content.push(CommunityNotePublicCard.component({ note }));
    }
  });
}
