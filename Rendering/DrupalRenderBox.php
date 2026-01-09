<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Rendering;

use Drupal\Core\Template\TwigExtension;
use Drupal\pinto\Build\BuildRegistryInterface;
use Drupal\pinto\Build\BuildToken;
use Drupal\pinto\Element\PintoComponentElement;
use Drupal\pinto\Library\DrupalLibraryBuilder;
use Pinto\List\Resource\ObjectListEnumResource;
use Pinto\PintoMapping;
use Ramsey\Collection\Collection;
use Ramsey\Collection\Map\TypedMap;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use Twig\TemplateWrapper;

/**
 * A class for determining hook theme and all assets for a library.
 */
final class DrupalRenderBox {

  /**
   * @phpstan-param \Ramsey\Collection\Collection<string, array{\Pinto\List\ObjectListInterface, string} $hookThemeMap
   * @phpstan-param \Ramsey\Collection\Collection<\Ramsey\Collection\Map\TypedMap<string, array>> $libraryCss
   * @phpstan-param \Ramsey\Collection\Collection<\Ramsey\Collection\Map\TypedMap<string, array>> $libraryJs
   * @phpstan-param (\Closure(object): array<mixed>) $builder
   */
  private function __construct(
    private BuildRegistryInterface $buildRegistry,
    private Collection $hookThemeMap,
    private Collection $libraryCss,
    private Collection $libraryJs,
    private \Closure $builder,
  ) {
  }

  /**
   * Factory.
   *
   * @phpstan-param array<string, array{path: string, template: string, variables: array<string, mixed>}> $hookThemeDefinitions
   */
  public static function from(
    BuildRegistryInterface $buildRegistry,
    PintoMapping $pintoMapping,
    array $hookThemeDefinitions,
  ): static {
    [$css, $js, $dependencyGraph, $hookThemeMap] = static::dependencyGraph($pintoMapping, $hookThemeDefinitions);
    [$libraryCss, $libraryJs] = static::finalizeDependencyGraph($css, $js, $dependencyGraph);
    return new static($buildRegistry, $hookThemeMap, $libraryCss, $libraryJs, static function (object $component) use ($pintoMapping): array {
      return ($pintoMapping->getBuilder($component))();
    });
  }

  /**
   * @phpstan-param array<string, array{path: string, template: string, variables: array<string, mixed>}> $hookThemeDefinitions
   * @phpstan-return array{
   *    \Ramsey\Collection\Collection<TypedMap>,
   *    \Ramsey\Collection\Collection<TypedMap>,
   *   array<mixed>,
   *   \Ramsey\Collection\Collection<string, array{\Pinto\List\ObjectListInterface, string},
   * }
   */
  private static function dependencyGraph(PintoMapping $pintoMapping, $hookThemeDefinitions): array {
    $css = new Collection(TypedMap::class);
    $js = new Collection(TypedMap::class);
    $dependencyGraph = [];

    /** @var \Ramsey\Collection\Collection<array{\Pinto\Resource\ResourceInterface, string}> $hookThemeMap */
    $hookThemeMap = new Collection('array');

    // Libraries:
    foreach (DrupalLibraryBuilder::libraryInfoBuild($pintoMapping) as $libraryName => $library) {
      $publicLibraryName = \sprintf('pinto/%s', $libraryName);
      $dependencyGraph[$publicLibraryName] = $library['dependencies'] ?? [];

      foreach (($library['css'] ?? []) as $componentName => $component) {
        foreach ($component as $absoluteFileName => $definition) {
          $css[$publicLibraryName] ??= new \Ramsey\Collection\Map\TypedMap('string', 'array');
          $css[$publicLibraryName][$absoluteFileName] = $definition;
        }
      }

      foreach (($library['js'] ?? []) as $absoluteFileName => $definition) {
        $js[$publicLibraryName] ??= new \Ramsey\Collection\Map\TypedMap('string', 'array');
        $js[$publicLibraryName][$absoluteFileName] = $definition;
      }
    }

    foreach ($pintoMapping->getResources() as $resource) {
      if (!$resource instanceof ObjectListEnumResource) {
        // @todo need to fix this if we want to support Standalone components.
        continue;
      }

      if (NULL === $resource->getClass()) {
        continue;
      }

      $hookThemeMap[$resource->name()] = [
        $resource,
        $resource->templateDirectory(),
      ];
    }

    return [$css, $js, $dependencyGraph, $hookThemeMap];
  }

