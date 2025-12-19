<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Command;

use Drupal\Core\Template\Attribute;
use Drupal\pinto\Build\BuildRegistryInterface;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Pinto\List\Resource\ObjectListEnumResource;
use Pinto\PintoMapping;
use PreviousNext\Ds\Common\Component\Media\Image\Image;
use PreviousNext\Ds\Common\List as CommonLists;
use PreviousNext\Ds\Common\Utility\Twig as CommonTwig;
use PreviousNext\Ds\Common\Vo\Id\Id;
use PreviousNext\Ds\Mixtape\Utility\Twig as MixtapeTwig;
use PreviousNext\Ds\Nsw\Utility\Twig as NswTwig;
use PreviousNext\IdsTools\DependencyInjection\IdsCompilerPass;
use PreviousNext\IdsTools\ImageGeneration\DumperImageGenerator;
use PreviousNext\IdsTools\Pinto\VisualRegressionContainer\VisualRegressionContainer;
use PreviousNext\IdsTools\Rendering\DrupalRenderBox;
use PreviousNext\IdsTools\Scenario\CompiledScenario;
use PreviousNext\IdsTools\Scenario\Scenarios;
use Ramsey\Collection\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Markup;

#[AsCommand(
    name: 'dump:html',
)]
final class DumpHtml extends Command {

  protected const IDE_LAUNCH = [
    'emacs' => 'emacs://open?url=file://%s&line=%s',
    'macvim' => 'mvim://open?url=file://%s&line=%s',
    'phpstorm' => 'phpstorm://open?file=%s&line=%s',
    'sublime' => 'subl://open?url=file://%s&line=%s',
    'textmate' => 'txmt://open?url=file://%s&line=%s',
    'vscode' => 'vscode://file/%s:%s',
  ];

  /**
   * @var value-of<\PreviousNext\IdsTools\Command\DumpHtml::IDE_LAUNCH>
   */
  private string $ideLaunch = self::IDE_LAUNCH['phpstorm'];

  private DrupalRenderBox $box;

  private ?RemoteWebDriver $driver = NULL;
  private string $twigCacheDirectory;

  /**
   * @phpstan-param array<class-string<\Pinto\List\ObjectListInterface>> $primaryLists
   */
  public function __construct(
    private BuildRegistryInterface $buildRegistry,
    private PintoMapping $pintoMapping,
    #[Autowire('%pinto.internal.hook_theme%')]
    string $hookTheme,
    #[Autowire('%ids.ide%')]
    string $ide,
    #[Autowire('%ids.project_dir%')]
    private string $projectDir,
    #[Autowire('%' . IdsCompilerPass::PRIMARY_LISTS . '%')]
    private array $primaryLists,
    #[Autowire('%ids.webdriver.url%')]
    private string $webdriverUrl,
    #[Autowire('%ids.twig.cache_dir%')]
    ?string $twigCacheDirectory = NULL,
    private Stopwatch $stopwatch = new Stopwatch(),
  ) {
    $this->twigCacheDirectory = $twigCacheDirectory ?? \sys_get_temp_dir();
    if (!\is_dir($this->twigCacheDirectory)) {
      throw new \Exception(\sprintf('Directory `%s` does not exist', $this->twigCacheDirectory));
    }

    /** @var array<string, array{path: string, template: string, variables: array<string, mixed>}> $hookThemeDefinitions */
    $hookThemeDefinitions = \unserialize($hookTheme);
    $this->box = DrupalRenderBox::from($this->buildRegistry, $this->pintoMapping, $hookThemeDefinitions);
    $this->ideLaunch = self::IDE_LAUNCH[$ide] ?? throw new \Exception('Unknown IDE: ' . $ide);
    parent::__construct();
  }

