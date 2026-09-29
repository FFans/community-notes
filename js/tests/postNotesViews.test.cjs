global.m = require('mithril');
jest.mock('flarum/forum/components/CommentPost', () => ({
  __esModule: true,
  default: { view: () => m('div', '原帖正文') },
}));
jest.mock('../src/forum/components/CommunityNotesLayout', () => ({
  __esModule: true,
  default: { view: (vnode) => vnode.children },
}));
jest.mock('../src/forum/components/CommunityNoteRatingForm', () => ({
  __esModule: true,
  default: { view: () => m('div', '评价表单') },
}));
const app = require('./app.cjs');
const PostPage = require('../src/forum/pages/CommunityNotesPostPage').default;
const PostModal = require('../src/forum/components/CommunityNotesPostModal').default;
const NotesPage = require('../src/forum/pages/CommunityNotesPage').default;
const State = require('../src/forum/states/CommunityNotesState').default;
const Content = require('../src/forum/components/CommunityNotesPostContent').default;

const post = {
  id: () => '3',
  number: () => 3,
  attribute: (name) => (name === 'visibleCommunityNoteCount' ? 3 : undefined),
  discussion: () => ({ title: () => '原帖标题' }),
  isHidden: () => false,
  communityNote: () => false,
};
const note = (id, hidden = false) => ({
  id: () => id,
  post: () => post,
  isHidden: () => hidden,
  status: () => 'needs_more_ratings',
  user: () => null,
  reason: () => 'missing_context',
  content: () => `附注 ${id}`,
  sources: () => [],
});

beforeEach(() => {
  m.redraw = jest.fn();
  jest.spyOn(m.route, 'param').mockReturnValue('helpful');
  app.session = { user: { id: () => '1' } };
  app.cache = {};
  app.history = { push: jest.fn() };
  app.modal = { show: jest.fn() };
  app.forum = { canRateCommunityNotes: () => true, canModerateCommunityNotes: () => true, attribute: () => null };
  app.route = jest.fn((name) => `/${name}`);
  app.route.post = () => '/d/1/1';
  app.store = { find: jest.fn() };
});

afterEach(() => jest.restoreAllMocks());

test('帖子菜单弹窗与详情页展示同帖全部附注，包括隐藏附注，管理入口只在 meta', () => {
  const state = new State({ post: '3' });
  state.loaded = true;
  state.notes = [note('1'), note('2', true)];
  const page = new PostPage();
  page.state = state;
  const modal = new PostModal();
  modal.attrs = { post };
  modal.notesState = state;
  const root = document.createElement('div');
  for (const view of [page.view(), modal.content()]) {
    m.render(root, view);
    expect(root.textContent).toContain('原帖正文');
    expect(root.querySelectorAll('article[aria-label="社区附注详情"]')).toHaveLength(2);
    expect(root.querySelectorAll('.CommunityNoteDetailCard-meta button[aria-label="管理社区附注"]')).toHaveLength(2);
    expect(root.querySelector('.CommunityNoteDetailCard-footer button[aria-label="管理社区附注"]')).toBeNull();
    expect(root.textContent).toContain('已隐藏');
    m.render(root, []);
  }
});

test('已隐藏页签展示原帖和同帖多条附注，直接在 meta 管理并保留详情入口', async () => {
  m.route.param.mockReturnValue('hidden');
  const notes = [note('2', true), note('3', true)];
  app.store.find.mockResolvedValue(Object.assign(notes, { payload: { links: {} } }));
  const page = new NotesPage();
  page.selectTab();
  await Promise.resolve();
  expect(page.tab).toBe('hidden');
  expect(app.store.find.mock.calls[0][1].filter).toEqual({ moderation: 'hidden' });
  const root = document.createElement('div');
  m.render(root, page.view());
  expect(root.querySelector('select')).toBeNull();
  expect(root.textContent.match(/原帖正文/g)).toHaveLength(2);
  const controls = root.querySelectorAll('.CommunityNoteDetailCard-meta button[aria-label="管理社区附注"]');
  expect(controls).toHaveLength(2);
  controls[1].click();
  expect(app.modal.show.mock.calls[0][1].note).toBe(notes[1]);
  expect(root.querySelector('.CommunityNoteDetailCard-footer')).toBeNull();
  expect(root.textContent).toContain('查看此帖的所有附注');
  expect(app.route).toHaveBeenCalledWith('communityNotesPost', { id: '3', tab: 'hidden' });
  m.render(root, []);
});

test('普通评价者直达 hidden 页签不能加载或展示管理列表', () => {
  m.route.param.mockReturnValue('hidden');
  app.forum.canModerateCommunityNotes = () => false;
  const page = new NotesPage();
  page.selectTab();
  expect(app.store.find).not.toHaveBeenCalled();
  const root = document.createElement('div');
  m.render(root, page.view());
  expect(root.textContent).toContain('你没有管理社区附注的权限');
  expect(root.querySelector('[aria-label="刷新附注列表"]')).toBeNull();
  expect(root.querySelector('[aria-label="管理社区附注"]')).toBeNull();
  m.render(root, []);
});

test('详情返回链接保留 hidden 页签，返回列表复用内容和位置', async () => {
  m.route.param.mockReturnValue('hidden');
  app.store.find.mockResolvedValue(Object.assign([note('2', true)], { payload: { links: {} } }));
  const page = new NotesPage();
  page.selectTab();
  await Promise.resolve();
  page.state.scrollTop = 520;
  const detail = new PostPage();
  detail.state = page.state;
  const root = document.createElement('div');
  m.render(root, detail.view());
  expect(app.route).toHaveBeenCalledWith('communityNotes', { tab: 'hidden' });
  const returned = new NotesPage();
  returned.selectTab();
  expect(returned.state).toBe(page.state);
  expect(returned.state.scrollTop).toBe(520);
  expect(app.store.find).toHaveBeenCalledTimes(1);
  m.render(root, []);
});

test('帖子弹窗按 post 筛选载入全部附注，详情加载失败可以重试', async () => {
  app.store.find.mockRejectedValueOnce({ status: 403 });
  const modal = new PostModal();
  modal.oninit({ attrs: { post } });
  await Promise.resolve();
  expect(app.store.find.mock.calls[0][1].filter).toEqual({ post: '3' });
  expect(modal.notesState.error).toContain('无权');
  const root = document.createElement('div');
  m.render(root, m(Content, { state: modal.notesState, post }));
  expect(root.querySelector('[role="alert"]').textContent).toContain('无权');
  app.store.find.mockResolvedValue(Object.assign([], { payload: { links: {} } }));
  await modal.notesState.retry();
  m.render(root, m(Content, { state: modal.notesState, post }));
  expect(root.textContent).toContain('暂无可查看的附注');
  expect(root.textContent).toContain('原帖正文');
  m.render(root, []);
});
