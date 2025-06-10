<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\pinto\PintoCompilerPass;
use Drupal\pinto\PintoMappingFactory;
use Pinto\PintoMapping;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A class for setting up a container.
 */
final class IdsContainer {

  public static function setupContainer(?ContainerInterface $container = NULL): void {
    $container ??= new ContainerBuilder();
    $container->setParameter('container.namespaces', []);
    $container->setParameter('pinto.namespaces', []);
    $container->setDefinition(PintoMappingFactory::class, new Definition(PintoMappingFactory::class));
    $container->setDefinition(PintoMapping::class, $pintoMappingDefinition = new Definition());
    $pintoMappingDefinition->setPublic(TRUE);
    $pintoMappingDefinition->setFactory([new Reference(PintoMappingFactory::class), 'create']);

    $container->addCompilerPass(new IdsCompilerPass());
    $container->addCompilerPass(new PintoCompilerPass());
    \Drupal::setContainer($container);
  }

}
