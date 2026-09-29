# FFans Community Notes · 社区附注

[![许可证](https://img.shields.io/packagist/l/ffans/community-notes.svg?label=许可证)](https://raw.githubusercontent.com/FFans/community-notes/2.x/LICENSE) [![Flarum](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FFFans%2Fcommunity-notes%2F2.x%2Fcomposer.json&query=%24.require%5B%22flarum%2Fcore%22%5D&label=Flarum)](https://docs.flarum.org/2.x/) [![最新版本](https://img.shields.io/github/v/tag/FFans/community-notes?filter=v2.*&sort=semver&label=最新版本)](https://github.com/FFans/community-notes/releases) [![发布日期](https://img.shields.io/github/release-date/ffans/community-notes.svg?display_date=published_at&label=发布日期)](https://github.com/ffans/community-notes/releases/latest) [![总下载量](https://img.shields.io/packagist/dt/ffans/community-notes.svg?label=总下载量)](https://packagist.org/packages/ffans/community-notes/stats) [![月下载量](https://img.shields.io/packagist/dm/ffans/community-notes.svg?label=月下载量)](https://packagist.org/packages/ffans/community-notes/stats)

[Flarum](https://flarum.org) 扩展程序。允许社区成员为可能具有误导性的帖子补充来源和背景，经多人评价后展示在原帖下方。创意来自 Twitter 的社群附注。

## 功能

- 为首贴和回复添加附注和参考链接。
- 不可以为自己的帖子添加附注。
- 收到评价前可以随时修改附注。
- 每个帖子只展示一个最新的、且评分最高的附注。
- 一个单独的附注页面，用户评价、管理附注。

## 要求

| Flarum 版本 | 扩展版本 | 分支  |
|-------------|----------|-------|
| 2.x         | `2.x`    | `2.x` |

## 安装

通过 Composer:

```sh
composer require ffans/community-notes:"*"
php flarum cache:clear
```

## 更新

```sh
composer update ffans/community-notes
php flarum migrate
php flarum cache:clear
```

## 配置

| 设置         | Key                                           | 默认值 | 限制                      |
|--------------|-----------------------------------------------|--------|---------------------------|
| 最少评价人数 | `ffans-community-notes.min_ratings`           | 5      | 1–100                     |
| 有帮助阈值   | `ffans-community-notes.helpful_threshold`     | 80     | 0–100                     |
| 没有帮助阈值 | `ffans-community-notes.not_helpful_threshold` | 20     | 0–100，必须小于有帮助阈值 |

设置修改并保存后，会重新计算已有附注的评价。

### 评分规则

> 有帮助计 1 分、部分有帮助计 0.5 分、没有帮助计 0 分。

总评价人数 = 有帮助人数 + 部分有帮助人数 + 没有帮助人数

评分 =（有帮助人数 × 1 + 部分有帮助人数 × 0.5 + 没有帮助人数 × 0）÷ 总评价人数 × 100%

- 人数不足：标记为“需要更多评价”。
- 达到人数要求且分数百分比大于等于有帮助阈值：标记“有帮助”。
- 达到人数要求且分数百分比小于等于没有帮助阈值：标记“没有帮助”。
- 其他情况：标记为“需要更多评价”。

例如，5 人中 4 人认为有帮助、1 人认为没有帮助，分数为 80%，可公开展示。

## 权限

| 权限         | Key                                   | 能力                                                         |
|--------------|---------------------------------------|--------------------------------------------------------------|
| 创建社区附注 | `ffans-community-notes.note.create`   | 为符合条件的帖子创建附注，并编辑或删除自己尚未获得评价的附注 |
| 评价社区附注 | `ffans-community-notes.note.rate`     | 评价或修改自己对他人附注的评价                               |
| 管理社区附注 | `ffans-community-notes.note.moderate` | 管理全部可见原帖的附注，隐藏、恢复或永久删除附注             |

附注创建者仅管理员可见。

## 姊妹扩展

[创作者声明](https://github.com/FFans/creator-declarations) 允许作者在发布主题或回复时，主动说明内容来源、安全风险和商业合作等信息。

创作者声明侧重作者主动披露，社区附注侧重社区成员补充来源与背景并共同评价.

## 翻译

帮助翻译本扩展，请前往 [Weblate 平台](https://weblate.rob006.net/projects/flarum2/ffans-community-notes/)。

## 链接

- [GitHub](https://github.com/ffans/community-notes)
- [Packagist](https://packagist.org/packages/ffans/community-notes)
- [英文社区](https://discuss.flarum.org/d/39948)
- [中文社区](https://discuss.flarum.org.cn/d/16573)

## 许可证

[MIT](LICENSE.md)。
