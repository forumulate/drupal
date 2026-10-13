<?php

namespace Drupal\forumulate\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\forumulate\ForumulateClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared plumbing for the Forumulate blocks: the client, and a "space" field.
 */
abstract class EmbedBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected ForumulateClient $forumulate) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('forumulate.client'));
  }

  public function defaultConfiguration(): array {
    return ['space' => ''];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['space'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Space'),
      '#description' => $this->t('Leave empty for the default space in the Forumulate settings.'),
      '#default_value' => $this->configuration['space'],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

}
