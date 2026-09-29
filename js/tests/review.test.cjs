global.m = require('mithril');
const app = require('./app.cjs');
const canRate = require('../src/forum/utils/canRateCommunityNote').default;
const Form = require('../src/forum/components/CommunityNoteRatingForm').default;
const { safeSource } = require('../src/forum/utils/noteInput');

beforeEach(() => {
  m.redraw = jest.fn();
  app.session = { user: {} };
  app.forum = { canRateCommunityNotes: () => true, attribute: () => '/api' };
});

test.each([
  'https://example.com/a b',
  'https://example.com/\\path',
  'https://example.com/\npath',
  'https://example.com/\u0000',
])('来源校验拒绝后端不接受的空白或控制字符：%s', (url) => {
  expect(safeSource(url)).toBe(false);
});

test('隐藏附注或隐藏原帖不能显示评价入口，也不能发送请求', async () => {
  const note = { id: () => '8', isMine: () => false, isHidden: () => true, post: () => false, myRating: () => null };
  app.request = jest.fn();
  expect(canRate(note)).toBe(false);
  const form = new Form();
  form.oninit({ attrs: { note } });
  form.value = 'helpful';
  form.reasons = ['clear'];
  await form.submit({ preventDefault() {} });
  expect(app.request).not.toHaveBeenCalled();
  note.isHidden = () => false;
  note.post = () => ({ isHidden: () => true, contentType: () => 'comment' });
  expect(canRate(note)).toBe(false);
  note.post = () => ({ isHidden: () => false, contentType: () => 'comment' });
  expect(canRate(note)).toBe(true);
});

test('保存评价后摘要仍显示成功反馈，并明确提供修改操作', async () => {
  let rating = null;
  const note = { id: () => '8', isMine: () => false, isHidden: () => false, post: () => false, myRating: () => rating };
  const form = new Form();
  form.oninit({ attrs: { note } });
  form.value = 'helpful';
  form.reasons = ['clear'];
  app.request = jest.fn().mockResolvedValue({ data: {} });
  app.store = {
    pushPayload: () => {
      rating = { value: 'helpful', reasons: ['clear'] };
    },
  };
  await form.submit({ preventDefault() {} });
  const root = document.createElement('div');
  m.render(root, form.view());
  expect(root.querySelector('button').getAttribute('aria-label')).toContain('修改评价');
  m.render(root, []);
});
