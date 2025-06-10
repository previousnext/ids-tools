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
  ) {
  }

  public static function create(
    mixed $inner,
  ): static {
    return static::factoryCreate($inner);
  }

  protected function build(Slots\Build $build): Slots\Build {
    return $build;
  }

}
