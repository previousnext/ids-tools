<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Command;

use Drupal\pinto\Build\BuildRegistryInterface;
use Pinto\Attribute\Definition;
use Pinto\PintoMapping;
use PreviousNext\Ds\Common\Vo\Id\Id;
use PreviousNext\IdsTools\DependencyInjection\IdsCompilerPass;
use PreviousNext\IdsTools\Rendering\ComponentRender;
use PreviousNext\IdsTools\Scenario\CompiledScenario;
use PreviousNext\IdsTools\Scenario\Scenarios;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Yaml\Yaml;

/**
 * Dumps the render array from Pinto Objects as YAML.
 *
 * YAML is used instead of JSON, XML, or others, since it doesn't suffer from
 * trailing comma issues.
 */
#[AsCommand(
  name: 'dump:build-objects',
)]
final class DumpBuildObjectSnapshots extends Command {

  private Serializer $serializer;
  private Stopwatch $stopwatch;

  /**
   * @phpstan-param array{directory: string} $buildObjectConfiguration
   * @phpstan-param array<class-string<\Pinto\List\ObjectListInterface>> $primaryLists
   */
  public function __construct(
    private BuildRegistryInterface $buildRegistry,
    private PintoMapping $pintoMapping,
    #[Autowire(param: 'ids.build_objects')]
    private array $buildObjectConfiguration,
    #[Autowire('%' . IdsCompilerPass::PRIMARY_LISTS . '%')]
    private array $primaryLists,
  ) {
    $this->stopwatch = new Stopwatch();
    $this->serializer = static::serializerSetup();
    parent::__construct();
  }

  protected function configure(): void {
    parent::configure();
    $this
      ->addOption('dry-run', 'd', InputOption::VALUE_NONE);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $isDryRun = (bool) $input->getOption('dry-run');

    $io = new SymfonyStyle($input, $output);
    if ($isDryRun) {
      $io->writeln('This is a dry run.');
    }

    $io->writeln('Starting...');
    $outputRelativeToObject = $this->buildObjectConfiguration['directory'];

    $this->stopwatch->start('object generation');
    $io->writeln('Object starting...');

    // Get the Objects and their Scenarios.
    /** @var array<array{\PreviousNext\IdsTools\Scenario\CompiledScenario, callable, class-string}> $scenarios */
    $scenarios = [];
    foreach (Scenarios::findScenarios($this->pintoMapping, $this->primaryLists) as $scenario => $scenarioObject) {
      $this->stopwatch->lap('object generation');
      $pintoEnum = $scenario->pintoEnum ?? throw new \LogicException();
      $definition = ((new \ReflectionEnumUnitCase($pintoEnum::class, $pintoEnum->name))->getAttributes(Definition::class)[0] ?? NULL)?->newInstance() ?? throw new \LogicException('Missing ' . Definition::class);
      $scenarios[] = [$scenario, $scenarioObject, $definition->className];
    }
    $this->stopwatch->stop('object generation');

    // Execute the Pinto Object Scenarios and write to disk.
    $this->stopwatch->start('snapshot write');
    $io->writeln('Writing snapshots');

    $fs = new Filesystem();
    foreach ($scenarios as [$scenario, $scenarioObject, $objectClassName]) {
      try {
        Id::resetGlobalState();

        \assert(\is_object($scenarioObject));
        $rendered = ComponentRender::render(
          $this->pintoMapping,
          $this->buildRegistry,
          $scenarioObject,
        );
      }
      catch (\Throwable $e) {
        throw new \Exception(\sprintf('Failed to render scenario from %s', (string) $scenario), previous: $e);
      }

      $this->stopwatch->lap('snapshot write');
      $fileName = static::getDiskLocationForScenario($scenario, $outputRelativeToObject);
      if ($isDryRun === FALSE) {
        $fs->dumpFile($fileName, $this->serializer->serialize($rendered, 'yaml', [
          'yaml_flags' => Yaml::DUMP_OBJECT | Yaml::PARSE_CUSTOM_TAGS | Yaml::DUMP_NULL_AS_TILDE | Yaml::PARSE_CONSTANT,
          'yaml_inline' => 100,
          'yaml_indent' => 0,
        ]));
      }

      $io->writeln(\sprintf("Writing %s to %s", $scenario->id ?? throw new \LogicException(), $fileName));
    }
    $this->stopwatch->stop('snapshot write');

    // Output debug info.
    $io->writeln('Done.');
    foreach ($this->stopwatch->getSectionEvents(Stopwatch::ROOT) as $event) {
      $io->writeln((string) $event);
    }

    return self::SUCCESS;
  }

  public static function serializerSetup(): Serializer {
    // @todo move this to a service.
    // https://symfony.com/doc/current/serializer.html
    $normalizers = [
      // https://symfony.com/doc/current/serializer.html#serializer-normalizers
      new Normalizer\UnwrappingDenormalizer(),
      new Normalizer\ProblemNormalizer(),
      new Normalizer\UidNormalizer(),
      new Normalizer\DateTimeNormalizer(),
      new Normalizer\BackedEnumNormalizer(),
      new Normalizer\ArrayDenormalizer(),
      new Normalizer\ObjectNormalizer(),
    ];
    return new Serializer($normalizers, [
      new YamlEncoder(),
    ]);
  }

  /**
   * Get the expected fixture location on disk.
   *
   * @return string
   *   Absolute file path. The file may or may not exist.
   */
  public static function getDiskLocationForScenario(CompiledScenario $scenario, string $outputRelativeToObject): string {
    $pintoEnum = $scenario->pintoEnum;
    /** @var \Pinto\Attribute\Definition $definition */
    $definition = ((new \ReflectionEnumUnitCase($pintoEnum::class, $pintoEnum->name))->getAttributes(Definition::class)[0] ?? NULL)?->newInstance() ?? throw new \LogicException('Missing ' . \Symfony\Component\DependencyInjection\Definition::class);
    $r = new \ReflectionClass($definition->className);
    $dumpLocation = \sprintf('%s%s', \dirname($r->getFileName()), $outputRelativeToObject);
    return \sprintf('%s/%s%s.yaml', $dumpLocation, $scenario->id, ($scenario->yieldKey !== NULL ? '-' . $scenario->yieldKey : ''));
  }

}
