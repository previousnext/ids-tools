<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Scenario;

/**
 * An attribute referencing scenarios for generating fixtures and tests.
 *
 * These fixtures are used in:
 *   - Visual regression tests.
 *   - Unit tests for render array generation.
 *   - Generating runtime controller content in Drupal.
 *
 * Attach above one or more public static methods on a Pinto object class or
 * methods referenced by the #[Scenarios] class.
 *
 * Scenarios should be stable: they always generate the same content.
 * E.g: Do not use random text or random images.
 *
 * A scenario method, or classes of a scenario class reference, must:
 * - Be a public static method.
 * - Return instances of the object it was attached to.
 */
#[\Attribute(flags: \Attribute::TARGET_METHOD)]
final class Scenario {

  /**
   * @phpstan-param string|null $id
   *   String used in file names and test fixtures
   *   If omitted, the method name will be used.
   *   An ID must not conflict with another Scenario for the same object.
   * @phpstan-param positive-int $viewPortWidth
   * @phpstan-param positive-int $viewPortHeight
   */
  public function __construct(
    public ?string $id = NULL,
    public ?int $viewPortWidth = NULL,
    public ?int $viewPortHeight = NULL,
  ) {
  }

}
