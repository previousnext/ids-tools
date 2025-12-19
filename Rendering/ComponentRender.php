<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Rendering;

use Drupal\pinto\Build\BuildRegistryInterface;
use Drupal\pinto\Element\PintoComponentElement;
use Pinto\PintoMapping;

/**
 * Executes an object and converts to render array recursively.
 */
final class ComponentRender {

  /**
   * @phpstan-return array<mixed>
   */
  public static function render(
    PintoMapping $pintoMapping,
    BuildRegistryInterface $buildRegistry,
    object $object,
  ) {
    // Get the current snapshot.
    $rendered = ($pintoMapping->getBuilder($object))();

    ($loop = static function (&$rendered) use (&$loop, $buildRegistry): void {
      if (FALSE === \is_array($rendered)) {
        return;
      }

      if (($rendered['#type'] ?? '') === PintoComponentElement::class) {
        $buildToken = $rendered['#buildToken'];
        $renderArrayExpanded = $buildRegistry->render($buildToken);
        unset($rendered['#type']);
        unset($rendered['#buildToken']);
        $rendered = $renderArrayExpanded + $rendered;
      }

      foreach ($rendered as &$item) {
        $loop($item);
      }
    })($rendered);

    return $rendered;
  }

  /**
   * Assumes a Drupal container was set to \Drupal::setContainer().
   *
   * @phpstan-return array<mixed>
   * @see \PreviousNext\IdsTools\DependencyInjection\IdsContainer::setupContainer()
   */
  public static function renderViaGlobal(
    object $object,
  ): array {
    $container = \Drupal::getContainer();
    $pintoMapping = $container->get(PintoMapping::class);
    $buildRegistry = $container->get(BuildRegistryInterface::class);
    return static::render($pintoMapping, $buildRegistry, $object);
  }

}
