<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Scenario;

use Pinto\List\Resource\ObjectListEnumResource;
use Pinto\PintoMapping;
use Pinto\Resource\ResourceInterface;

/**
 * An attribute referencing scenarios for generating fixtures and tests.
 *
 * These fixtures are used in:
 *   - Visual regression tests.
 *   - Unit tests for render array generation.
 *   - Generating runtime controller content in Drupal.
 *
 * Attach to a Pinto object class.
 *
 * Scenarios should be stable: they always generate the same content.
 * E.g: Do not use random text or random images.
 */
#[\Attribute(flags: \Attribute::TARGET_CLASS)]
final class Scenarios {

  /**
   * @phpstan-param class-string[] $scenarios
   */
  public function __construct(
    public array $scenarios = [],
  ) {
  }

  /**
   * @phpstan-param array<class-string<\Pinto\List\ObjectListInterface>> $filterByEnumClasses
   * @phpstan-return \Generator<\PreviousNext\IdsTools\Scenario\CompiledScenario, callable&object>
   */
  public static function findScenarios(PintoMapping $pintoMapping, array $filterByEnumClasses = []): \Generator {
    /** @var array<class-string<\Pinto\List\ObjectListInterface>, array<\Pinto\Resource\ResourceInterface>> $resourcesByEnumClass */
    $resourcesByEnumClass = [];
    foreach ($pintoMapping->getResources() as $resource) {
      // Only full components.
      if ($resource->getClass() === NULL) {
        continue;
      }

      if (!$resource instanceof ObjectListEnumResource) {
        // @todo need to fix this if we want to support Standalone components.
        continue;
      }

      $resourcesByEnumClass[$resource->pintoEnum::class][] = $resource;
    }

    foreach ($resourcesByEnumClass as $enumClass => $resources) {
      if ($filterByEnumClasses !== [] && !\in_array($enumClass, $filterByEnumClasses, TRUE)) {
        continue;
      }

      foreach ($resources as $resource) {
        $rClass = new \ReflectionClass($resource->getClass() ?? throw new \LogicException('Impossible, checked above.'));

        // Look for scenarios on the object class:
        yield from static::findScenariosOnClass($rClass, $resource, allMethods: FALSE);

        // Look for scenarios on classes referenced by #[Scenarios] above the
        // class.
        $attributes = $rClass->getAttributes(static::class);
        foreach ($attributes as $attribute) {
          $scenarios = $attribute->newInstance();
          foreach ($scenarios->scenarios as $className) {
            // When a scenarios class is used, all public static methods are
            // used regardless of whether they have a Scenario attribute.
            yield from static::findScenariosOnClass(new \ReflectionClass($className), $resource, allMethods: TRUE);
          }
        }
      }
    }
  }

  /**
   * @phpstan-return \Generator<\PreviousNext\IdsTools\Scenario\CompiledScenario, callable&object>
   */
  private static function findScenariosOnClass(\ReflectionClass $rClass, ResourceInterface $resource, bool $allMethods): \Generator {
    foreach ($rClass->getMethods(\ReflectionMethod::IS_STATIC) as $rMethod) {
      if (FALSE === $rMethod->isPublic()) {
        // Cant use bitwise filter so need this check.
        continue;
      }

      $attributes = $rMethod->getAttributes(Scenario::class);
      $scenario = ($attributes[0] ?? NULL)?->newInstance();
      if ($scenario === NULL) {
        if ($allMethods === FALSE) {
          continue;
        }
        else {
          $scenario = new Scenario();
        }
      }

      if (!$resource instanceof ObjectListEnumResource) {
        // @todo need to fix this if we want to support Standalone components.
        throw new \LogicException('Checked above.');
      }

      $case = $resource->pintoEnum;

      // Recreate the Scenario.
      $compiledScenario = new CompiledScenario(
        $scenario->id ?? $rMethod->getName(),
        $case,
        [$rClass->getName(), $rMethod->getName()],
        $scenario->viewPortWidth,
        $scenario->viewPortHeight,
      );

      /** @var \Generator<string|int, callable-object>|callable-object $result */
      $result = $compiledScenario();
      if (!$result instanceof \Generator) {
        yield $compiledScenario => $result;
        // Yield $compiledScenario->id => [$result, $compiledScenario];.
        continue;
      }

      foreach ($result as $k => $r) {
        $k = (string) $k;
        $newScenario = $compiledScenario->cloneWith(
          id: \sprintf('%s-%s', $compiledScenario->id, $k),
          yieldKey: $k,
        );
        // $k can be customised in the scenario generator by using syntax:
        // `yield 'SCENARIONAME' => $object;`
        yield $newScenario => $r;
        // Yield $compiledScenario->id => [$r, $compiledScenario];.
      }
    }
  }

}
