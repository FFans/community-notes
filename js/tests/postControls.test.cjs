global.m = require('mithril');
jest.mock('flarum/forum/utils/PostControls', () => {
  const ItemList = require('flarum/common/utils/ItemList').default;
  return {
    __esModule: true,
    default: {
      userControls: () => new ItemList().add('report', m('button', '举报'), 10).add('other', m('button', '其他')),
    },
  };
});
const app = require('./app.cjs');
const PostControls = require('flarum/forum/utils/PostControls').default;
const addPostControls = require('../src/forum/extenders/addPostControls.tsx').default;
const open = jest.fn();
const manage = jest.fn();
addPostControls(open, manage);

beforeEach(() => {
  app.session = { user: { id: () => '1' } };
  app.forum = { canCreateCommunityNotes: () => true, canModerateCommunityNotes: () => true };
  open.mockClear();
  manage.mockClear();
});

const post = (overrides = {}) => ({
  contentType: () => 'comment',
  isHidden: () => false,
  user: () => ({ id: () => '2' }),
  attribute: (name) => (name === 'communityNoteCount' ? 1 : null),
  ...overrides,
});

test('管理附注紧跟添加入口，其他菜单项保持原顺序并打开对应帖子', () => {
  const target = post();
  const controls = PostControls.userControls(target);
  expect(controls.toArray().map((item) => item.itemName)).toEqual([
    'report',
    'other',
    'communityNote',
    'manageCommunityNotes',
  ]);
  controls.get('manageCommunityNotes').attrs.onclick();
  expect(manage).toHaveBeenCalledWith(target);
  controls.get('communityNote').attrs.onclick();
  expect(open).toHaveBeenCalledWith(target);
});

test.each([
  { user: () => ({ id: () => '1' }) },
  { isHidden: () => true },
  { attribute: (name) => (name === 'communityNoteCount' ? 1 : undefined) },
])('管理员仍能管理没有添加入口的帖子：%p', (overrides) => {
  const controls = PostControls.userControls(post(overrides));
  expect(controls.has('communityNote')).toBe(false);
  expect(controls.has('manageCommunityNotes')).toBe(true);
});

test.each([0, undefined, null])('没有附注或计数未加载时不显示管理入口：%p', (count) => {
  expect(
    PostControls.userControls(post({ attribute: (name) => (name === 'communityNoteCount' ? count : null) })).has(
      'manageCommunityNotes'
    )
  ).toBe(false);
});

test('仅有管理权限仍有入口，普通作者和游客没有管理入口，事件帖不显示', () => {
  app.forum.canCreateCommunityNotes = () => false;
  expect(PostControls.userControls(post()).has('manageCommunityNotes')).toBe(true);
  app.forum.canModerateCommunityNotes = () => false;
  expect(PostControls.userControls(post()).has('manageCommunityNotes')).toBe(false);
  app.forum.canModerateCommunityNotes = () => true;
  app.session.user = null;
  expect(PostControls.userControls(post()).has('manageCommunityNotes')).toBe(false);
  app.session.user = { id: () => '1' };
  expect(PostControls.userControls(post({ contentType: () => 'discussionRenamed' })).has('manageCommunityNotes')).toBe(
    false
  );
});
