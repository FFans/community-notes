global.m = require('mithril');
jest.mock('flarum/forum/components/CommentPost', () => ({ __esModule: true, default: class {} }));
const app = require('./app.cjs');
const Modal = require('../src/forum/components/CommunityNoteRatingModal').default;

test('弹窗读取附注详情和原帖，失败后可重试', async () => {
  m.redraw = jest.fn();
  const modal = new Modal();
  modal.attrs = { note: { id: () => '7' } };
  app.store = { find: jest.fn().mockRejectedValue({ status: 403 }) };
  await modal.load();
  expect(modal.loading).toBe(false);
  expect(modal.error).toContain('无权');
  expect(modal.note).toBeUndefined();
  const detail = { id: () => '7' };
  app.store.find.mockResolvedValue(detail);
  await modal.load();
  expect(modal.note).toBe(detail);
  expect(modal.error).toBe('');
  expect(app.store.find).toHaveBeenLastCalledWith(
    'community-notes',
    '7',
    require('../src/forum/utils/noteRequest').noteRequest
  );
});
