<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Scenario;

use Pinto\List\ObjectListInterface;

/**
 * Scenario with finalized ID and enum.
 */
final class CompiledScenario {

  /**
   * @phpstan-param string $id
   *   String used in file names and test fixtures
   *   An ID must not conflict with another Scenario for the same object.
   * @phpstan-param \Pinto\List\ObjectListInterface $pintoEnum
   *   The enum case associated with this scenario. Should not be provided when
   *   defining a #[Scenario], instead this is populated automatically by
   *   internals.
   * @phpstan-param array{class-string, string}
   *   A callable to generate the scenario.
   * @phpstan-param positive-int $viewPortWidth
   * @phpstan-param positive-int $viewPortHeight
   */
  public function __construct(
    public readonly string $id,
    public readonly ObjectListInterface $pintoEnum,
    public $scenario,
    public readonly ?int $viewPortWidth = NULL,
    public readonly ?int $viewPortHeight = NULL,
    public readonly ?string $yieldKey = NULL,
  ) {
  }

  public function cloneWith(
    string $id,
    ?string $yieldKey = NULL,
  ): static {
    return new static(
      $id,
      $this->pintoEnum,
      $this->scenario,
      $this->viewPortWidth,
      $this->viewPortHeight,
      $yieldKey,
    );
  }

  public function scenarioLocation(): string {
    $rClass = new \ReflectionClass($this->scenario[0]);
    $rMethod = $rClass->getMethod($this->scenario[1]);
    return \sprintf('%s::%s', ...$this->scenario);
  }

  /**
   * @phpstan-return (callable(): (callable-object|\Generator))
   */
  public function __invoke(): mixed {
    return (($this->scenario)(...))();
  }

  /**
   * A string for debugging purposes.
   *
   * This is used in test dataprovider.
   * Not suitable for use with directory and file name use.
   */
  public function __toString(): string {
    $rClass = new \ReflectionClass($this->scenario[0]);
    $rMethod = $rClass->getMethod($this->scenario[1]);
    return \sprintf('%s::%s%s', $rClass->getName(), $rMethod->getName(), ($this->yieldKey !== NULL) ? ('=>' . $this->yieldKey) : '');
  }

}
