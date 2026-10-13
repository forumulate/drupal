<?php

namespace Drupal\forumulate\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * A server-rendered list of Forumulate blog posts, topics, releases or pages.
 *
 * Rendered into the HTML (crawlable), cached for the configured lifetime.
 *
 * @Block(
 *   id = "forumulate_items",
 *   admin_label = @Translation("Forumulate content list"),
 *   category = @Translation("Forumulate")
 * )
 */
class ItemsBlock extends EmbedBlockBase {

  public function defaultConfiguration(): array {
    return ['space' => '', 'kind' => 'blog', 'limit' => 5];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['kind'] = [
      '#type' => 'select',
      '#title' => $this->t('Content'),
      '#options' => [
        'blog' => $this->t('Blog posts'),
        'forum' => $this->t('Forum topics'),
        'changelog' => $this->t('Changelog releases'),
        'docs' => $this->t('Docs pages'),
      ],
      '#default_value' => $this->configuration['kind'],
    ];
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('How many'),
      '#min' => 1,
      '#max' => 50,
      '#default_value' => $this->configuration['limit'],
    ];
    return $form;
  }

  public function build(): array {
    $items = [];
    $rows = $this->forumulate->items($this->configuration['kind'], (int) $this->configuration['limit'], $this->configuration['space'] ?: NULL);
    foreach ($rows as $row) {
      try {
        // Link escapes the title; Url::fromUri refuses a non-URL.
        $items[] = Link::fromTextAndUrl((string) ($row['title'] ?? ''), Url::fromUri($row['url']))->toRenderable();
      }
      catch (\InvalidArgumentException $e) {
        continue;
      }
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['forumulate-items']],
      '#cache' => [
        'max-age' => $this->forumulate->settings()['ttl'],
        'tags' => ['config:forumulate.settings'],
      ],
    ];
  }

}
