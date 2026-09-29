global.m = require('mithril');
jest.mock('flarum/forum/components/CommentPost', () => ({ __esModule: true, default: class {} }));
const app = require('./app.cjs');
const Card = require('../src/forum/components/CommunityNotePublicCard').default;
test('公开卡片转义纯文本、阻止危险协议且不显示身份或分数', () => {
  app.session = { user: null };
  app.forum = { attribute: () => null };
  const root = document.createElement('div');
  const note = {
    content: () => '<script>alert(1)</script>\n纯文本',
    sources: () => ['https://example.com?a=1&b=2', 'javascript:alert(1)'],
    user: () => {
      throw new Error('不得读取作者');
    },
    score: () => {
      throw new Error('不得读取分数');
    },
  };
  m.render(root, m(Card, { note }));
  expect(root.querySelector('script')).toBeNull();
  expect(root.textContent).toContain('<script>alert(1)</script>');
  expect(root.querySelectorAll('a')).toHaveLength(1);
  expect(root.querySelector('a').rel).toBe('noopener noreferrer');
  expect(root.querySelector('a').target).toBe('_blank');
  expect(root.textContent).toContain('社区认为这条附注有帮助');
  expect(root.querySelector('button')).toBeNull();
  m.render(root, []);
});

test('仅按登录状态、全局权限和作者身份显示评价入口，并可编辑已有评价', () => {
  const Modal = require('../src/forum/components/CommunityNoteRatingModal').default;
  const root = document.createElement('div');
  const note = {
    content: () => '附注内容',
    sources: () => [],
    isMine: () => false,
    isHidden: () => false,
    post: () => false,
    myRating: () => null,
  };
  app.session = { user: {} };
  app.forum = { canRateCommunityNotes: () => false, attribute: () => null };
  app.modal = { show: jest.fn() };
  m.render(root, m(Card, { note }));
  expect(root.querySelector('button')).toBeNull();
  app.forum.canRateCommunityNotes = () => true;
  note.isMine = () => true;
  m.render(root, m(Card, { note }));
  expect(root.querySelector('button')).toBeNull();
  note.isMine = () => false;
  m.render(root, m(Card, { note }));
  expect(root.querySelector('button').textContent).toBe('你觉得此信息是否有帮助？');
  root.querySelector('button').click();
  expect(app.modal.show).toHaveBeenLastCalledWith(Modal, { note, editing: false });
  for (const [value, summary] of [
    ['helpful', '很有用'],
    ['somewhat_helpful', '部分有用'],
    ['not_helpful', '没有帮助'],
  ]) {
    note.myRating = () => ({ value, reasons: [] });
    m.render(root, m(Card, { note }));
    expect(root.textContent).toContain('你认为这条附注' + summary);
    expect(root.querySelector('button').textContent).toBe('你认为这条附注' + summary);
  }
  root.querySelector('button').click();
  expect(app.modal.show).toHaveBeenLastCalledWith(Modal, { note, editing: true });
  app.session.user = null;
  m.render(root, m(Card, { note }));
  expect(root.querySelector('button')).toBeNull();
  m.render(root, []);
});
