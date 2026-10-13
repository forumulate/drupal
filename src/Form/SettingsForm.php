<?php

namespace Drupal\forumulate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Organization, default space and caching for Forumulate.
 */
class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'forumulate_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['forumulate.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('forumulate.settings');
    $form['org'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#description' => $this->t('Your Forumulate organization slug or id.'),
      '#default_value' => $config->get('org'),
      '#required' => TRUE,
    ];
    $form['space'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default space'),
      '#description' => $this->t('Used when a block or formatter names no space of its own.'),
      '#default_value' => $config->get('space'),
      '#required' => TRUE,
    ];
    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Forumulate address'),
      '#description' => $this->t('https://forumulate.com, or the custom domain your spaces are served on.'),
      '#default_value' => $config->get('base_url'),
    ];
    $form['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API URL'),
      '#description' => $this->t('Leave empty for the address above plus /api.'),
      '#default_value' => $config->get('api_url'),
    ];
    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Embed mode'),
      '#options' => [
        'script' => $this->t('Scripts served by the Forumulate API'),
        'elements' => $this->t('Custom elements (@forumulate/embed)'),
      ],
      '#default_value' => $config->get('mode') ?: 'script',
    ];
    $form['elements_src'] = [
      '#type' => 'url',
      '#title' => $this->t('Custom elements script'),
      '#default_value' => $config->get('elements_src'),
      '#states' => ['visible' => [':input[name="mode"]' => ['value' => 'elements']]],
    ];
    $form['cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Content cache lifetime (seconds)'),
      '#min' => 0,
      '#default_value' => $config->get('cache_ttl') ?? 300,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('forumulate.settings');
    foreach (['org', 'space', 'base_url', 'api_url', 'mode', 'elements_src'] as $key) {
      $config->set($key, trim((string) $form_state->getValue($key)));
    }
    $config->set('cache_ttl', (int) $form_state->getValue('cache_ttl'))->save();
    parent::submitForm($form, $form_state);
  }

}
