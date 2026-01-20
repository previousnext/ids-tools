<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Tests;

use GoodPhp\Reflection\Reflector;
use GoodPhp\Reflection\ReflectorBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PreviousNext\Ds\Common;
use PreviousNext\Ds\Common\Modifier\Lookup\CollectionGenerics;
use PreviousNext\Ds\Mixtape;
use PreviousNext\Ds\Nsw;
use PreviousNext\IdsTools\DependencyInjection\IdsCollectionGenericsCompilerPass;
use PreviousNext\IdsTools\DependencyInjection\IdsContainer;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectionGenericsTest extends TestCase {

  /**
   * Alias so we don't need to make the original public.
   */
  private const COLLECTION_GENERICS_SERVICE = '.collection_generics';

  private static Reflector $reflector;

  protected function setUp(): void {
    parent::setUp();

    // Build once as it's expensive.
    $this::$reflector ??= (new ReflectorBuilder())
      ->withFileCache()
      ->withMemoryCache()
      ->build();
  }

  /**
   * Checks collection generics is correct for determining which value type is produced when iterating the collection.
   *
   * @phpstan-param class-string $objectClassName
   */
  #[DataProvider('offsetGetData')]
  public function testGenericsIteration(
    string $expectedType,
    string $objectClassName,
    CollectionGenerics $collectionGenericsDiscovery,
  ): void {
    static::assertEquals($expectedType, (string) $collectionGenericsDiscovery->iterableType($objectClassName));
  }

  public static function offsetGetData(): \Generator {
    $commonCollectionItems = [
      Common\Atom\Html\Html::class => 'mixed',
      Common\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Common\Component\Breadcrumb\Breadcrumb::class => Common\Atom\Link\Link::class,
      Common\Component\LinkList\LinkList::class => Common\Atom\Link\Link::class,
      Common\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Common\Component\Pagination\Pagination::class => Common\Component\Pagination\PaginationItem\PaginationItem::class,
      Common\Component\SideNavigation\SideNavigation::class => Common\Vo\MenuTree\MenuTree::class,
      Common\Component\SocialLinks\SocialLinks::class => Common\Component\SocialLinks\SocialLink\SocialLink::class,
      Common\Component\Steps\Steps::class => Common\Component\Steps\Step\Step::class,
      Common\Component\Tabs\Tabs::class => Common\Component\Tabs\Tab::class,
      Common\Component\Tags\Tags::class => 'PreviousNext\Ds\Common\Component\Tags\Tag|PreviousNext\Ds\Common\Component\Tags\CheckboxTag|PreviousNext\Ds\Common\Component\Tags\LinkTag',
      Common\Layout\Grid\Grid::class => Common\Layout\Grid\GridItem\GridItem::class,
      Common\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Common\Layout\Section\Section::class => Common\Layout\Section\SectionItem::class,
      Common\Layout\Sidebar\Sidebar::class => 'mixed',
    ];
    $mixtapeCollectionItems = $commonCollectionItems + [
      Mixtape\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Mixtape\Component\Breadcrumb\Breadcrumb::class => Common\Atom\Link\Link::class,
      Mixtape\Component\LinkList\LinkList::class => Common\Atom\Link\Link::class,
      Mixtape\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Mixtape\Component\Pagination\Pagination::class => Common\Component\Pagination\PaginationItem\PaginationItem::class,
      Mixtape\Component\SideNavigation\SideNavigation::class => Common\Vo\MenuTree\MenuTree::class,
      Mixtape\Component\SocialLinks\SocialLinks::class => Common\Component\SocialLinks\SocialLink\SocialLink::class,
      Mixtape\Component\Steps\Steps::class => Common\Component\Steps\Step\Step::class,
      Mixtape\Component\Tabs\Tabs::class => Common\Component\Tabs\Tab::class,
      Mixtape\Component\Tags\Tags::class => 'PreviousNext\Ds\Common\Component\Tags\Tag|PreviousNext\Ds\Common\Component\Tags\CheckboxTag|PreviousNext\Ds\Common\Component\Tags\LinkTag',
      Mixtape\Layout\Grid\Grid::class => Common\Layout\Grid\GridItem\GridItem::class,
      Mixtape\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Mixtape\Layout\Section\Section::class => Common\Layout\Section\SectionItem::class,
      Mixtape\Layout\Sidebar\Sidebar::class => 'mixed',
    ];
    $nswCollectionItems = $commonCollectionItems + [
      Nsw\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Nsw\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Nsw\Component\SocialLinks\SocialLinks::class => Common\Component\SocialLinks\SocialLink\SocialLink::class,
      Nsw\Layout\Grid\Grid::class => Common\Layout\Grid\GridItem\GridItem::class,
      Nsw\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Nsw\Layout\Section\Section::class => Common\Layout\Section\SectionItem::class,
    ];

    foreach (IdsContainer::testContainers(beforeCompile: static function (ContainerBuilder $containerBuilder): void {
      // Way to slow to add to every container, so just for this test:
      $containerBuilder
        ->addCompilerPass(new IdsCollectionGenericsCompilerPass())
        ->setAlias(static::COLLECTION_GENERICS_SERVICE, CollectionGenerics::class)->setPublic(TRUE);
    }) as $ds => $container) {
      $expectedItems = match ($ds) {
        'mixtape' => $mixtapeCollectionItems,
        'nswds' => $nswCollectionItems,
        default => throw new \LogicException('Missing for DS:' . $ds),
      };

      foreach ($expectedItems as $objectClassName => $expectedItem) {
        yield \sprintf('%s:%s', $ds, $objectClassName) => [
          $expectedItems[$objectClassName],
          $objectClassName,
          $container->get(static::COLLECTION_GENERICS_SERVICE),
        ];
      }
    }
  }

  /**
   * Checks collection generics is correct for determining which value type is required when appending to the collection.
   *
   * @phpstan-param class-string $objectClassName
   */
  #[DataProvider('offsetSetData')]
  public function testGenericsAppend(
    string $expectedType,
    string $objectClassName,
    CollectionGenerics $collectionGenericsDiscovery,
  ): void {
    static::assertEquals($expectedType, $collectionGenericsDiscovery->appendType($objectClassName));
  }

  public static function offsetSetData(): \Generator {
    $commonCollectionAppends = [
      Common\Atom\Html\Html::class => 'mixed',
      Common\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Common\Component\Breadcrumb\Breadcrumb::class => Common\Atom\Link\Link::class,
      Common\Component\LinkList\LinkList::class => Common\Atom\Link\Link::class,
      Common\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Common\Component\Pagination\Pagination::class => Common\Component\Pagination\PaginationItem\PaginationItem::class,
      Common\Component\SideNavigation\SideNavigation::class => Common\Vo\MenuTree\MenuTree::class,
      Common\Component\SocialLinks\SocialLinks::class => \sprintf("%s|%s|%s", Common\Component\SocialLinks\SocialLink\SocialLink::class, Common\Atom\LinkedImage\LinkedImage::class, Common\Atom\Link\Link::class),
      Common\Component\Steps\Steps::class => Common\Component\Steps\Step\Step::class,
      Common\Component\Tabs\Tabs::class => Common\Component\Tabs\Tab::class,
      Common\Component\Tags\Tags::class => 'PreviousNext\Ds\Common\Component\Tags\Tag|PreviousNext\Ds\Common\Component\Tags\CheckboxTag|PreviousNext\Ds\Common\Component\Tags\LinkTag',
      Common\Layout\Grid\Grid::class => 'mixed',
      Common\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Common\Layout\Section\Section::class => 'mixed',
      Common\Layout\Sidebar\Sidebar::class => 'mixed',
    ];
    $mixtapeCollectionAppends = $commonCollectionAppends + [
      Mixtape\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Mixtape\Component\Breadcrumb\Breadcrumb::class => Common\Atom\Link\Link::class,
      Mixtape\Component\LinkList\LinkList::class => Common\Atom\Link\Link::class,
      Mixtape\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Mixtape\Component\Pagination\Pagination::class => Common\Component\Pagination\PaginationItem\PaginationItem::class,
      Mixtape\Component\SideNavigation\SideNavigation::class => Common\Vo\MenuTree\MenuTree::class,
      Mixtape\Component\SocialLinks\SocialLinks::class => \sprintf("%s|%s|%s", Common\Component\SocialLinks\SocialLink\SocialLink::class, Common\Atom\LinkedImage\LinkedImage::class, Common\Atom\Link\Link::class),
      Mixtape\Component\Steps\Steps::class => Common\Component\Steps\Step\Step::class,
      Mixtape\Component\Tabs\Tabs::class => Common\Component\Tabs\Tab::class,
      Mixtape\Component\Tags\Tags::class => 'PreviousNext\Ds\Common\Component\Tags\Tag|PreviousNext\Ds\Common\Component\Tags\CheckboxTag|PreviousNext\Ds\Common\Component\Tags\LinkTag',
      Mixtape\Layout\Grid\Grid::class => 'mixed',
      Mixtape\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Mixtape\Layout\Section\Section::class => 'mixed',
      Mixtape\Layout\Sidebar\Sidebar::class => 'mixed',
    ];
    $nswCollectionAppends = $commonCollectionAppends + [
      Nsw\Component\Accordion\Accordion::class => Common\Component\Accordion\AccordionItem\AccordionItem::class,
      Nsw\Component\Navigation\Navigation::class => Common\Vo\MenuTree\MenuTree::class,
      Nsw\Component\SocialLinks\SocialLinks::class => \sprintf("%s|%s|%s", Common\Component\SocialLinks\SocialLink\SocialLink::class, Common\Atom\LinkedImage\LinkedImage::class, Common\Atom\Link\Link::class),
      Nsw\Layout\Grid\Grid::class => 'mixed',
      Nsw\Layout\Grid\GridItem\GridItem::class => 'mixed',
      Nsw\Layout\Section\Section::class => 'mixed',
    ];

    foreach (IdsContainer::testContainers(beforeCompile: static function (ContainerBuilder $containerBuilder): void {
      // Way to slow to add to every container, so just for this test:
      $containerBuilder
        ->addCompilerPass(new IdsCollectionGenericsCompilerPass())
        ->setAlias(static::COLLECTION_GENERICS_SERVICE, CollectionGenerics::class)->setPublic(TRUE);
    }) as $ds => $container) {
      $expectedItems = match ($ds) {
        'mixtape' => $mixtapeCollectionAppends,
        'nswds' => $nswCollectionAppends,
        default => throw new \LogicException('Missing for DS:' . $ds),
      };

      foreach ($expectedItems as $objectClassName => $expectedItem) {
        yield \sprintf('%s:%s', $ds, $objectClassName) => [
          $expectedItems[$objectClassName],
          $objectClassName,
          $container->get(static::COLLECTION_GENERICS_SERVICE),
        ];
      }
    }
  }

}
