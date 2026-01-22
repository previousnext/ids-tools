<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Pinto;

use Pinto\Attribute\Definition;
use Pinto\List\ObjectListInterface;
use PreviousNext\Ds\Common\List\ListTrait;
use PreviousNext\IdsTools\Pinto\Utility\Twig;
use PreviousNext\IdsTools\Pinto\VisualRegressionContainer\VisualRegressionContainer;

enum IdsToolsList implements ObjectListInterface {

  use ListTrait;

  #[Definition(VisualRegressionContainer::class)]
  case VisualRegressionContainer;

  public function templateDirectory(): string {
    return \sprintf('@%s/%s', Twig::NAMESPACE, 'Pinto/VisualRegressionContainer');
  }

}
