global.m = require('mithril');
jest.mock('flarum/forum/components/CommentPost', () => ({
  __esModule: true,
  default: class {
    oninit() {
      this.subtree = { check: jest.fn() };
    }
    content() {
      return ['原帖正文'];
    }
  },
}));
const CommentPost = require('flarum/forum/components/CommentPost').default;
const addPublicNote = require('../src/forum/extenders/addPublicNote.ts').default;
const Model = require('flarum/common/Model').default;
const Store = require('flarum/common/Store').default;
const Note = require('../src/forum/models/CommunityNote').default;
class Post extends Model {
  communityNote = Model.hasOne('communityNote');
  isHidden() {
    return false;
  }
}
addPublicNote();
test('Store 关系刷新为 null 后移除公开卡片，保留原帖内容', () => {
  const store = new Store({ posts: Post, 'community-notes': Note });
  const post = store.pushPayload({
    data: { type: 'posts', id: '1', relationships: { communityNote: { data: { type: 'community-notes', id: '2' } } } },
    included: [
      { type: 'community-notes', id: '2', attributes: { status: 'helpful', content: '附注正文', sources: [] } },
    ],
  });
  const view = new CommentPost();
  view.attrs = { post };
  view.oninit();
  expect(view.content()).toHaveLength(2);
  expect(view.content()[0]).toBe('原帖正文');
  store.pushPayload({ data: { type: 'posts', id: '1', relationships: { communityNote: { data: null } } } });
  expect(view.content()).toEqual(['原帖正文']);
  expect(view.subtree.check).toHaveBeenCalledTimes(1);
});
