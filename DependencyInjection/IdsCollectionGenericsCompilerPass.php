<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use PreviousNext\Ds\Common\Modifier\Lookup\CollectionGenerics;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IdsCollectionGenericsCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $container
      ->register(CollectionGenerics::class, CollectionGenerics::class)
      ->setAutowired(TRUE);
  }

}
