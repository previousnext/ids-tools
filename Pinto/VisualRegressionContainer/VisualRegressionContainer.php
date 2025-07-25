<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Pinto\VisualRegressionContainer;

use Pinto\Slots;
use PreviousNext\Ds\Common\Utility;

class VisualRegressionContainer implements Utility\CommonObjectInterface {

  use Utility\ObjectTrait;

  final private function __construct(
    public mixed $inner,
    public array $css,
    public array $js,
    public ?string $enum = NULL,
    public ?string $enumHref = NULL,
    public ?string $objectClass = NULL,
    public ?string $objectClassHref = NULL,
    public ?string $scenario = NULL,
    public ?string $scenarioHref = NULL,
    public ?string $subScenario = NULL,
    public ?string $previousHref = NULL,
    public ?string $nextHref = NULL,
    public ?int $viewPortWidth = NULL,
    public ?int $viewPortHeight = NULL,
  ) {
  }

  #[\Pinto\Attribute\ObjectType\Slots(bindPromotedProperties: TRUE)]
  public static function create(
    mixed $inner,
  ): static {
    return new static($inner, [], []);
  }

  protected function build(Slots\Build $build): Slots\Build {
    return $build
      ->set('enum', $this->enum)
      ->set('enumHref', $this->enumHref)
      ->set('objectClass', $this->objectClass)
      ->set('objectClassHref', $this->objectClassHref)
      ->set('scenario', $this->scenario)
      ->set('scenarioHref', $this->scenarioHref)
      ->set('subScenario', $this->subScenario)
      ->set('viewPortWidth', $this->viewPortWidth)
      ->set('viewPortHeight', $this->viewPortHeight)
      ->set('previousHref', $this->previousHref)
      ->set('nextHref', $this->nextHref);
  }

}