  protected function configure(): void {
    parent::configure();
    $this
      ->addOption('no-screenshots')
      ->addOption('filter', mode: InputOption::VALUE_REQUIRED, description: 'Filters the scenarios by the value. Value is compared against scenario name.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $io->writeln('Starting...');
    $this->stopwatch->start('Total');

    // Object Generation:
    $this->stopwatch->start('object generation');
    $io->writeln('Object starting...');

    // Iterate the findScenarios collection so it forces PHP objects to be
    // generated and timed.
    /** @var \SplObjectStorage<\PreviousNext\IdsTools\Scenario\CompiledScenario, object&callable> $scenarios */
    $scenarios = new \SplObjectStorage();
    foreach (Scenarios::findScenarios($this->pintoMapping, $this->primaryLists) as $scenario => $scenarioObject) {
      $this->stopwatch->lap('object generation');
      $scenarios[$scenario] = $scenarioObject;
    }

    $this->stopwatch->stop('object generation');

    // Filter.
    $filter = $input->getOption('filter');
    if (\is_string($filter)) {
      foreach ($scenarios as $scenario) {
        if (FALSE === \str_contains((string) $scenario, $filter)) {
          $scenarios->offsetUnset($scenario);
          if ($io->isDebug()) {
            $io->writeln(\sprintf('Scenario `%s` was removed as it did not match the provided filter.', $scenario));
          }
        }
      }
    }

    // Twig:
    $this->stopwatch->start('twig');
    $io->writeln('Twig setup');
    // Twig namespaces:
    // @todo fix hardcoding NSW (TODO#0011).
    $nswTwigDir = \Safe\realpath(\DRUPAL_ROOT . '/' . CommonTwig::computePathFromDrupalRootTo(
      \Safe\realpath(\sprintf('%s/../components/design-system/nswds/', \DRUPAL_ROOT)),
    ));
    $loader = new FilesystemLoader();
    $loader->addPath($nswTwigDir, namespace: NswTwig::NAMESPACE);
    $loader->addPath($nswTwigDir, namespace: 'nswds');
    $commonFileName = (new \ReflectionClass(CommonLists\CommonComponents::class))->getFileName();
    if ($commonFileName === FALSE) {
      throw new \LogicException('Impossible');
    }
    $loader->addPath(\DRUPAL_ROOT . '/' . CommonTwig::computePathFromDrupalRootTo(
        \realpath(\dirname($commonFileName) . '/..'),
      ), namespace: CommonTwig::NAMESPACE);
    $loader->addPath(\Safe\realpath(\DRUPAL_ROOT . '/' . CommonTwig::computePathFromDrupalRootTo(
      \Safe\realpath(\sprintf('%s/../components/design-system/mixtape/', \DRUPAL_ROOT)),
    )), namespace: MixtapeTwig::NAMESPACE);
    $this->stopwatch->stop('twig');

    // Generate HTML & Assets and Screenshots.
    ProgressBar::setFormatDefinition('custom', "%bar% %current%/%max%\n%message%\n%memory%");
    $section1 = $output->section();
    $section2 = $output->section();
    $progress = new ProgressBar($section2);
    $progress->setFormat('custom');
    $progress->setMaxSteps(\count($scenarios));
    $screenshots = !$input->getOption('no-screenshots');

    $isPassingSoFar = TRUE;
    foreach ($scenarios as $scenario) {
      $scenarioObject = $scenarios[$scenario];
      $rEnum = new \ReflectionClass(($scenario->pintoEnum ?? throw new \LogicException())::class);
      $progress->setMessage(\sprintf('%s::%s [%s]', $rEnum->getShortName(), $scenario->pintoEnum->name, $scenario->id));

      $scenarioStopwatch = (string) $scenario;
      $scenarioStopwatch = 'scenario: ' . (\strlen($scenarioStopwatch) > 44 ? '...' . \substr($scenarioStopwatch, -44 - 3) : $scenarioStopwatch);
      $this->stopwatch->start($scenarioStopwatch);

      $io2 = new SymfonyStyle($input, $section1);
      $isPassingSoFar &= $this->process($scenario, $scenarioObject, $loader, $io2, $screenshots);

      $this->stopwatch->stop($scenarioStopwatch);
      $progress->advance();
    }

    // Output debug info.
    $io->writeln('Stats...');
    $table = $io->createTable()->setHeaders(['Timer', 'Peak Memory', 'Time']);
    foreach ($this->stopwatch->getSectionEvents(Stopwatch::ROOT) as $event) {
      $table->addRow([$event->getName(), \sprintf('%d MiB', $event->getMemory() / 1024 / 1024), \sprintf('%s ms', $event->getDuration())]);
    }
    $table->render();
    $io->newLine();

    // Finishing.
    $io->writeln('Disconnecting from webdriver...');
    $this->driver?->quit();
    $io->writeln('Done.');

    return (bool) $isPassingSoFar ? self::SUCCESS : self::FAILURE;
  }

  /**
   * Process a single scenario, outputting HTML & Assets, and screenshots.
   *
   * @return bool
   *   Whether the process was successful.
   */
  private function process(CompiledScenario $scenario, object $scenarioObject, LoaderInterface $loader, SymfonyStyle $io, bool $screenshots): bool {
    Id::resetGlobalState();
    Image::setImageGenerator(DumperImageGenerator::class);

    $fs = new Filesystem();
    $templateLoader = $this->box->createTemplateLoader($loader, $this->twigCacheDirectory);
    $needLibraries = new Collection('string');

    // Render the Scenario itself.
    $this->stopwatch->start('rendering');
    $io->writeln('Rendering...');
    try {
      $result = $this->box->renderObject($scenarioObject, $templateLoader, $needLibraries);
    }
    catch (\Exception $e) {
      $io->error('Failed to render template: ' . $e->getMessage());
      return FALSE;
    }
    $this->stopwatch->stop('rendering');

    // Render the Outer template which will display the Scenario inside it.
    $io->writeln('Container template...');

    // This could be improved by using the same render loop above somehow.
    // Without the hard coded paths and template name...
    $vrcfileName = (new \ReflectionClass(VisualRegressionContainer::class))->getFileName();
    if ($vrcfileName === FALSE) {
      throw new \LogicException('Impossible');
    }

    $loader = new FilesystemLoader([\dirname($vrcfileName)]);
    $twig = new Environment($loader, options: [
      'debug' => TRUE,
      'cache' => $this->twigCacheDirectory,
    ]);

    // @todo make configurable (TODO#0005)
    $htmlDiskRoot = \Safe\realpath(__DIR__ . '/../../../output/html');
    $screenshotDiskRoot = \Safe\realpath(__DIR__ . '/../../../output/screenshots');
    $snippetDiskRoot = \Safe\realpath(__DIR__ . '/../../../output/code-snippets');

    $rEnum = new \ReflectionClass(($scenario->pintoEnum ?? throw new \LogicException())::class);
    $locationSuffix = \sprintf('/%s/%s/%s', $rEnum->getShortName(), $scenario->pintoEnum->name, $scenario->id);
    $dumpHtmlTo = $htmlDiskRoot . $locationSuffix;
    $dumpScreenshotsTo = $screenshotDiskRoot . $locationSuffix;

    $code = $scenario->scenarioCode();
    if ($code !== NULL) {
      $code = "```php\n" . $code . "\n```\n";
      $fs->dumpFile($snippetDiskRoot . $locationSuffix . '.md', $code);
    }

    [$css, $js] = $this->box->collectLibraries($needLibraries);

    $io->writeln('Container template libraries...');
    $cssPaths = [];
    $jsPaths = [];
    // Should this just be inlined?
    $io->writeln('Dumping CSS and JS...');
    $i = 0;
    foreach ($css as $absoluteFileName => $definition) {
      $absoluteFileName = \DRUPAL_ROOT . $absoluteFileName;
      $i++;
      $assetPath = \sprintf('/css/%s-%s', $i, \basename($absoluteFileName));
      if (isset($definition['attributes'])) {
        // @todo remove use of Drupals attribute.
        $definition['attributes'] = new Attribute($definition['attributes']);
      }
      $cssPaths[] = ['href' => $locationSuffix . $assetPath] + $definition;
      $fs->dumpFile(\sprintf('%s%s', $dumpHtmlTo, $assetPath), \Safe\file_get_contents($absoluteFileName));
    }
    $i = 0;
    foreach ($js as $absoluteFileName => $definition) {
      $absoluteFileName = \DRUPAL_ROOT . $absoluteFileName;
      $i++;
      $assetPath = \sprintf('/js/%s-%s', $i, \basename($absoluteFileName));
      if (isset($definition['attributes'])) {
        // @todo remove use of Drupals attribute.
        $definition['attributes'] = new Attribute($definition['attributes']);
      }
      $jsPaths[] = ['src' => $locationSuffix . $assetPath] + $definition;
      $fs->dumpFile(\sprintf('%s%s', $dumpHtmlTo, $assetPath), \Safe\file_get_contents($absoluteFileName));
    }

    $commonLeftPrefix = static function (string $a, string $b): int {
      $longer = \strlen($a) >= \strlen($b) ? $a : $b;
      $i = 0;
      while ($i < \strlen($longer)) {
        $fragA = \substr($a, $i, 1);
        $fragB = \substr($b, $i, 1);
        if ($fragB !== $fragA) {
          break;
        }

        $i++;
      }

      return $i;
    };

    $io->writeln('Debugging information...');
    // Debugging vars.
    $resource = $this->pintoMapping->getResource($scenarioObject::class);
    if (!$resource instanceof ObjectListEnumResource) {
      // @todo need to fix this if we want to support Standalone components.
      throw new \LogicException('Not supported right now...');
    }

    if (NULL === $resource->getClass()) {
      throw new \LogicException('Not supported right now...');
    }

    $enum = $resource->pintoEnum;
    $r = new \ReflectionEnum($enum);
    $enumHref = \sprintf($this->ideLaunch, $this->projectDir . '/' . \substr($r->getFileName(), $commonLeftPrefix($r->getFileName(), \DRUPAL_ROOT)), (string) $r->getStartLine());
    $r = new \ReflectionClass($scenarioObject::class);
    $objectHref = \sprintf($this->ideLaunch, $this->projectDir . '/' . \substr($r->getFileName(), $commonLeftPrefix($r->getFileName(), \DRUPAL_ROOT)), (string) $r->getStartLine());
    $r = new \ReflectionMethod($scenario->scenarioLocation());
    $scenarioHref = \sprintf($this->ideLaunch, $this->projectDir . '/' . \substr($r->getFileName(), $commonLeftPrefix($r->getFileName(), \DRUPAL_ROOT)), (string) $r->getStartLine());

    $io->writeln('Rendering container template...');
    $outerRendered = $twig->render('template.html.twig', [
        // Trust it.
      'inner' => new Markup($result, 'utf-8'),
      'css' => $cssPaths,
      'js' => $jsPaths,
      'enum' => \sprintf('%s::%s', $enum::class, $enum->name),
      'enumHref' => $enumHref,
      'objectClass' => $scenarioObject::class,
      'objectClassHref' => $objectHref,
        // @todo render array dumped. This can be collapsed in render.
      'scenario' => $scenario->scenarioLocation(),
      'scenarioHref' => $scenarioHref,
      'subScenario' => $scenario->yieldKey ?? NULL,
      'viewPortWidth' => $scenario->viewPortWidth,
      'viewPortHeight' => $scenario->viewPortHeight,
    ]);

    $io->writeln('Dumping container template...');
    $fs->dumpFile($dumpHtmlTo . '/index.html', $outerRendered);

    if ($screenshots === FALSE) {
      return TRUE;
    }

    // Navigate and take a screenshot.
    $io->writeln('Connecting to webdriver...');
    $driver = $this->driver();
    // @todo configurable.
    $urlBase = 'http://http/';

    $requestUrl = $urlBase . $locationSuffix;
    $io->writeln('Navigating...');
    $w = $driver->executeScript('return document.body.scrollWidth') + 100;
    $h = $driver->executeScript('return document.body.scrollHeight') + 100;
    // Not 100% sure this works.
    $driver->manage()->window()->setSize(new WebDriverDimension($scenario->viewPortWidth ?? $w, $scenario->viewPortHeight ?? $h));
    $driver->get($requestUrl);
    $io->writeln('Taking screenshot...');
    $screenshotArea = $driver->findElement(WebDriverBy::id('pinto-ace-screenshot-area'));
    // Common reason for this failing is there is no data to screenshot, i.e, a bad template problem.
    try {
      $screenshotArea->takeElementScreenshot(\sprintf('%s.png', $dumpScreenshotsTo));
    }
    catch (WebDriverException $e) {
      $io->error('Failed to take screenshot for `' . $scenario . '`: ' . $e->getMessage());
      return FALSE;
    }

    return TRUE;
  }

  private function driver(): RemoteWebDriver {
    if ($this->driver !== NULL) {
      return $this->driver;
    }

    $profile = new FirefoxProfile();
    $profile->setPreference('layout.css.devPixelsPerPx', '4.0');
    $profile->setPreference('ui.prefersReducedMotion', 1);
    $firefoxOptions = new FirefoxOptions();
    $firefoxOptions->setProfile($profile);
    $desiredCapabilities = DesiredCapabilities::firefox();
    $desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);
    $driver = RemoteWebDriver::create($this->webdriverUrl, $desiredCapabilities, 2000);
    return $this->driver = $driver;
  }

}
