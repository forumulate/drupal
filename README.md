# Forumulate for Drupal

A Drupal 10/11 module (machine name `forumulate`): blocks for Forumulate
comments, the docs search / ask-AI box, the "What's new" changelog bell and a
server-rendered list of your blog posts, forum topics, changelog releases or
docs pages, plus a field formatter that puts comments on any entity.

## Install

```sh
composer config repositories.forumulate vcs https://github.com/forumulate/drupal
composer require forumulate/forumulate
drush en forumulate
```

(Or copy this folder to `modules/custom/forumulate` and enable it.)

Then set your **organization** and **default space** at
*Configuration → Web services → Forumulate* (`/admin/config/services/forumulate`).

## Blocks

*Structure → Block layout → Place block*, category **Forumulate**:

- **Forumulate comments** — comments for the page being viewed (one Forumulate
  topic per page URL, created on first visit). Options: space, theme, accent.
- **Forumulate docs search** — search or ask-AI box. Options: space, mode, placeholder.
- **Forumulate changelog bell** — unread badge plus a popover of releases.
- **Forumulate content list** — the latest blog posts, forum topics, changelog
  releases or docs pages as links, rendered into the HTML (crawlable) and cached.

## Field formatter

Add a **Boolean** ("Comments enabled") or **Text (plain)** field to a content
type and choose the **Forumulate comments** formatter under *Manage display*.
A checked boolean shows comments for that node; a text value pins the thread
to that Forumulate topic id (empty = the topic for the node's URL).

## In code

```php
$forumulate = \Drupal::service('forumulate.client');
$build['comments'] = $forumulate->embed('comments', ['space' => 'blog-comments', 'theme' => 'dark']);
$posts = $forumulate->items('blog', 5);           // arrays with 'title', 'url', 'excerpt', ...
$build['body'] = $forumulate->markdownHtml('blog', $slug);
```

All markup is built from render arrays (`html_tag`, `Link`, `item_list`), so
Drupal escapes every value. `markdownHtml` escapes raw HTML (with
`league/commonmark`) and then runs `Xss::filter` — forum posts are written by
visitors. Only the public read API is used, no token; content is cached in
`cache.default` for the configured lifetime and an unreachable API yields an
empty list rather than an error.

## License

GPL-2.0-or-later, as Drupal requires for modules.

---

## About this repository

This repository is a public mirror, refreshed on each Forumulate release
(one commit per release). Pull requests are welcome; accepted changes are
applied upstream and arrive here with the next release.

- API reference: https://forumulate.com/reference
- Guides: https://forumulate.com/guides
- Community and support: https://forumulate.com/community
- Source: https://github.com/forumulate/drupal
