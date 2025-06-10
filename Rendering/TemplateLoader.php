<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Rendering;

/**
 * A collection of closures to add Twig templates to Twig loader when hook used.
 *
 * @internal
 */
final class TemplateLoader {

  /**
   * @phpstan-param array<string, callable> $templateLoader
   */
  private function __construct(
    private array $templateLoader,
  ) {
  }

  /**
   * @phpstan-param array<string, callable> $templateLoader
   */
  public static function create(array $templateLoader): static {
    return new static($templateLoader);
  }

  /**
   * @throws \InvalidArgumentException
   *   Thrown if a template for the provided hook theme does not exist.
   */
  public function renderTemplate(string $hookTheme, array $context): string {
    $template = $this->templateLoader[$hookTheme] ?? throw new \InvalidArgumentException();
    /** @var \Twig\TemplateWrapper $templateWrapper */
    $templateWrapper = $template();
    return $templateWrapper->render(context: $context);
  }

}
