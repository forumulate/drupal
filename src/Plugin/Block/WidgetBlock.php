<?php

namespace Drupal\forumulate\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;

/**
 * The Forumulate docs search / ask-AI box.
 *
 * @Block(
 *   id = "forumulate_widget",
 *   admin_label = @Translation("Forumulate docs search"),
 *   category = @Translation("Forumulate")
 * )
 */
class WidgetBlock extends EmbedBlockBase {

  public function defaultConfiguration(): array {
    return ['space' => '', 'mode' => 'search', 'placeholder' => ''];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => ['search' => $this->t('Search'), 'ask' => $this->t('Ask AI')],
      '#default_value' => $this->configuration['mode'],
    ];
    $form['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->configuration['placeholder'],
    ];
    return $form;
  }

  public function build(): array {
    return $this->forumulate->embed('widget', [
      'space' => $this->configuration['space'],
      'mode' => $this->configuration['mode'],
      'placeholder' => $this->configuration['placeholder'],
    ]);
  }

}
