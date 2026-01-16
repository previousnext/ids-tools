<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pinto\Attribute\Definition;
use PreviousNext\Ds\Common;
use PreviousNext\Ds\Common\Modifier\Lookup\ModifierLookup;
use PreviousNext\Ds\Mixtape;
use PreviousNext\Ds\Nsw;
use PreviousNext\IdsTools\DependencyInjection\IdsContainer;
use PreviousNext\IdsTools\DependencyInjection\IdsModifierLookupCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ModifierBagDiscoveryTest extends TestCase {

  /**
   * Alias so we don't need to make the original public.
   */
  private const MODIFIER_LOOKUP_SERVICE = '.modifier_lookup';

  /**
   * Checks the discovered modifiers for an object class.
   *
   * @phpstan-param class-string $objectClass
   * @phpstan-param array<string, class-string> $expectedModifiers
   */
  #[DataProvider('containers')]
  public function testModifierBagType(ContainerInterface $container, string $objectClass, array $expectedModifiers): void {
    /** @var \PreviousNext\Ds\Common\Modifier\Lookup\ModifierLookup $modifierLookup */
    $modifierLookup = $container->get(static::MODIFIER_LOOKUP_SERVICE);
    self::assertEqualsCanonicalizing($expectedModifiers, $modifierLookup->modifiersFor($objectClass));
  }

  public static function containers(): \Generator {
    $mixtapeModifiers = [
      Mixtape\Atom\Button\Button::class => [
        Common\Atom\Button\ButtonLayout::class,
        Common\Atom\Button\ButtonStyle::class,
      ],
      Mixtape\Atom\Icon\Icon::class => [
        Mixtape\Atom\Icon\IconSize::class,
        Mixtape\Atom\Icon\Icons::class,
      ],
      Mixtape\Component\Card\Card::class => [
        Mixtape\Component\Card\CardLayout::class,
      ],
      Mixtape\Component\HeroBanner\HeroBanner::class => [
        Mixtape\Component\HeroBanner\HeroBannerBackground::class,
      ],
      Mixtape\Component\InPageNavigation\InPageNavigation::class => [
        Common\Component\InPageNavigation\IncludeHeadingLevels::class,
      ],
      Mixtape\Component\ListItem\ListItem::class => [
        Common\Component\ListItem\DisplayLinkAs::class,
        Common\Component\ListItem\ImagePosition::class,
      ],
      Mixtape\Component\SocialShare\SocialShare::class => [
        Common\Component\SocialShare\SocialMediaUrl::class,
      ],
      Mixtape\Component\Steps\Steps::class => [
        Mixtape\Component\Steps\StepsBackground::class,
      ],
      Mixtape\Layout\Footer\Footer::class => [
        Mixtape\Layout\Footer\FooterBackground::class,
      ],
      Mixtape\Layout\Grid\Grid::class => [
        Mixtape\Layout\Grid\GridColumnSizeModifierExtraLarge::class,
        Mixtape\Layout\Grid\GridColumnSizeModifierExtraSmall::class,
        Mixtape\Layout\Grid\GridColumnSizeModifierLarge::class,
        Mixtape\Layout\Grid\GridColumnSizeModifierMedium::class,
        Mixtape\Layout\Grid\GridColumnSizeModifierSmall::class,
      ],
      Mixtape\Layout\Grid\GridItem\GridItem::class => [
        Mixtape\Layout\Grid\GridItem\GridItemSpanModifier::class,
      ],
      Mixtape\Layout\Header\Header::class => [
        Mixtape\Layout\Header\HeaderLayout::class,
      ],
      Mixtape\Layout\Masthead\Masthead::class => [
        Mixtape\Layout\Masthead\MastheadBackground::class,
      ],
      Mixtape\Layout\Section\Section::class => [
        Mixtape\Layout\Section\SectionSize::class,
        Mixtape\Layout\Section\SectionBackground::class,
        Mixtape\Layout\Section\SectionWidth::class,
      ],
      Mixtape\Layout\Sidebar\Sidebar::class => [
        Mixtape\Layout\Sidebar\SidebarOrderModifier::class,
      ],
    ];

    $nswModifiers = [
      Nsw\Atom\Button\Button::class => [
        Common\Atom\Button\ButtonLayout::class,
        Common\Atom\Button\ButtonStyle::class,
      ],
      Nsw\Atom\Icon\Icon::class => [
        Nsw\Atom\Icon\IconSize::class,
      ],
      Nsw\Component\Card\Card::class => [],
      Nsw\Component\HeroBanner\HeroBanner::class => [
        Nsw\Component\HeroBanner\HeroBannerBackground::class,
      ],
      Nsw\Component\ListItem\ListItem::class => [
        Common\Component\ListItem\DisplayLinkAs::class,
        Common\Component\ListItem\ImagePosition::class,
      ],
      Nsw\Layout\Footer\Footer::class => [
        Nsw\Layout\Footer\FooterBackground::class,
      ],
      Nsw\Layout\Grid\Grid::class => [
        Nsw\Layout\Grid\GridColumnSizeModifier::class,
      ],
      Nsw\Layout\Grid\GridItem\GridItem::class => [],
      Nsw\Layout\Masthead\Masthead::class => [
        Nsw\Layout\Masthead\MastheadBackground::class,
      ],
      Nsw\Layout\Section\Section::class => [
        Nsw\Layout\Section\SectionBackground::class,
      ],
    ];

    foreach (IdsContainer::testContainers(beforeCompile: static function (ContainerBuilder $containerBuilder): void {
      // Way to slow to add to every container, so just for this test:
      $containerBuilder->addCompilerPass(new IdsModifierLookupCompilerPass());
      $containerBuilder->setAlias(static::MODIFIER_LOOKUP_SERVICE, ModifierLookup::class)->setPublic(TRUE);
    }) as $ds => $container) {
      /** @var array<class-string<\Pinto\List\ObjectListInterface>> $pintoLists */
      $pintoLists = $container->getParameter('pinto.lists');
      foreach ($pintoLists as $pintoList) {
        foreach ($pintoList::cases() as $case) {
          $rCase = new \ReflectionEnumUnitCase($case::class, $case->name);
          $definitionAttr = ($rCase->getAttributes(Definition::class)[0] ?? NULL)?->newInstance();
          if ($definitionAttr === NULL) {
            continue;
          }

          yield \sprintf('%s:%s', $ds, $definitionAttr->className) => [
            $container,
            $definitionAttr->className,
            match ($ds) {
              'mixtape' => $mixtapeModifiers[$definitionAttr->className] ?? [],
              'nswds' => $nswModifiers[$definitionAttr->className] ?? [],
              default => [],
            },
          ];
        }
      }
    }
  }

}
