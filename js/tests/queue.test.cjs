global.m = require('mithril');
jest.mock('flarum/forum/components/CommentPost', () => ({ __esModule: true, default: class {} }));
jest.mock('../src/forum/components/CommunityNotesLayout', () => ({ __esModule: true, default: class {} }));
const app = require('./app.cjs');
const Page = require('../src/forum/pages/CommunityNotesPage').default;
const canViewCommunityNotes = require('../src/forum/utils/canViewCommunityNotes').default;
const State = require('../src/forum/states/CommunityNotesState').default;
const payload = (ids, next) =>
  Object.assign(
    ids.map((id) => ({ id: () => id })),
    { payload: { links: next ? { next: '/next' } : {} } }
  );
beforeEach(() => {
  m.redraw = jest.fn();
  app.session = { user: { id: () => '1' } };
  app.forum = { canRateCommunityNotes: () => true, canModerateCommunityNotes: () => false };
  app.store = { find: jest.fn() };
  app.cache = {};
  app.history = { push: jest.fn() };
});
test('权限：匿名拒绝，仅管理权限可进入', () => {
  app.session.user = null;
  expect(canViewCommunityNotes()).toBe(false);
  app.session.user = {};
  app.forum.canRateCommunityNotes = () => false;
  app.forum.canModerateCommunityNotes = () => true;
  expect(canViewCommunityNotes()).toBe(true);
});
test('晚到响应不能覆盖刷新后的帖子流', async () => {
  const state = new State({ feed: 'latest' });
  let finish;
  app.store.find.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      })
  );
  const first = state.load();
  const latest = payload(['2']);
  app.store.find.mockResolvedValueOnce(latest);
  await state.load();
  finish(payload(['1'], true));
  await first;
  expect(state.notes).toBe(latest);
  expect(state.hasNext).toBe(false);
  expect(app.store.find.mock.calls[1][1].filter).toEqual({ feed: 'latest' });
});
test('按服务端链接继续分页，失败后重试同一偏移且保留已有内容', async () => {
  const state = new State({ post: '5' });
  app.store.find.mockResolvedValueOnce(payload(['1', '2'], true));
  await state.load();
  app.store.find.mockRejectedValueOnce(new Error('网络异常'));
  await state.load(true);
  expect(state.notes.map((note) => note.id())).toEqual(['1', '2']);
  app.store.find.mockResolvedValueOnce(payload(['3']));
  await state.load(true);
  expect(app.store.find.mock.calls[1][1].page.offset).toBe(2);
  expect(app.store.find.mock.calls[2][1].page.offset).toBe(2);
  expect(state.notes.map((note) => note.id())).toEqual(['1', '2', '3']);
  expect(state.hasNext).toBe(false);
});
test('返回列表复用页签内容和位置，切换账号不复用旧数据', () => {
  const tab = jest.spyOn(m.route, 'param').mockReturnValue('latest');
  const cached = new State({ feed: 'latest' });
  cached.loaded = true;
  cached.scrollTop = 520;
  cached.notes = payload(['1']);
  app.cache.communityNotesFeed = {
    actorId: '1',
    latest: cached,
    helpful: new State({ feed: 'helpful' }),
    hidden: new State({ moderation: 'hidden' }),
  };
  const page = new Page();
  page.selectTab();
  expect(page.state).toBe(cached);
  expect(page.state.scrollTop).toBe(520);
  expect(app.store.find).not.toHaveBeenCalled();
  app.session.user.id = () => '2';
  app.store.find.mockResolvedValue(payload([]));
  page.selectTab();
  expect(page.state).not.toBe(cached);
  expect(page.state.notes).toEqual([]);
  tab.mockRestore();
});
test('加载更多时同帖有新附注也不会重复展示原帖', async () => {
  const state = new State({ feed: 'latest' });
  const row = (id, postId) => ({ id: () => id, post: () => ({ id: () => postId }) });
  app.store.find.mockResolvedValueOnce(Object.assign([row('1', '5')], { payload: { links: { next: '/next' } } }));
  await state.load();
  app.store.find.mockResolvedValueOnce(Object.assign([row('2', '5'), row('3', '6')], { payload: { links: {} } }));
  await state.load(true);
  expect(state.notes.map((note) => note.post().id())).toEqual(['5', '6']);
});

test('刷新失败后重试第一页，不误加载下一页，保留已有内容', async () => {
  const state = new State({ post: '5' });
  app.store.find.mockResolvedValueOnce(payload(['1', '2'], true));
  await state.load();
  app.store.find.mockRejectedValueOnce(new Error('网络异常'));
  await state.load();
  expect(state.notes.map((note) => note.id())).toEqual(['1', '2']);
  app.store.find.mockResolvedValueOnce(payload(['3']));
  await state.retry();
  expect(app.store.find.mock.calls[2][1].page.offset).toBe(0);
  expect(state.notes.map((note) => note.id())).toEqual(['3']);
});
