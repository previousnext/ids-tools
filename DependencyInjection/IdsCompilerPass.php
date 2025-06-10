<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A class for wiring up parameters.
 */
final class IdsCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    if (FALSE === $container->hasParameter('ids.design_system')) {
      throw new \Exception('Parameter `ids.design_systems` is required');
    }

    if (FALSE === $container->hasParameter('ids.design_system')) {
      throw new \Exception('Parameter `ids.design_system` is required');
    }

    $ds = $container->resolveEnvPlaceholders($container->getParameter('ids.design_system'), format: TRUE);
    $dsList = $container->getParameter('ids.design_systems');
    if (FALSE === \array_key_exists($ds, $dsList)) {
      throw new \Exception('A design system in `ids.design_systems` with key `' . $ds . '` is not defined.');
    }

    $pintoLists = $container->hasParameter('pinto.lists') ? $container->getParameter('pinto.lists') : [];
    \array_push($pintoLists, ...$dsList[$ds]);

    if ($container->hasParameter('ids.design_system.additional')) {
      /** @var string[] $additionalDs */
      $additionalDs = $container->getParameter('ids.design_system.additional');
      foreach ($additionalDs as $ds) {
        \array_push($pintoLists, ...$dsList[$ds] ?? throw new \LogicException('Design system `' . $ds . '` is not defined.'));
      }
    }

    $container->setParameter('pinto.lists', $pintoLists);
  }

}
