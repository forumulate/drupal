<?php

namespace Drupal\forumulate\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Forumulate comments for the page being viewed (one topic per page URL).
 *
 * @Block(
 *   id = "forumulate_comments",
 *   admin_label = @Translation("Forumulate comments"),
 *   category = @Translation("Forumulate")
 * )
 */
class CommentsBlock extends EmbedBlockBase {

  public function defaultConfiguration(): array {
    return ['space' => '', 'theme' => '', 'accent' => ''];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => ['' => $this->t('Light'), 'dark' => $this->t('Dark')],
      '#default_value' => $this->configuration['theme'],
    ];
    $form['accent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accent color'),
      '#size' => 10,
      '#default_value' => $this->configuration['accent'],
    ];
    return $form;
  }

  public function build(): array {
    $build = $this->forumulate->embed('comments', [
      'space' => $this->configuration['space'],
      'theme' => $this->configuration['theme'],
      'accent' => $this->configuration['accent'],
      'article-url' => Url::fromRoute('<current>', [], ['absolute' => TRUE])->toString(),
    ]);
    $build['#cache']['contexts'][] = 'url.path';
    return $build;
  }

}
