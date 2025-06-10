<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Pinto;

use Pinto\Attribute\Definition;
use Pinto\Attribute\ObjectType;
use Pinto\List\ObjectListInterface;
use PreviousNext\Ds\Common\List\ListTrait;
use PreviousNext\Ds\Common\Utility\Twig;
use PreviousNext\IdsTools\Pinto\VisualRegressionContainer\VisualRegressionContainer;

#[ObjectType\Slots(method: 'create', bindPromotedProperties: TRUE)]
enum IdsToolsList implements ObjectListInterface {

  use ListTrait;

  #[Definition(VisualRegressionContainer::class)]
  case VisualRegressionContainer;

  public function templateDirectory(): string {
    return \sprintf('@%s/%s', Twig::NAMESPACE, $this->resolveSubDirectory());
  }

}
