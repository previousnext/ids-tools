<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use Drupal\pinto\Build\BuildRegistryInterface;
use Drupal\pinto\PintoCompilerPass;
use Drupal\pinto\PintoMappingFactory;
use Pinto\PintoMapping;
use PreviousNext\Ds\Common\Component\Media\Image\Image;
use PreviousNext\IdsTools\Command\DumpBuildObjectSnapshots;
use PreviousNext\IdsTools\DependencyInjection\Build\IdsToolsBuildRegistry;
use PreviousNext\IdsTools\ImageGeneration\DumperImageGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A class for setting up a container.
 */
final class IdsContainer {

  public static function setupContainer(?ContainerBuilder $container = NULL): void {
    $container ??= new ContainerBuilder();
    $container->setParameter('container.namespaces', []);
    $container->setParameter('pinto.namespaces', []);
    $container->setParameter('pinto.components', []);
    $container->setDefinition(BuildRegistryInterface::class, (new Definition(IdsToolsBuildRegistry::class))->setPublic(TRUE)->setAutowired(TRUE));
    $container->setDefinition(PintoMappingFactory::class, new Definition(PintoMappingFactory::class));
    $container->setDefinition(PintoMapping::class, $pintoMappingDefinition = new Definition());
    $pintoMappingDefinition->setPublic(TRUE);
    $pintoMappingDefinition->setFactory([new Reference(PintoMappingFactory::class), 'create']);

    $container->addCompilerPass(new IdsCompilerPass());
    $container->addCompilerPass(new PintoCompilerPass());

    Image::setImageGenerator(DumperImageGenerator::class);

    \Drupal::setContainer($container);
  }

  /**
   * @phpstan-param (callable(ContainerBuilder): void)|null $beforeCompile
   * @phpstan-return \Generator<string, ContainerInterface>
   */
  public static function testContainers(?callable $beforeCompile = NULL): \Generator {
    $baseContainer = new ContainerBuilder();

    $fileLocator = new FileLocator([\getcwd() . '/.ids-config', __DIR__ . '/../config']);
    $loader = new YamlFileLoader($baseContainer, $fileLocator);
    $loader->load('ids.yaml');

    /** @var array<string, array{ds: array<class-string<\Pinto\List\ObjectListInterface>>, additional?: array<array<class-string<\Pinto\List\ObjectListInterface>>>}> $dsList */
    $dsList = $baseContainer->getParameter('ids.design_systems');
    foreach (\array_keys($dsList) as $ds) {
      yield $ds => static::testContainerForDs($ds, $beforeCompile);
    }
  }

  /**
   * @phpstan-param (callable(ContainerBuilder): void)|null $beforeCompile
   */
  public static function testContainerForDs(string $ds, ?callable $beforeCompile = NULL): ContainerInterface {
    $container = new ContainerBuilder();

    $fileLocator = new FileLocator([\getcwd() . '/.ids-config', __DIR__ . '/../config']);
    $loader = new YamlFileLoader($container, $fileLocator);
    $loader->load('ids.yaml');

    /** @var 'mixtape'|'nswds' $ds */
    $container->setParameter('ids.design_system', $ds);

    // Needed to add scenarios runs as coverage.
    $container->register(DumpBuildObjectSnapshots::class, DumpBuildObjectSnapshots::class)
      ->setPublic(TRUE)->setAutowired(TRUE);

    IdsContainer::setupContainer($container);
    if ($beforeCompile !== NULL) {
      $beforeCompile($container);
    }
    $container->compile();
    return $container;
  }

}
