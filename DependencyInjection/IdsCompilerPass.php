<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A class for wiring up parameters.
 */
final class IdsCompilerPass implements CompilerPassInterface {

  public const PRIMARY_LISTS = 'ids-tools.primary_lists';

  public function process(ContainerBuilder $container): void {
    if (FALSE === $container->hasParameter('ids.design_system')) {
      throw new \Exception('Parameter `ids.design_system` is required');
    }

    if (FALSE === $container->hasParameter('ids.design_systems')) {
      throw new \Exception('Parameter `ids.design_systems` is required');
    }

    /** @var string $ds */
    $ds = $container->resolveEnvPlaceholders($container->getParameter('ids.design_system'), format: TRUE);

    /** @var array<string, array{ds: array<class-string<\Pinto\List\ObjectListInterface>>, additional?: array<array<class-string<\Pinto\List\ObjectListInterface>>>}> $dsList */
    $dsList = $container->getParameter('ids.design_systems');
    if (FALSE === \array_key_exists($ds, $dsList)) {
      throw new \Exception('A design system in `ids.design_systems` with key `' . $ds . '` is not defined.');
    }

    /** @var array<class-string<\Pinto\List\ObjectListInterface>> $pintoLists */
    $pintoLists = $container->hasParameter('pinto.lists') ? $container->getParameter('pinto.lists') : [];
    \array_push($pintoLists, ...$dsList[$ds]['ds']);
    $container->setParameter(static::PRIMARY_LISTS, $dsList[$ds]['ds']);

    foreach (($dsList[$ds]['additional'] ?? []) as $lists) {
      \array_push($pintoLists, ...$lists);
    }

    $container->setParameter('pinto.lists', $pintoLists);
  }

}
