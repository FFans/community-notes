global.m = require('mithril');
jest.mock('../src/forum/components/CommunityNoteRatingForm', () => ({
  __esModule: true,
  default: { view: () => null },
}));
const app = require('./app.cjs');
const DetailCard = require('../src/forum/components/CommunityNoteDetailCard').default;

test('顶部按统一层级展示四项信息，公开状态按实际展示的附注判断', () => {
  const root = document.createElement('div');
  let publicId = '8';
  const note = {
    id: () => '7',
    status: () => 'helpful',
    isHidden: () => false,
    post: () => ({ isHidden: () => false, communityNote: () => ({ id: () => publicId }) }),
    user: () => {
      throw new Error('普通评价者不得读取起草者');
    },
    ratingCount: () => 6,
    reason: () => 'missing_context',
    content: () => '附注正文',
    sources: () => [],
  };
  app.forum = { canModerateCommunityNotes: () => false, attribute: () => null };
  app.session = { user: {} };
  app.modal = { show: jest.fn() };
  m.render(root, m(DetailCard, { note }));
  const rows = () => [...root.querySelectorAll('.CommunityNoteDetailCard-meta > li')].map((row) => row.textContent);
  expect(rows()).toEqual(['目前被评为有帮助', '未公开展示', '由 社区贡献者 起草', '理由为缺少重要背景']);
  expect(root.querySelector('article').firstElementChild.className).toBe('CommunityNoteDetailCard-meta');
  publicId = '7';
  m.render(root, m(DetailCard, { note }));
  expect(rows()[1]).toBe('已随原帖公开展示');
  note.isHidden = () => true;
  m.render(root, m(DetailCard, { note }));
  expect(rows()[1]).toBe('已隐藏');
  app.forum.canModerateCommunityNotes = () => true;
  note.user = () => ({ displayName: () => '附注起草者甲' });
  m.render(root, m(DetailCard, { note }));
  expect(rows()[2]).toBe('由 附注起草者甲 起草');
  note.user = () => null;
  m.render(root, m(DetailCard, { note }));
  expect(rows()[2]).toBe('由 已删除用户 起草');
  expect(root.querySelector('[aria-label="管理社区附注"]')).toBeNull();
  m.render(root, m(DetailCard, { note, management: true }));
  const control = root.querySelector('.CommunityNoteDetailCard-meta > .CommunityNoteDetailCard-actions button');
  expect(control.getAttribute('aria-label')).toBe('管理社区附注');
  control.click();
  expect(app.modal.show.mock.calls[0][2]).toBe(true);
  app.forum.canModerateCommunityNotes = () => false;
  m.render(root, m(DetailCard, { note, management: true }));
  expect(root.querySelector('[aria-label="管理社区附注"]')).toBeNull();
  m.render(root, []);
});
