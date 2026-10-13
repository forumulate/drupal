<?php

namespace Drupal\forumulate;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use GuzzleHttp\ClientInterface;

/**
 * Embed render arrays plus cached reads from Forumulate's PUBLIC read API.
 *
 * No token is ever sent. A failed read (network, a private space's 404) yields
 * an empty list / NULL, cached for 30 seconds, so a page that lists posts
 * still renders when Forumulate is unreachable.
 */
class ForumulateClient {

  /**
   * Kind => [list path, list key, item path, reader prefix].
   */
  const KINDS = [
    'blog' => ['/blog', 'articles', '/blog/t/%s', 'b'],
    'forum' => ['/forum', 'topics', '/forum/t/%s', 'f'],
    'changelog' => ['/changelog', 'releases', '/changelog/t/%s', 'c'],
    'docs' => ['', NULL, '/pages/%s', 'p'],
  ];

  const EMBEDS = [
    'comments' => ['comments.js', 'forumulate-comments'],
    'widget' => ['widget.js', 'forumulate-widget'],
    'changelog' => ['changelog.js', 'forumulate-changelog'],
    'feed' => [NULL, 'forumulate-feed'],
    'search' => [NULL, 'forumulate-search'],
  ];

  public function __construct(
    protected ClientInterface $http,
    protected CacheBackendInterface $cache,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * The module settings.
   */
  public function settings(): array {
    $config = $this->configFactory->get('forumulate.settings');
    $base = rtrim((string) ($config->get('base_url') ?: 'https://forumulate.com'), '/');
    return [
      'base' => $base,
      'api' => rtrim((string) ($config->get('api_url') ?: $base . '/api'), '/'),
      'org' => (string) $config->get('org'),
      'space' => (string) $config->get('space'),
      'elements' => $config->get('mode') === 'elements',
      'elements_src' => (string) ($config->get('elements_src') ?: 'https://cdn.jsdelivr.net/npm/@forumulate/embed@1'),
      'ttl' => (int) ($config->get('cache_ttl') ?? 300),
    ];
  }

  /**
   * A render array for one embed. Attributes are escaped by Drupal's Attribute.
   *
   * @param string $kind
   *   comments, widget, changelog, feed or search.
   * @param array $options
   *   Attribute => value (org, space, theme, article-url, ...).
   */
  public function embed(string $kind, array $options = []): array {
    $s = $this->settings();
    [$script, $element] = self::EMBEDS[$kind];
    $org = ($options['org'] ?? '') ?: $s['org'];
    $space = ($options['space'] ?? '') ?: $s['space'];
    $useElements = isset($options['use-elements']) ? (bool) $options['use-elements'] : $s['elements'];
    unset($options['org'], $options['space'], $options['use-elements']);
    $cache = ['tags' => ['config:forumulate.settings']];
    if (!$org || !$space) {
      return ['#cache' => $cache];
    }
    $attrs = ['org' => $org, 'space' => $space, 'api' => $s['api']];
    foreach ($options as $name => $value) {
      $name = strtolower(str_replace('_', '-', (string) $name));
      if ($value !== NULL && $value !== '' && $value !== FALSE && preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
        $attrs[$name] = (string) $value;
      }
    }

    if ($useElements || $script === NULL) {
      return [
        'element' => ['#type' => 'html_tag', '#tag' => $element, '#value' => '', '#attributes' => $attrs],
        'script' => [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => '',
          '#attributes' => ['type' => 'module', 'src' => $s['elements_src'], 'async' => 'async'],
        ],
        '#cache' => $cache,
      ];
    }

    $data = ['src' => $s['api'] . '/embed/' . $script, 'async' => 'async'];
    foreach ($attrs as $name => $value) {
      $data['data-' . $name] = $value;
    }
    // The scripts mount into their parent node, so each gets its own box.
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['forumulate-' . $kind]],
      'script' => ['#type' => 'html_tag', '#tag' => 'script', '#value' => '', '#attributes' => $data],
      '#cache' => $cache,
    ];
  }

  /**
   * Blog posts, forum topics, changelog releases or docs pages, with 'url'.
   */
  public function items(string $kind, int $limit = 10, ?string $space = NULL): array {
    [$path, $key] = self::KINDS[$kind];
    $base = $this->spaceUrl($space);
    if ($base === NULL) {
      return [];
    }
    if ($kind === 'docs') {
      $rows = [];
      foreach ((($this->fetch($base) ?: [])['collections'] ?? []) as $collection) {
        foreach ($collection['pages'] ?? [] as $page) {
          if (empty($page['hideFromNav'])) {
            $rows[] = $page;
          }
        }
      }
      $rows = array_slice($rows, 0, $limit);
    }
    else {
      $data = $this->fetch($base . $path . '?limit=' . $limit);
      $rows = is_array($data) ? ($data[$key] ?? []) : [];
    }
    foreach ($rows as &$row) {
      $row['url'] = ($row['externalUrl'] ?? NULL) ?: $this->readerUrl($kind, $space, (string) ($row['slug'] ?? $row['id']));
    }
    return $rows;
  }

  /**
   * One piece as data, or NULL.
   */
  public function item(string $kind, string $slug, ?string $space = NULL): ?array {
    $base = $this->spaceUrl($space);
    $data = $base === NULL ? NULL : $this->fetch($base . sprintf(self::KINDS[$kind][2], rawurlencode($slug)));
    if (!is_array($data)) {
      return NULL;
    }
    $piece = $data['article'] ?? $data['topic'] ?? $data['release'] ?? $data;
    $piece['url'] = $this->readerUrl($kind, $space, (string) ($piece['slug'] ?? $slug));
    return $piece;
  }

  /**
   * One piece as Markdown text ('' when it cannot be read).
   */
  public function markdown(string $kind, string $slug, ?string $space = NULL): string {
    $base = $this->spaceUrl($space);
    $body = $base === NULL ? NULL : $this->fetch($base . sprintf(self::KINDS[$kind][2], rawurlencode($slug)) . '.md', FALSE);
    return is_string($body) ? $body : '';
  }

  /**
   * Markdown rendered to filtered HTML.
   *
   * Forum posts are written by visitors, so raw HTML is escaped (CommonMark)
   * and the result still goes through Xss::filter, which drops script, event
   * attributes and javascript: URLs.
   */
  public function markdownHtml(string $kind, string $slug, ?string $space = NULL): array {
    $text = $this->markdown($kind, $slug, $space);
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $converter = new \League\CommonMark\CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => FALSE]);
      $html = (string) $converter->convert($text);
    }
    else {
      $html = '';
      foreach (preg_split('/\n\s*\n/', $text) as $para) {
        if (trim($para) !== '') {
          $html .= '<p>' . nl2br(Html::escape(trim($para))) . '</p>';
        }
      }
    }
    $allowed = ['a', 'em', 'strong', 'code', 'pre', 'p', 'br', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'del'];
    return [
      '#markup' => Markup::create(Xss::filter($html, $allowed)),
      '#cache' => ['max-age' => $this->settings()['ttl'], 'tags' => ['config:forumulate.settings']],
    ];
  }

  protected function spaceUrl(?string $space): ?string {
    $s = $this->settings();
    $space = $space ?: $s['space'];
    if (!$s['org'] || !$space) {
      return NULL;
    }
    return $s['api'] . '/public/orgs/' . rawurlencode($s['org']) . '/spaces/' . rawurlencode($space);
  }

  protected function readerUrl(string $kind, ?string $space, string $slug): string {
    $s = $this->settings();
    return sprintf('%s/%s/%s/%s/%s', $s['base'], self::KINDS[$kind][3], rawurlencode($s['org']), rawurlencode($space ?: $s['space']), rawurlencode($slug));
  }

  /**
   * GET a URL through the cache. Returns decoded JSON / text, or FALSE.
   */
  protected function fetch(string $url, bool $json = TRUE): mixed {
    $cid = 'forumulate:' . hash('sha256', $url);
    if ($hit = $this->cache->get($cid)) {
      return $hit->data;
    }
    $value = FALSE;
    $ttl = 30;
    try {
      $res = $this->http->request('GET', $url, [
        'timeout' => 5,
        'http_errors' => FALSE,
        'headers' => ['Accept' => $json ? 'application/json' : 'text/markdown', 'User-Agent' => 'forumulate-drupal'],
      ]);
      if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
        $body = (string) $res->getBody();
        $value = $json ? (json_decode($body, TRUE) ?? FALSE) : $body;
        $ttl = $this->settings()['ttl'];
      }
      else {
        $this->loggerFactory->get('forumulate')->warning('@url answered @code', ['@url' => $url, '@code' => $res->getStatusCode()]);
      }
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('forumulate')->warning('@url: @message', ['@url' => $url, '@message' => $e->getMessage()]);
    }
    $this->cache->set($cid, $value, time() + $ttl, ['config:forumulate.settings']);
    return $value;
  }

}
