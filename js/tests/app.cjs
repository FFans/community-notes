const fs = require('node:fs');
const path = require('node:path');
const yaml = require('js-yaml');

module.exports = { data: { resources: [] } };
window.app = module.exports;

const Translator = require('flarum/common/Translator').default;
const translations = {};
const flatten = (entries, prefix = '') => {
  for (const [key, value] of Object.entries(entries)) {
    const id = prefix ? prefix + '.' + key : key;
    if (value && typeof value === 'object') flatten(value, id);
    else if (typeof value === 'string') translations[id] = value;
  }
};
flatten(yaml.load(fs.readFileSync(path.join(__dirname, '../../locale/zh-Hans.yml'), 'utf8')));
const resolve = (id, seen = []) => {
  if (!translations[id] || seen.includes(id)) throw new Error('无效的翻译引用：' + id);
  const reference = translations[id].match(/^=>\s*(.+)$/);
  return reference ? resolve(reference[1], [...seen, id]) : translations[id];
};
for (const id of Object.keys(translations)) translations[id] = resolve(id);

module.exports.translator = new Translator();
module.exports.translator.setLocale('zh-Hans');
module.exports.translator.addTranslations(translations);
