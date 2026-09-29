global.m = require('mithril');
const app = require('./app.cjs');
const Modal = require('../src/forum/components/CommunityNoteModerationModal').default;
const Navigation = require('../src/forum/components/CommunityNotesNavigation').default;
const { moderationRequest } = require('../src/forum/utils/noteRequest');

beforeEach(() => {
  m.redraw = jest.fn();
  app.session = { user: {} };
  app.forum = { canModerateCommunityNotes: () => true, canRateCommunityNotes: () => true, attribute: () => '/api' };
  app.cache = {
    communityNotesFeed: Object.fromEntries(
      ['helpful', 'latest', 'hidden'].map((tab) => [tab, { loaded: true, load: jest.fn() }])
    ),
  };
  app.current = { get: () => 'communityNotesPost' };
  app.store = { find: jest.fn(), pushPayload: jest.fn() };
  app.request = jest.fn();
  app.alerts = { show: jest.fn() };
  app.route = jest.fn((name) => `/${name}`);
  window.confirm = jest.fn().mockReturnValue(true);
});

function modal(hidden = false) {
  const instance = new Modal();
  instance.note = {
    id: () => '7',
    isHidden: () => hidden,
    status: () => 'helpful',
    user: () => ({ displayName: () => '附注起草者甲' }),
    reason: () => 'missing_context',
    ratingCount: () => 5,
    content: () => '附注正文',
    sources: () => [],
    hiddenReason: () => '来源违规',
    post: () => false,
    delete: jest.fn().mockResolvedValue(),
  };
  instance.attrs = { note: instance.note, onchanged: jest.fn() };
  instance.hide = jest.fn();
  return instance;
}

test('普通评价者没有已隐藏页签，管理者有入口', () => {
  const root = document.createElement('div');
  app.forum.canModerateCommunityNotes = () => false;
  m.render(root, m(Navigation, { active: 'helpful', state: {} }));
  expect(root.textContent).not.toContain('已隐藏');
  app.forum.canModerateCommunityNotes = () => true;
  m.render(root, m(Navigation, { active: 'hidden', state: {} }));
  expect(root.querySelector('a[aria-current="page"]').textContent).toBe('已隐藏');
  expect(app.route).toHaveBeenCalledWith('communityNotes', { tab: 'hidden' });
  m.render(root, []);
});

test('管理详情按需读取隐藏原因，不加载操作历史；失败可重试', async () => {
  const view = modal();
  view.note = undefined;
  app.store.find.mockRejectedValueOnce({ status: 403 });
  await view.load();
  expect(view.error).toContain('无权');
  expect(view.loading).toBe(false);
  app.store.find.mockResolvedValueOnce(view.attrs.note);
  await view.load();
  expect(view.note).toBe(view.attrs.note);
  expect(app.store.find).toHaveBeenLastCalledWith('community-notes', '7', moderationRequest);
  expect(moderationRequest['fields[community-notes]']).not.toContain('history');
});

test('隐藏要求原因，阻止重复提交，成功后更新模型并刷新列表', async () => {
  const view = modal();
  await view.onsubmit({ preventDefault() {} });
  expect(view.error).toContain('原因');
  expect(app.request).not.toHaveBeenCalled();
  view.reason = ' 来源违规 ';
  let finish;
  app.request.mockImplementation(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      })
  );
  const request = view.onsubmit({ preventDefault() {} });
  await view.onsubmit({ preventDefault() {} });
  expect(app.request).toHaveBeenCalledTimes(1);
  const hidden = modal(true).note;
  app.store.pushPayload.mockReturnValue(hidden);
  finish({ data: {} });
  await request;
  expect(app.request.mock.calls[0][0]).toMatchObject({
    method: 'POST',
    url: '/api/community-notes/7/hide',
    body: { reason: '来源违规' },
  });
  expect(view.note).toBe(hidden);
  expect(view.attrs.onchanged).toHaveBeenCalledTimes(1);
  expect(Object.values(app.cache.communityNotesFeed).every((state) => !state.loaded)).toBe(true);
});

test('已隐藏附注显示恢复和永久删除，恢复无须填写原因', async () => {
  const view = modal(true);
  const root = document.createElement('div');
  m.render(root, view.content());
  expect(root.querySelector('textarea')).toBeNull();
  const meta = root.querySelector('.CommunityNoteDetailCard-meta');
  expect([...meta.children].map((item) => item.textContent)).toEqual([
    '目前被评为有帮助',
    '已隐藏',
    '由 附注起草者甲 起草',
    '理由为缺少重要背景',
  ]);
  expect(root.querySelector('.CommunityNoteModerationModal-reason').textContent).toBe('来源违规');
  expect(meta.querySelector('[aria-label="管理社区附注"]')).toBeNull();
  expect(
    [...root.querySelectorAll('.CommunityNoteModerationModal-actions button')].map((button) => button.textContent)
  ).toEqual(['恢复', '取消', '永久删除']);
  expect(root.textContent).not.toContain('操作历史');
  app.request.mockResolvedValue({ data: {} });
  app.store.pushPayload.mockReturnValue(modal().note);
  await view.onsubmit({ preventDefault() {} });
  expect(app.request.mock.calls[0][0].url).toBe('/api/community-notes/7/restore');
  expect(view.success).toBe('附注已恢复。');
  m.render(root, []);
});

