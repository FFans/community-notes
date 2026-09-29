const { validateNote, safeSource } = require('../src/forum/utils/noteInput');
const text = '😀'.repeat(30);
test('正文按 Unicode 字符计数，校验原因及 trim 后边界', () => {
  expect(validateNote('missing_context', ` ${text} `, ['https://example.com'])).toBeNull();
  for (const body of ['😀'.repeat(29), '中'.repeat(1001)])
    expect(validateNote('other', body, ['https://example.com'])).toBeTruthy();
  expect(validateNote('invalid', text, ['https://example.com'])).toBeTruthy();
});
test('来源数量、协议、主机和去重', () => {
  for (const url of ['javascript:alert(1)', 'data:text/html,a', 'https://', '//example.com'])
    expect(safeSource(url)).toBe(false);
  for (const sources of [
    [],
    Array(6).fill('https://example.com'),
    ['https://example.com', ' https://example.com '],
    ['https://example.com/' + 'a'.repeat(2048)],
  ]) {
    expect(validateNote('other', text, sources)).toBeTruthy();
  }
});
