<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Scenario;

use Pinto\List\ObjectListInterface;

/**
 * Scenario with finalized ID and enum.
 */
final class CompiledScenario {

  /**
   * @phpstan-param string $id
   *   String used in file names and test fixtures
   *   An ID must not conflict with another Scenario for the same object.
   * @phpstan-param \Pinto\List\ObjectListInterface $pintoEnum
   *   The enum case associated with this scenario. Should not be provided when
   *   defining a #[Scenario], instead this is populated automatically by
   *   internals.
   * @phpstan-param array{class-string, string}
   *   A callable to generate the scenario.
   * @phpstan-param positive-int $viewPortWidth
   * @phpstan-param positive-int $viewPortHeight
   */
  public function __construct(
    public readonly string $id,
    public readonly ObjectListInterface $pintoEnum,
    public $scenario,
    public readonly ?int $viewPortWidth = NULL,
    public readonly ?int $viewPortHeight = NULL,
    public readonly ?string $yieldKey = NULL,
  ) {
  }

  public function cloneWith(
    string $id,
    ?string $yieldKey = NULL,
  ): static {
    return new static(
      $id,
      $this->pintoEnum,
      $this->scenario,
      $this->viewPortWidth,
      $this->viewPortHeight,
      $yieldKey,
    );
  }

  public function scenarioLocation(): string {
    $rClass = new \ReflectionClass($this->scenario[0]);
    $rMethod = $rClass->getMethod($this->scenario[1]);
    return \sprintf('%s::%s', ...$this->scenario);
  }

  /**
   * Get the scenario code for a snippet.
   *
   * Returns null when there is nothing significant left after processing.
   */
  public function scenarioCode(): ?string {
    $rClass = new \ReflectionClass($this->scenario[0]);
    $rMethod = $rClass->getMethod($this->scenario[1]);

    $startLine = $rMethod->getStartLine();
    $endLine = $rMethod->getEndLine();
    $fileName = $rMethod->getFileName();
    if ($startLine === FALSE || $endLine === FALSE || $fileName === FALSE) {
      return NULL;
    }

    $lines = \Safe\file($fileName);
    $lines = \array_slice($lines, $startLine, $endLine - $startLine - 1);

    // Compute common left padding and remove.
    $codeLines = \array_filter($lines, static fn ($line) => \trim($line) !== '');
    if ([] === $codeLines) {
      return NULL;
    }

    $commonSpacing = \min(\array_map(static function ($line) {
      \preg_match('/^(\s*)/', $line, $matches);
      return \strlen($matches[1]);
    }, $codeLines));

    // Remove spacing.
    $trimmedLines = \array_map(static fn ($line) => \substr($line, $commonSpacing), $lines);

    // From the last line, remove all empty lines until code is found.
    foreach (\range(\count($trimmedLines) - 1, 0) as $k) {
      if ($trimmedLines[$k] === '') {
        unset($trimmedLines[$k]);
      }

      // Break when non empty is found.
      break;
    }

    // Remove last line if it has a `return`.
    if (\count($trimmedLines) !== 0 && \str_contains($trimmedLines[\array_key_last($trimmedLines)], 'return ')) {
      unset($trimmedLines[\array_key_last($trimmedLines)]);
    }

    // Sometimes theres nothing left.
    if ([] === $trimmedLines) {
      return NULL;
    }

    // Also remove any `\n` from last item.
    $trimmedLines[\array_key_last($trimmedLines)] = \rtrim($trimmedLines[\array_key_last($trimmedLines)]);

    return \implode('', $trimmedLines);
  }

  /**
   * @phpstan-return (callable(): (callable-object|\Generator))
   */
  public function __invoke(): mixed {
    return (($this->scenario)(...))();
  }

  /**
   * A string for debugging purposes.
   *
   * This is used in test dataprovider.
   * Not suitable for use with directory and file name use.
   */
  public function __toString(): string {
    $rClass = new \ReflectionClass($this->scenario[0]);
    $rMethod = $rClass->getMethod($this->scenario[1]);
    return \sprintf('%s::%s%s', $rClass->getName(), $rMethod->getName(), ($this->yieldKey !== NULL) ? ('=>' . $this->yieldKey) : '');
  }

}