test('权限不足不能加载或发送隐藏、删除请求', async () => {
  const view = modal();
  app.forum.canModerateCommunityNotes = () => false;
  await view.load();
  view.reason = '来源违规';
  await view.onsubmit({ preventDefault() {} });
  await view.deleteNote();
  expect(app.request).not.toHaveBeenCalled();
  expect(app.store.find).not.toHaveBeenCalled();
  expect(view.note.delete).not.toHaveBeenCalled();
  expect(window.confirm).not.toHaveBeenCalled();
});

test('删除取消不请求，失败保留附注，确认成功后清除公开关系并刷新列表', async () => {
  const view = modal();
  const note = view.note;
  window.confirm.mockReturnValueOnce(false);
  await view.deleteNote();
  expect(note.delete).not.toHaveBeenCalled();
  note.delete.mockRejectedValueOnce({ status: 403 });
  await view.deleteNote();
  expect(view.error).toContain('无权');
  expect(view.attrs.onchanged).not.toHaveBeenCalled();
  expect(view.hide).not.toHaveBeenCalled();
  const post = {
    id: () => '3',
    attribute: () => 7,
    communityNote: () => note,
    pushData: jest.fn(),
    pushAttributes: jest.fn(),
  };
  note.post = () => post;
  app.store.find.mockResolvedValue(post);
  await view.deleteNote();
  expect(post.pushData).toHaveBeenCalledWith({ relationships: { communityNote: null } });
  expect(post.pushAttributes).toHaveBeenCalledWith({ myCommunityNoteId: null });
  expect(view.attrs.onchanged).toHaveBeenCalledTimes(1);
  expect(view.hide).toHaveBeenCalledTimes(1);
  expect(Object.values(app.cache.communityNotesFeed).every((state) => !state.loaded)).toBe(true);
});

test('管理弹窗保留隐藏、取消和独立删除操作，删除不受隐藏原因必填限制，取消不请求', () => {
  const view = modal();
  const root = document.createElement('div');
  m.render(root, view.content());
  const buttons = [...root.querySelectorAll('.CommunityNoteModerationModal-actions button')];
  expect(buttons.map((button) => button.textContent)).toEqual(['隐藏', '取消', '永久删除']);
  const deleteButton = buttons.find((button) => button.textContent === '永久删除');
  const cancelButton = buttons.find((button) => button.textContent === '取消');
  expect(root.querySelector('input[type="radio"]')).toBeNull();
  expect(root.querySelector('textarea').required).toBe(true);
  expect(buttons[0].type).toBe('submit');
  expect(deleteButton.type).toBe('button');
  expect(cancelButton.type).toBe('button');
  window.confirm.mockReturnValueOnce(false);
  deleteButton.click();
  expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('无法恢复'));
  expect(view.note.delete).not.toHaveBeenCalled();
  expect(app.request).not.toHaveBeenCalled();
  cancelButton.click();
  expect(view.hide).toHaveBeenCalledTimes(1);
  m.render(root, []);
});

test('隐藏失败保留原因和当前状态，允许重试', async () => {
  const view = modal();
  view.reason = '来源违规';
  app.request.mockRejectedValueOnce({ status: 403 });
  await view.onsubmit({ preventDefault() {} });
  expect(view.reason).toBe('来源违规');
  expect(view.note.isHidden()).toBe(false);
  expect(view.error).toContain('无权');
  expect(view.loading).toBe(false);
  expect(view.attrs.onchanged).not.toHaveBeenCalled();
});

test.each(['helpful', 'latest', 'hidden'])('在 %s 页签连续管理附注会刷新列表并使其他缓存失效', async (active) => {
  const view = modal();
  const cache = app.cache.communityNotesFeed;
  const feed = cache[active];
  app.current.get = () => 'communityNotes';
  const tab = jest.spyOn(m.route, 'param').mockReturnValue(active);
  view.reason = '来源违规';
  app.request.mockResolvedValue({ data: {} });
  app.store.pushPayload.mockReturnValue(modal(true).note);
  await view.onsubmit({ preventDefault() {} });
  expect(feed.load).toHaveBeenCalledTimes(1);
  await view.onsubmit({ preventDefault() {} });
  expect(feed.load).toHaveBeenCalledTimes(2);
  expect(app.cache.communityNotesFeed).toBe(cache);
  for (const name of ['helpful', 'latest', 'hidden']) {
    expect(cache[name].loaded).toBe(false);
    if (name !== active) expect(cache[name].load).not.toHaveBeenCalled();
  }
  tab.mockRestore();
});
