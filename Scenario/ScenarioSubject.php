<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Scenario;

/**
 * Represents the subject of the scenario being tested or rendered.
 */
final class ScenarioSubject {

  /**
   * @phpstan-param callable-object $obj
   * @phpstan-param callable-object|null $context
   */
  private function __construct(
    public object $obj,
    private ?object $context = NULL,
  ) {
  }

  /**
   * For IDS internal use only.
   *
   * @phpstan-param self|callable-object $obj
   * @internal
   */
  public static function createFromCallableObject(
    object $obj,
  ): static {
    if ($obj instanceof self) {
      return $obj;
    }

    return new static($obj);
  }

  /**
   * For IDS internal use only.
   *
   * @param callable-object $obj
   *   The subject object. This object should also be contained within the outer component.
   * @param callable-object $context
   *   The context object.
   */
  public static function createFromWiderContext(
    object $obj,
    object $context,
  ): static {
    return new static($obj, $context);
  }

  /**
   * @phpstan-return callable-object
   */
  public function renderableObject(): object {
    return $this->context ?? $this->obj;
  }

}
