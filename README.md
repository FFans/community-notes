# FFans Community Notes

[![License](https://img.shields.io/packagist/l/ffans/community-notes.svg?label=license)](https://raw.githubusercontent.com/FFans/community-notes/2.x/LICENSE) [![Flarum](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FFFans%2Fcommunity-notes%2F2.x%2Fcomposer.json&query=%24.require%5B%22flarum%2Fcore%22%5D&label=Flarum)](https://docs.flarum.org/2.x/) [![Version](https://img.shields.io/github/v/tag/FFans/community-notes?filter=v2.*&sort=semver&label=version)](https://github.com/FFans/community-notes/releases) [![Release Date](https://img.shields.io/github/release-date/ffans/community-notes.svg?display_date=published_at&label=release%20date)](https://github.com/ffans/community-notes/releases/latest) [![Total Downloads](https://img.shields.io/packagist/dt/ffans/community-notes.svg?label=downloads)](https://packagist.org/packages/ffans/community-notes/stats) [![Monthly Downloads](https://img.shields.io/packagist/dm/ffans/community-notes.svg?label=downloads)](https://packagist.org/packages/ffans/community-notes/stats)

A [Flarum](https://flarum.org) extension. Let members add context and sources to potentially misleading posts and rate
the helpfulness of community notes. Inspired by Twitter's Community Notes.

## Features

- Add notes and source links to the first posts and replies.
- Members cannot add notes to their own posts.
- Notes can be edited at any time before receiving ratings.
- Each post displays only one note: the latest, highest-scoring note.
- A dedicated notes page for rating and managing notes.

## Requirements

| Flarum Version | Extension Version | Branch |
|----------------|-------------------|--------|
| 2.x            | `2.x`             | `2.x`  |

## Installation

Install with Composer:

```sh
composer require ffans/community-notes:"*"
php flarum cache:clear
```

## Updating

```sh
composer update ffans/community-notes
php flarum migrate
php flarum cache:clear
```

## Configuration

| Setting                   | Key                                           | Default | Constraints                                        |
|---------------------------|-----------------------------------------------|---------|----------------------------------------------------|
| Minimum number of ratings | `ffans-community-notes.min_ratings`           | 5       | 1 to 100                                           |
| Helpful threshold         | `ffans-community-notes.helpful_threshold`     | 80      | 0 to 100                                           |
| Not helpful threshold     | `ffans-community-notes.not_helpful_threshold` | 20      | 0 to 100; must be lower than the helpful threshold |

Saving changes to these settings recalculates the ratings of existing notes.

### Scoring rules

> A helpful rating is worth 1 point, a somewhat helpful rating 0.5 points, and a not helpful rating 0 points.

Total ratings = helpful ratings + somewhat helpful ratings + not helpful ratings

Score = (helpful ratings × 1 + somewhat helpful ratings × 0.5 + not helpful ratings × 0) ÷ total ratings × 100%

- Not enough ratings: marked as “Needs more ratings”.
- Enough ratings and a score at or above the helpful threshold: marked as “Helpful”.
- Enough ratings and a score at or below the not helpful threshold: marked as “Not helpful”.
- Otherwise: marked as “Needs more ratings”.

For example, if 4 out of 5 members rate a note helpful and 1 rates it not helpful, its score is 80%, making it eligible
for public display.

## Permissions

| Permission             | Key                                   | Capability                                                                                    |
|------------------------|---------------------------------------|-----------------------------------------------------------------------------------------------|
| Create community notes | `ffans-community-notes.note.create`   | Create notes on posts, and edit or delete own notes before receive ratings                    |
| Rate community notes   | `ffans-community-notes.note.rate`     | Rate notes written by others or update own existing ratings                                   |
| Manage community notes | `ffans-community-notes.note.moderate` | Manage notes on all visible posts, including hiding, restoring, or permanently deleting notes |

Note authors are visible only to administrators.

## Sister extension

[Creator Declarations](https://github.com/FFans/creator-declarations) lets authors disclose information such as content
sources, safety risks, and commercial relationships when starting discussions or writing replies.

Creator Declarations focuses on disclosures by authors, while Community Notes lets community members add sources and
context and rate their helpfulness.

They provide readers with information from both authors and the community side.

## Translations

To help translate this extension, visit [Weblate](https://weblate.rob006.net/projects/flarum2/ffans-community-notes/).

## Links

- [GitHub](https://github.com/ffans/community-notes)
- [Packagist](https://packagist.org/packages/ffans/community-notes)
- [Discuss](https://discuss.flarum.org/d/39948)
- [Discuss in Chinese](https://discuss.flarum.org.cn/d/16573)

## License

[MIT](LICENSE.md).
