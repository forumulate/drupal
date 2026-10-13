<?php

namespace Drupal\forumulate\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\forumulate\ForumulateClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows Forumulate comments for the entity the field is on.
 *
 * On a boolean field the comments show when it is checked. On a text field
 * the value is a Forumulate topic id to pin the thread to; empty means "the
 * topic for this entity's URL", created on first visit.
 *
 * @FieldFormatter(
 *   id = "forumulate_comments",
 *   label = @Translation("Forumulate comments"),
 *   field_types = {"boolean", "string"}
 * )
 */
class ForumulateCommentsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected ForumulateClient $forumulate) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('forumulate.client'),
    );
  }

  public static function defaultSettings(): array {
    return ['space' => '', 'theme' => ''] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['space'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Space'),
      '#description' => $this->t('Leave empty for the default space.'),
      '#default_value' => $this->getSetting('space'),
    ];
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => ['' => $this->t('Light'), 'dark' => $this->t('Dark')],
      '#default_value' => $this->getSetting('theme'),
    ];
    return $form;
  }

  public function settingsSummary(): array {
    return [$this->t('Space: @space', ['@space' => $this->getSetting('space') ?: $this->t('default')])];
  }

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $isBoolean = $this->fieldDefinition->getType() === 'boolean';
    $value = $items->isEmpty() ? NULL : $items->first()->value;
    if ($isBoolean && !$value) {
      return [];
    }
    $entity = $items->getEntity();
    $options = [
      'space' => $this->getSetting('space'),
      'theme' => $this->getSetting('theme'),
      'article-url' => $entity->hasLinkTemplate('canonical') && !$entity->isNew()
        ? $entity->toUrl('canonical', ['absolute' => TRUE])->toString()
        : NULL,
    ];
    if (!$isBoolean && $value) {
      $options['topic'] = (string) $value;
    }
    return [0 => $this->forumulate->embed('comments', $options)];
  }

}
