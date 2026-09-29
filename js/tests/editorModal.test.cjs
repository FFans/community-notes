global.m = require('mithril');
const app = require('./app.cjs');
const Modal = require('../src/forum/components/CommunityNoteEditorModal').default;
beforeEach(() => {
  app.alerts = { show: jest.fn() };
  app.session = { user: {} };
  app.forum = { canCreateCommunityNotes: () => true, attribute: () => null };
  app.store = { find: jest.fn().mockResolvedValue() };
  m.redraw = jest.fn();
});
function modal(note) {
  const instance = new Modal();
  instance.oninit({ attrs: { note, post: { id: () => '3', pushAttributes: jest.fn(), isHidden: () => false } } });
  instance.hide = jest.fn();
  return instance;
}
test('编辑复制来源，保存不发送 post 关系，失败保留输入', async () => {
  const sources = ['https://example.com'];
  const note = {
    reason: () => 'other',
    content: () => '字'.repeat(30),
    sources: () => sources,
    isMine: () => true,
    isHidden: () => false,
    ratingCount: () => 0,
    save: jest.fn().mockRejectedValue({ status: 403 }),
  };
  const view = modal(note);
  view.sources[0] = 'https://example.org';
  await view.onsubmit({ preventDefault() {} });
  expect(sources[0]).toBe('https://example.com');
  expect(note.save.mock.calls[0][0].relationships).toBeUndefined();
  expect(note.save.mock.calls[0][1]).toEqual({ params: { include: 'post.communityNote' } });
  expect(view.error).toContain('无权');
  expect(view.sources[0]).toBe('https://example.org');
  expect(view.loading).toBe(false);
  expect(view.hide).not.toHaveBeenCalled();
});
test.each([
  ['未登录', null, true, true, 0],
  ['无创建权限', {}, false, true, 0],
  ['不是作者', {}, true, false, 0],
  ['已获得评价', {}, true, true, 1],
  ['评价数量未加载', {}, true, true, undefined],
])('%s 时不显示编辑和删除入口，也不发送请求', async (_label, user, permission, isMine, ratingCount) => {
  app.session = { user };
  app.forum.canCreateCommunityNotes = () => permission;
  const note = {
    reason: () => 'other',
    content: () => '字'.repeat(30),
    sources: () => [],
    isMine: () => isMine,
    isHidden: () => false,
    ratingCount: () => ratingCount,
    save: jest.fn(),
    delete: jest.fn(),
  };
  const view = modal(note);
  const root = document.createElement('div');
  m.render(root, view.content());
  expect(root.querySelector('fieldset').disabled).toBe(true);
  expect(root.querySelector('.Form-controls button')).toBeNull();
  window.confirm = jest.fn();
  await view.onsubmit({ preventDefault() {} });
  await view.deleteNote();
  expect(note.save).not.toHaveBeenCalled();
  expect(note.delete).not.toHaveBeenCalled();
  expect(window.confirm).not.toHaveBeenCalled();
  m.render(root, []);
});
test('删除需要确认，取消不请求，成功才清除帖子记录', async () => {
  const note = {
    reason: () => 'other',
    content: () => '字'.repeat(30),
    sources: () => [],
    isMine: () => true,
    isHidden: () => false,
    ratingCount: () => 0,
    delete: jest.fn().mockResolvedValue(),
  };
  const view = modal(note);
  const root = document.createElement('div');
  m.render(root, view.content());
  expect(root.querySelector('fieldset').disabled).toBe(false);
  expect(root.querySelector('.Form-controls button[type="submit"]').textContent).toBe('保存修改');
  expect(root.querySelector('.Button--danger').textContent).toBe('删除附注');
  m.render(root, []);
  window.confirm = jest.fn().mockReturnValue(false);
  await view.deleteNote();
  expect(note.delete).not.toHaveBeenCalled();
  window.confirm.mockReturnValue(true);
  await view.deleteNote();
  expect(note.delete).toHaveBeenCalledTimes(1);
  expect(view.attrs.post.pushAttributes).toHaveBeenCalledWith({ myCommunityNoteId: null });
  expect(app.store.find).toHaveBeenCalledWith('posts', '3');
});

test('表单标签和说明关联到控件，来源增减后仍然有效，错误可被读屏获知', () => {
  const root = document.createElement('div');
  const view = modal();
  view.sources = ['https://example.com', 'https://example.org'];
  m.render(root, view.content());

  const select = root.querySelector('select[name="reason"]');
  expect(select.labels[0].textContent).toBe('附注原因');
  select.value = 'missing_context';
  select.dispatchEvent(new Event('change'));
  expect(view.reason).toBe('missing_context');

  for (const field of root.querySelectorAll('textarea, input[type="url"]')) {
    expect(field.labels.length > 0 || field.getAttribute('aria-label')).toBeTruthy();
    for (const id of field.getAttribute('aria-describedby').split(' ')) {
      expect(root.querySelector(`#${id}`).textContent).not.toBe('');
    }
  }

  root.querySelector('button[aria-label="移除来源 1"]').click();
  m.render(root, view.content());
  const source = root.querySelector('input[type="url"]');
  expect(source.value).toBe('https://example.org');
  expect(source.labels[0].textContent).toBe('来源（1–5 个）');
  expect(source.getAttribute('aria-label')).toBe('来源 1');
  expect(root.querySelector('button[aria-label="移除来源 1"]').disabled).toBe(true);

  view.error = '请选择附注原因。';
  m.render(root, view.content());
  expect(root.querySelector('[role="alert"]').textContent).toContain('请选择附注原因。');
  expect(root.querySelector('.Form-controls button[type="submit"]').textContent).toBe('提交附注');
  m.render(root, []);
});

test('删除被后端拒绝时显示错误并保留附注', async () => {
  const note = {
    reason: () => 'other',
    content: () => '字'.repeat(30),
    sources: () => [],
    isMine: () => true,
    isHidden: () => false,
    ratingCount: () => 0,
    delete: jest.fn().mockRejectedValue({ status: 403 }),
  };
  const view = modal(note);
  window.confirm = jest.fn().mockReturnValue(true);
  await view.deleteNote();
  expect(view.error).toContain('无权');
  expect(view.attrs.post.pushAttributes).not.toHaveBeenCalled();
  expect(app.alerts.show).not.toHaveBeenCalled();
  expect(view.hide).not.toHaveBeenCalled();
  expect(view.loading).toBe(false);
});

test('管理者查看已隐藏附注或原帖时不能编辑和删除', async () => {
  const note = {
    reason: () => 'other',
    content: () => '字'.repeat(30),
    sources: () => [],
    isMine: () => true,
    isHidden: () => true,
    ratingCount: () => 0,
    save: jest.fn(),
    delete: jest.fn(),
  };
  const view = modal(note);
  expect(view.canSave()).toBe(false);
  await view.onsubmit({ preventDefault() {} });
  await view.deleteNote();
  expect(note.save).not.toHaveBeenCalled();
  expect(note.delete).not.toHaveBeenCalled();
  note.isHidden = () => false;
  view.attrs.post.isHidden = () => true;
  expect(view.canSave()).toBe(false);
});
