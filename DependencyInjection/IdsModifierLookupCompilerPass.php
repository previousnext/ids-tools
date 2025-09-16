<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\DependencyInjection;

use Pinto\Attribute\Definition;
use PreviousNext\Ds\Common\Modifier\Lookup\ModifierBagTypeDiscovery;
use PreviousNext\Ds\Common\Modifier\Lookup\ModifierLookup;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Called after Pinto lists added to Container.
 */
final class IdsModifierLookupCompilerPass implements CompilerPassInterface {

  public function process(ContainerBuilder $container): void {
    $modifierLookup = $container
      ->register(ModifierLookup::class, ModifierLookup::class)
      ->setAutowired(TRUE);

    /** @var array<class-string<\Pinto\List\ObjectListInterface>> $pintoLists */
    $pintoLists = $container->getParameter('pinto.lists');

    $modifierMapping = (new ModifierBagTypeDiscovery())->discovery($pintoLists);
    $modifierLookup->setArgument(1, $modifierMapping);

    $modifierInterfaces = [];
    foreach ($modifierMapping as $modifiersProperties) {
      \array_push($modifierInterfaces, ...\array_values($modifiersProperties));
    }
    $modifierInterfaces = \array_unique($modifierInterfaces);

    foreach ($this->discovery($this->findNamespaces($pintoLists), $modifierInterfaces) as $modifierInterface => $modifiers) {
      foreach ($modifiers as $modifierClassName) {
        $modifierLookup->addMethodCall('addModifierEnum', [$modifierInterface, $modifierClassName]);
      }
    }
  }

  /**
   * Get namespace prefixes and directories for all objects in the lists.
   *
   * @phpstan-param array<class-string<\Pinto\List\ObjectListInterface>> $pintoLists
   * @phpstan-return \Generator<string, string>
   */
  private function findNamespaces(array $pintoLists): \Generator {
    $dirByNamespacePrefix = [];
    foreach ($pintoLists as $pintoList) {
      foreach ($pintoList::cases() as $case) {
        $rCase = new \ReflectionEnumUnitCase($case::class, $case->name);
        $definitionAttr = ($rCase->getAttributes(Definition::class)[0] ?? NULL)?->newInstance();
        if ($definitionAttr === NULL) {
          continue;
        }

        $rObj = new \ReflectionClass($definitionAttr->className);
        $fileName = $rObj->getFileName();
        if ($fileName === FALSE) {
          throw new \LogicException('Impossible');
        }
        $dirByNamespacePrefix[$rObj->getNamespaceName()] = \dirname($fileName);
      }
    }

    yield from $dirByNamespacePrefix;
  }

  /**
   * Find classes in namespaces which extend of the classes/interfaces.
   *
   * Returns enums class-strings keyed by the classes/interfaces they extend/implement from $extending.
   *
   * @phpstan-param \Generator<string, string> $dirByNamespacePrefix
   * @phpstan-param class-string[] $extending
   * @phpstan-return array<class-string, array<class-string>>
   */
  private function discovery(iterable $dirByNamespacePrefix, array $extending): array {
    /** @var array<class-string, array<class-string>> $classesExtend */
    $classesExtend = [];
    foreach ($dirByNamespacePrefix as $namespacePrefix => $dir) {
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
      foreach ($iterator as $fileinfo) {
        \assert($fileinfo instanceof \SplFileInfo);
        if ($fileinfo->getExtension() !== 'php') {
          continue;
        }

        /** @var \RecursiveDirectoryIterator|null $subDir */
        $subDir = $iterator->getSubIterator();
        if (NULL === $subDir) {
          continue;
        }

        $subDir = $subDir->getSubPath();
        $subDir = $subDir !== '' ? \str_replace(DIRECTORY_SEPARATOR, '\\', $subDir) . '\\' : '';

        /** @var class-string $class */
        $class = $namespacePrefix . $subDir . '\\' . $fileinfo->getBasename('.php');
        if (\class_exists($class) === FALSE) {
          continue;
        }

        foreach ($extending as $extendingClass) {
          // Use regular reflection for the full class hierarchy.
          $implements = [];
          // Implements the interface, and is not an interface/abstract/trait. Instantiable doesn't work as it requires a public constructor.
          $r = new \ReflectionClass($class);
          if ($r->implementsInterface($extendingClass) && !$r->isAbstract() && !$r->isInterface() && !$r->isTrait()) {
            $implements[] = $extendingClass;
          }

          if ($implements !== []) {
            $classesExtend[$class] = $implements;
          }
        }
      }
    }

    // Regroup by interface -> modifier.
    $byModifierIface = [];
    foreach ($classesExtend as $modifier => $modifierImplementsOrExtends) {
      foreach ($modifierImplementsOrExtends as $modifierInterface) {
        $byModifierIface[$modifierInterface][] = $modifier;
      }
    }

    return $byModifierIface;
  }

}
