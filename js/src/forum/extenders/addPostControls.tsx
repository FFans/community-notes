import Button from 'flarum/common/components/Button';
import { extend } from 'flarum/common/extend';
import Post from 'flarum/common/models/Post';
import app from 'flarum/forum/app';
import PostControls from 'flarum/forum/utils/PostControls';

import canModerateCommunityNotes from '../utils/canModerateCommunityNotes';

export default function addPostControls(open: (post: Post) => void, manage: (post: Post) => void) {
  extend(PostControls, 'userControls', (items, post) => {
    if (!app.session.user || post.contentType() !== 'comment') return;

    const author = post.user();
    // 字段缺失时等待完整 Post 响应，不根据残缺 Store 数据猜测资格。
    const ownId = post.attribute<number | null | undefined>('myCommunityNoteId');

    if (
      app.forum.canCreateCommunityNotes() &&
      !post.isHidden() &&
      (!author || author.id() !== app.session.user.id()) &&
      ownId !== undefined
    ) {
      items.add(
        'communityNote',
        <Button icon="far fa-note-sticky" onclick={() => open(post)}>
          {ownId
            ? app.translator.trans('ffans-community-notes.forum.post_controls.view_mine_button', {}, true)
            : app.translator.trans('ffans-community-notes.forum.post_controls.add_button', {}, true)}
        </Button>
      );
    }

    // 同一原生菜单分组内紧随添加入口，管理资格不依赖创建资格或帖子作者身份。
    if (canModerateCommunityNotes() && (post.attribute<number>('communityNoteCount') || 0) > 0) {
      items.add(
        'manageCommunityNotes',
        <Button icon="fas fa-shield-halved" onclick={() => manage(post)}>
          {app.translator.trans('ffans-community-notes.forum.post_controls.manage_button')}
        </Button>,
        items.has('communityNote') ? items.getPriority('communityNote') : 0
      );
    }
  });
}
