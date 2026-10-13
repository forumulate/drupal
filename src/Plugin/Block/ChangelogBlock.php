<?php

namespace Drupal\forumulate\Plugin\Block;

/**
 * The Forumulate "What's new" changelog bell.
 *
 * @Block(
 *   id = "forumulate_changelog",
 *   admin_label = @Translation("Forumulate changelog bell"),
 *   category = @Translation("Forumulate")
 * )
 */
class ChangelogBlock extends EmbedBlockBase {

  public function build(): array {
    return $this->forumulate->embed('changelog', ['space' => $this->configuration['space']]);
  }

}