  private static function finalizeDependencyGraph($css, $js, $dependencyGraph): array {
    $libraryCss = new Collection(TypedMap::class);
    $libraryJs = new Collection(TypedMap::class);

    // Solve graph.
    $level = 0;
    foreach ($dependencyGraph as $libraryName => $dependenciesOn) {
      $solvedLibraryDependencies = \iterator_to_array(($loop = static function ($dependencies) use (&$loop, &$dependencyGraph, &$level, $libraryName): \Generator {
        $level++;
        if ($level > 32) {
          throw new \LogicException(\sprintf('Recursion needs to be solved for %s', $libraryName));
        }

        yield from $dependencies;

        foreach ($dependencies as $other) {
          if (\array_key_exists($other, $dependencyGraph)) {
            yield from $loop($dependencyGraph[$other]);
          }
        }

        $level--;
      })($dependenciesOn), preserve_keys: FALSE);

      $libraryCss[$libraryName] = $css[$libraryName] ?? new \Ramsey\Collection\Map\TypedMap('string', 'array');
      $libraryJs[$libraryName] = $js[$libraryName] ?? new \Ramsey\Collection\Map\TypedMap('string', 'array');
      foreach ($solvedLibraryDependencies as $dependencyLibraryName) {
        if (isset($css[$dependencyLibraryName])) {
          foreach ($css[$dependencyLibraryName] as $absoluteFileName => $definition) {
            $libraryCss[$libraryName][$absoluteFileName] = $definition;
          }
        }

        if (isset($js[$dependencyLibraryName])) {
          foreach ($js[$dependencyLibraryName] as $definition) {
            $libraryJs[$libraryName][$absoluteFileName] = $definition;
          }
        }
      }
    }

    return [$libraryCss, $libraryJs];
  }

  public function createTemplateLoader(
    FilesystemLoader $loader,
    ?string $twigCacheDirectory = NULL,
  ): TemplateLoader {
    $twig = new Environment($loader, [
      'debug' => TRUE,
      'cache' => $twigCacheDirectory,
      // https://symfony.com/doc/current/reference/configuration/twig.html#strict-variables
      // @todo.
      // 'strict_variables' => TRUE, @codingStandardsIgnoreLine
    ]);
    // Adds `create_attribute`. We should really do away with that.
    // ->render on null bug
    // Not having this also somehow double encodes {{ attributes }}.
    $twig->addExtension((new \ReflectionClass(TwigExtension::class))->newInstanceWithoutConstructor());

    $templateLoader = [];
    foreach ($this->hookThemeMap as $hookTheme => [$resource, $templateDirectory]) {
      $templateLoader[$hookTheme] = static function () use ($twig, $templateDirectory, $resource): TemplateWrapper {
        // Lazy so we dont need to load all templates.
        // Templates used multiple times are cached in $twig.
        // @todo abstract this out.
        $suffix = \str_starts_with($templateDirectory, '@mixtape')
          ? '.twig'
          : '.html.twig';
        return $twig->load(\sprintf('%s/%s%s', $templateDirectory, $resource->templateName(), $suffix));
      };
    }

    return TemplateLoader::create($templateLoader);
  }

  public function renderObject(object $obj, TemplateLoader $templateLoader, Collection $needLibraries): Markup {
    /** @var array<mixed> $result */
    $result = [($this->builder)($obj)];

    $buildRegistryRender = function (BuildToken $buildToken): array {
      return $this->buildRegistry->render($buildToken, []);
    };

    ($loop = static function (&$build) use (&$loop, $templateLoader, $needLibraries, $obj, $buildRegistryRender): void {
      foreach ($build as &$item) {
        if (\is_array($item)) {
          if (($item['#type'] ?? '') === PintoComponentElement::class) {
            $renderArrayExpanded = $buildRegistryRender($item['#buildToken']);
            unset($item['#type']);
            unset($item['#buildToken']);
            $item += $renderArrayExpanded;
          }

          $loop($item);

          // Push libraries:
          foreach (($item['#attached']['library'] ?? []) as $libraryName) {
            $needLibraries[] = $libraryName;
          }

          // Twig variables (context).
          $theme = $item['#theme'] ?? NULL;
          if ($theme !== NULL) {
            foreach ($item as $key => $value) {
              if (\str_starts_with($key, '#')) {
                unset($item[$key]);
                $item[\substr($key, 1)] = $value;
              }
            }

            // Twig render.
            try {
              $rendered = $templateLoader->renderTemplate($theme, context: $item);
            }
            catch (\Exception $e) {
              // If this throws something like
              // "Call to a member function render()" then its likely due to
              // something malformed. Like outputting a collection.
              throw new \Exception(
                \sprintf('Unable to render %s template: %s', $obj::class, $e->getMessage()),
                previous: $e,
              );
            }

            $item = new Markup($rendered, 'utf-8');
          }
        }
      }
    })($result);

    return $result[0];
  }

  public function collectLibraries(Collection $needLibraries): array {
    $css = new TypedMap('string', 'array');
    $js = new TypedMap('string', 'array');
    foreach ($needLibraries as $needLibrary) {
      // Merge values from each.
      if (isset($this->libraryCss[$needLibrary])) {
        foreach ($this->libraryCss[$needLibrary] as $k => $v) {
          $css[$k] = $v;
        }
      }
      if (isset($this->libraryJs[$needLibrary])) {
        foreach ($this->libraryJs[$needLibrary] as $k => $v) {
          $js[$k] = $v;
        }
      }
    }
    // Keys ensure unique.
    return [$css, $js];
  }

}
