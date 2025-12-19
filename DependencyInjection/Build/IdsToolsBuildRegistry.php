<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection\Build;

use Drupal\pinto\Build\BuildData;
use Drupal\pinto\Build\BuildRegistryInterface;
use Drupal\pinto\Build\BuildToken;
use Drupal\pinto\Library\DrupalLibraryBuilder;
use Drupal\pinto\Object\PintoToDrupalBuilder;
use Pinto\PintoMapping;
use Pinto\Resource\ResourceInterface;
use Pinto\Slots\Build;

/**
 * @see \Drupal\pinto\Build\BuildRegistry
 */
final class IdsToolsBuildRegistry implements BuildRegistryInterface {

  /**
   * @phpstan-param \WeakMap<\Drupal\pinto\Build\BuildToken, \Drupal\pinto\Build\BuildData> $map
   */
  public function __construct(
    private readonly PintoMapping $pintoMapping,
    // @phpstan-ignore-next-line parameter.defaultValue
    private $map = new \WeakMap(),
  ) {
  }

  public function createToken(ResourceInterface $resource, Build $built,): BuildToken {
    $token = BuildToken::createToken();
    $this->map[$token] = BuildData::createBuildData($resource, $built);
    return $token;
  }

  public function render(BuildToken $buildToken): array {
    $buildData = $this->map[$buildToken] ?? throw new \Exception('Cannot find build data for provided token.');

    $objectClassName = $buildData->resource->getClass() ?? throw new \LogicException('Missing definition for slot');
    $definition = $this->pintoMapping->getThemeDefinition($objectClassName);
    if (!$definition instanceof \Pinto\Slots\Definition) {
      throw new \LogicException('Only slots type is supported at this time.');
    }

    /** @var array<string, mixed> $twigContext */
    $twigContext = [];
    foreach ($definition->slots as $slot) {
      $slotName = $definition->renameSlots?->renamesTo($slot->name) ?? $slot->name;
      $twigContext['#' . PintoToDrupalBuilder::unitEnumToHookThemeVariableName($slotName)] = $buildData->built->pintoGet($slot->name);
    }

    // Create a fake #theme render array just for back compat snapshot reasons.
    $renderArray = [
      '#theme' => $buildData->resource->name(),
      '#attached' => ['library' => DrupalLibraryBuilder::attachLibraries($buildData->resource)],
    ] + $twigContext;

    return $renderArray;
  }

}
