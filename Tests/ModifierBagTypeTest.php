<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PreviousNext\Ds\Common;
use PreviousNext\Ds\Mixtape;
use PreviousNext\Ds\Nsw;
use PreviousNext\IdsTools\DependencyInjection\IdsContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ModifierBagTypeTest extends TestCase {

  /**
   * Checks which template is associated with an objects modifier bag(s).
   *
   * @phpstan-param array<string, class-string> $expectedModifiers
   */
  #[DataProvider('containers')]
  public function testModifierBagType(ContainerInterface $container, array $expectedModifiers): void {
    /** @var array<class-string<\Pinto\List\ObjectListInterface>> $pintoLists */
    $pintoLists = $container->getParameter('pinto.lists');

    $actualModifiers = [];
    $modifierMapping = (new Common\Modifier\Lookup\ModifierBagTypeDiscovery())->discovery($pintoLists);
    foreach ($modifierMapping as $objectClass => $modifiersProperties) {
      foreach ($modifiersProperties as $property => $modifiers) {
        $actualModifiers[$objectClass . '$' . $property] = $modifiers;
      }
    }

    static::assertEqualsCanonicalizing($expectedModifiers, $actualModifiers);
  }

  public static function containers(): \Generator {
    $commonModifiers = [
      Common\Atom\Button\Button::class . '$modifiers' => Common\Atom\Button\ButtonModifierInterface::class,
      Common\Atom\Icon\Icon::class . '$modifiers' => Common\Atom\Icon\IconModifierInterface::class,
      Common\Component\Card\Card::class . '$modifiers' => Common\Component\Card\CardModifierInterface::class,
      Common\Component\HeroBanner\HeroBanner::class . '$modifiers' => Common\Component\HeroBanner\HeroBannerModifierInterface::class,
      Common\Component\InPageNavigation\InPageNavigation::class . '$modifiers' => Common\Component\InPageNavigation\InPageNavigationIncludeElementsInterface::class,
      Common\Component\ListItem\ListItem::class . '$modifiers' => Common\Component\ListItem\ListItemModifierInterface::class,
      Common\Component\Steps\Steps::class . '$modifiers' => Common\Component\Steps\StepsModifierInterface::class,
      Common\Layout\Footer\Footer::class . '$modifiers' => Common\Layout\Footer\FooterModifierInterface::class,
      Common\Layout\Grid\Grid::class . '$modifiers' => Common\Layout\Grid\GridModifierInterface::class,
      Common\Layout\Grid\GridItem\GridItem::class . '$modifiers' => Common\Layout\Grid\GridItem\GridItemModifierInterface::class,
      Common\Layout\Header\Header::class . '$modifiers' => Common\Layout\Header\HeaderModifierInterface::class,
      Common\Layout\Masthead\Masthead::class . '$modifiers' => Common\Layout\Masthead\MastheadModifierInterface::class,
      Common\Layout\Section\Section::class . '$modifiers' => Common\Layout\Section\SectionModifierInterface::class,
      Common\Layout\Sidebar\Sidebar::class . '$modifiers' => Common\Layout\Sidebar\SidebarModifierInterface::class,
    ];

    $mixtapeModifiers = [
      Mixtape\Atom\Button\Button::class . '$modifiers' => Common\Atom\Button\ButtonModifierInterface::class,
      Mixtape\Atom\Icon\Icon::class . '$modifiers' => Common\Atom\Icon\IconModifierInterface::class,
      Mixtape\Component\Card\Card::class . '$modifiers' => Common\Component\Card\CardModifierInterface::class,
      Mixtape\Component\HeroBanner\HeroBanner::class . '$modifiers' => Common\Component\HeroBanner\HeroBannerModifierInterface::class,
      Mixtape\Component\InPageNavigation\InPageNavigation::class . '$modifiers' => Common\Component\InPageNavigation\InPageNavigationIncludeElementsInterface::class,
      Mixtape\Component\ListItem\ListItem::class . '$modifiers' => Common\Component\ListItem\ListItemModifierInterface::class,
      Mixtape\Component\Steps\Steps::class . '$modifiers' => Common\Component\Steps\StepsModifierInterface::class,
      Mixtape\Layout\Footer\Footer::class . '$modifiers' => Common\Layout\Footer\FooterModifierInterface::class,
      Mixtape\Layout\Grid\Grid::class . '$modifiers' => Common\Layout\Grid\GridModifierInterface::class,
      Mixtape\Layout\Grid\GridItem\GridItem::class . '$modifiers' => Common\Layout\Grid\GridItem\GridItemModifierInterface::class,
      Mixtape\Layout\Header\Header::class . '$modifiers' => Common\Layout\Header\HeaderModifierInterface::class,
      Mixtape\Layout\Masthead\Masthead::class . '$modifiers' => Common\Layout\Masthead\MastheadModifierInterface::class,
      Mixtape\Layout\Section\Section::class . '$modifiers' => Common\Layout\Section\SectionModifierInterface::class,
      Mixtape\Layout\Sidebar\Sidebar::class . '$modifiers' => Common\Layout\Sidebar\SidebarModifierInterface::class,
    ];

    $nswModifiers = [
      Nsw\Atom\Button\Button::class . '$modifiers' => Common\Atom\Button\ButtonModifierInterface::class,
      Nsw\Atom\Icon\Icon::class . '$modifiers' => Common\Atom\Icon\IconModifierInterface::class,
      Nsw\Component\Card\Card::class . '$modifiers' => Common\Component\Card\CardModifierInterface::class,
      Nsw\Component\HeroBanner\HeroBanner::class . '$modifiers' => Common\Component\HeroBanner\HeroBannerModifierInterface::class,
      Nsw\Component\ListItem\ListItem::class . '$modifiers' => Common\Component\ListItem\ListItemModifierInterface::class,
      Nsw\Layout\Footer\Footer::class . '$modifiers' => Common\Layout\Footer\FooterModifierInterface::class,
      Nsw\Layout\Grid\Grid::class . '$modifiers' => Common\Layout\Grid\GridModifierInterface::class,
      Nsw\Layout\Grid\GridItem\GridItem::class . '$modifiers' => Common\Layout\Grid\GridItem\GridItemModifierInterface::class,
      Nsw\Layout\Masthead\Masthead::class . '$modifiers' => Common\Layout\Masthead\MastheadModifierInterface::class,
      Nsw\Layout\Section\Section::class . '$modifiers' => Common\Layout\Section\SectionModifierInterface::class,
      Nsw\Layout\Sidebar\Sidebar::class . '$modifiers' => Common\Layout\Sidebar\SidebarModifierInterface::class,
    ];

    foreach (IdsContainer::testContainers() as $ds => $container) {
      yield $ds => [
        'container' => $container,
        'expectedModifiers' => match($ds) {
          'mixtape' => [...$commonModifiers, ...$mixtapeModifiers],
          'nswds' => [...$commonModifiers, ...$nswModifiers],
          default => [],
        },
      ];
    }
  }

}
