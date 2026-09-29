global.m = require('mithril');
const app = require('./app.cjs');
const Form = require('../src/forum/components/CommunityNoteRatingForm').default;
const { validateRating } = require('../src/forum/utils/ratingInput');
beforeEach(() => {
  m.redraw = jest.fn();
  app.session = { user: {} };
  app.forum = { canRateCommunityNotes: () => true, attribute: () => null };
});
test('理由数量和跨类型理由校验', () => {
  expect(validateRating('helpful', ['clear'])).toBeNull();
  for (const reasons of [
    [],
    ['clear', 'clear'],
    ['incorrect'],
    ['clear', 'neutral', 'unique_information', 'reliable_sources'],
  ])
    expect(validateRating('helpful', reasons)).toBeTruthy();
});
test('回填已有评价，换类型清空理由，成功合并服务端结果', async () => {
  const form = new Form();
  const note = {
    id: () => '5',
    isMine: () => false,
    isHidden: () => false,
    post: () => false,
    myRating: () => ({ value: 'helpful', reasons: ['clear'] }),
  };
  const onrated = jest.fn();
  form.oninit({ attrs: { note, onrated } });
  expect(form.reasons).toEqual(['clear']);
  expect(form.editing).toBe(false);
  form.edit();
  expect(form.editing).toBe(true);
  form.select('not_helpful');
  expect(form.reasons).toEqual([]);
  form.reasons = ['incorrect'];
  const payload = { data: { id: '5', type: 'community-notes', attributes: { status: 'not_helpful', ratingCount: 5 } } };
  app.forum = { canRateCommunityNotes: () => true, attribute: () => '/api' };
  app.request = jest.fn().mockResolvedValue(payload);
  app.store = { pushPayload: jest.fn() };
  await form.submit({ preventDefault() {} });
  expect(app.request.mock.calls[0][0]).toEqual({
    method: 'PUT',
    url: '/api/community-notes/5/rating',
    params: { ...require('../src/forum/utils/noteRequest').noteRequest, include: 'post.communityNote' },
    body: { value: 'not_helpful', reasons: ['incorrect'] },
  });
  expect(app.store.pushPayload).toHaveBeenCalledWith(payload);
  expect(onrated).toHaveBeenCalledTimes(1);
  expect(form.loading).toBe(false);
  expect(form.editing).toBe(false);
  form.edit();
  form.select('not_helpful');
  form.reasons = ['incorrect'];
  app.request.mockRejectedValue({ status: 403 });
  await form.submit({ preventDefault() {} });
  expect(form.error).toContain('无权');
  expect(form.reasons).toEqual(['incorrect']);
  expect(onrated).toHaveBeenCalledTimes(1);
});

test('未评价时仅显示单选，选择后显示理由和提交；已有评价默认折叠', () => {
  const root = document.createElement('div');
  app.session = { user: {} };
  app.forum = { canRateCommunityNotes: () => true, attribute: () => null };
  const note = { id: () => '5', isMine: () => false, isHidden: () => false, post: () => false, myRating: () => null };
  m.render(root, m(Form, { note }));
  expect(root.querySelectorAll('input[type=radio]')).toHaveLength(3);
  expect(root.querySelectorAll('input[type=checkbox]')).toHaveLength(0);
  expect(root.querySelector('button[type=submit]')).toBeNull();
  const radio = root.querySelector('input[value=helpful]');
  radio.dispatchEvent(new Event('change'));
  m.render(root, m(Form, { note }));
  expect(root.querySelectorAll('input[type=checkbox]').length).toBeGreaterThan(0);
  expect(root.querySelector('button[type=submit]').textContent).toBe('提交评价');
  note.myRating = () => ({ value: 'helpful', reasons: ['clear'] });
  m.render(root, m(Form, { note }));
  expect(root.querySelectorAll('input')).toHaveLength(0);
  expect(root.textContent).toContain('你认为这条附注很有用');
  root.querySelector('button').click();
  m.render(root, m(Form, { note }));
  expect(root.querySelector('input[value=helpful]').checked).toBe(true);
  expect(root.querySelectorAll('input[type=checkbox]:checked')).toHaveLength(1);
  expect(root.querySelector('button[type=submit]').textContent).toBe('更新评价');
  m.render(root, []);
});

test.each([
  ['首次评价', null],
  ['修改已有评价', { value: 'helpful', reasons: ['clear'] }],
])('%s 取消后收起理由，丢弃草稿且不发请求', (_label, savedRating) => {
  app.request = jest.fn();
  const onrated = jest.fn();
  const note = {
    id: () => '5',
    isMine: () => false,
    isHidden: () => false,
    post: () => false,
    myRating: () => savedRating,
  };
  const root = document.createElement('div');
  const render = () => m.render(root, m(Form, { note, onrated }));
  render();
  if (savedRating) {
    root.querySelector('button').click();
    render();
  }
  root.querySelector('input[value=not_helpful]').dispatchEvent(new Event('change'));
  render();
  root.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }));
  render();
  expect(root.querySelector('[role=alert]')).not.toBeNull();
  const cancel = [...root.querySelectorAll('button')].find((button) => button.textContent === '取消');
  expect(cancel.type).toBe('button');
  cancel.click();
  render();
  expect(root.querySelector('[role=alert]')).toBeNull();
  expect(root.querySelector('input[type=checkbox]')).toBeNull();
  expect(root.querySelector('button[type=submit]')).toBeNull();
  if (savedRating) {
    expect(root.textContent).toContain('你认为这条附注很有用');
    root.querySelector('button').click();
    render();
    expect(root.querySelector('input[value=helpful]').checked).toBe(true);
    expect(root.querySelectorAll('input[type=checkbox]:checked')).toHaveLength(1);
    expect(savedRating).toEqual({ value: 'helpful', reasons: ['clear'] });
  } else {
    expect(root.querySelectorAll('input[type=radio]')).toHaveLength(3);
    expect(root.querySelector('input:checked')).toBeNull();
  }
  expect(app.request).not.toHaveBeenCalled();
  expect(onrated).not.toHaveBeenCalled();
  m.render(root, []);
});

test.each([
  ['未登录', null, true, false],
  ['无评价权限', {}, false, false],
  ['附注作者', {}, true, true],
])('%s 时不显示评价表单，也不发送评价请求', async (_label, user, permission, isMine) => {
  app.session = { user };
  app.forum.canRateCommunityNotes = () => permission;
  app.request = jest.fn();
  const note = { id: () => '5', isMine: () => isMine, isHidden: () => false, post: () => false, myRating: () => null };
  const root = document.createElement('div');
  m.render(root, m(Form, { note }));
  expect(root.querySelector('form')).toBeNull();
  expect(root.textContent).toContain('你目前无法评价这条附注');
  const form = new Form();
  form.oninit({ attrs: { note } });
  form.value = 'helpful';
  form.reasons = ['clear'];
  await form.submit({ preventDefault() {} });
  expect(app.request).not.toHaveBeenCalled();
  m.render(root, []);
});
