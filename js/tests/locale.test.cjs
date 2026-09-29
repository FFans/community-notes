global.m = require('mithril');
const app = require('./app.cjs');
const Editor = require('../src/forum/components/CommunityNoteEditorModal').default;
const RatingForm = require('../src/forum/components/CommunityNoteRatingForm').default;
const PostLink = require('../src/forum/components/CommunityNotePostLink').default;
const { validateNote } = require('../src/forum/utils/noteInput');

test('模块导入后更新词条，表单选项、评价理由和校验提示在使用时读取翻译', () => {
  app.session = { user: {} };
  app.forum = { canCreateCommunityNotes: () => true, canRateCommunityNotes: () => true, attribute: () => null };
  const keys = {
    'ffans-community-notes.forum.note_reasons.other': '测试附注原因',
    'ffans-community-notes.forum.rating.values.helpful': '测试评价类型',
    'ffans-community-notes.forum.rating.reasons.helpful.clear': '测试评价理由',
    'ffans-community-notes.forum.editor.errors.reason_required': '测试校验提示',
  };
  const original = Object.fromEntries(Object.keys(keys).map((key) => [key, app.translator.trans(key, {}, true)]));
  const root = document.createElement('div');
  try {
    app.translator.addTranslations(keys);
    const editor = new Editor();
    editor.oninit({ attrs: { post: { isHidden: () => false } } });
    m.render(root, editor.content());
    expect(root.querySelector('option[value=other]').textContent).toBe('测试附注原因');
    expect(validateNote('', '', [])).toBe('测试校验提示');
    m.render(root, []);

    const form = new RatingForm();
    form.oninit({
      attrs: {
        note: { id: () => '1', isMine: () => false, isHidden: () => false, post: () => false, myRating: () => null },
      },
    });
    form.value = 'helpful';
    m.render(root, form.view());
    expect(root.textContent).toContain('测试评价类型');
    expect(root.textContent).toContain('测试评价理由');
  } finally {
    m.render(root, []);
    app.translator.addTranslations(original);
  }
});

test.each([0, 3])('原帖链接的无障碍描述插值保持纯文本，楼层为 %s', (number) => {
  app.route = { post: () => '/d/1' };
  const title = '<img src=x onerror=alert(1)>';
  const post = { number: () => number, discussion: () => ({ title: () => title }) };
  const root = document.createElement('div');
  m.render(root, m(PostLink, { post, heading: true }));
  expect(root.querySelector('img')).toBeNull();
  expect(root.querySelector('a').getAttribute('aria-label')).toBe('查看原帖：' + title + (number ? '，第 3 楼' : ''));
  if (number) expect(root.querySelector('.CommunityNotePostLink-floor').textContent).toBe('3 楼');
  m.render(root, []);
});
