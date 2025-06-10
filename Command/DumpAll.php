<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'dump',
)]
final class DumpAll extends Command {

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $io->note('Dumping all commands...');
    $io->writeln('Starting in 3...2...1...');
    foreach (\range(3, 1) as $i) {
      $io->writeln($i . '...');
      \sleep(1);
    }

    // https://symfony.com/doc/current/console/calling_commands.html
    $input = new ArrayInput([
      'command' => 'dump:build-objects',
    ]);
    $input->setInteractive(FALSE);
    $code = $this->getApplication()->doRun($input, $output);
    if ($code !== static::SUCCESS) {
      return $code;
    }
    $io->success('Complete render snapshots.');

    $input = new ArrayInput([
      'command' => 'dump:html',
    ]);
    $input->setInteractive(FALSE);
    $code = $this->getApplication()->doRun($input, $output);
    if ($code !== static::SUCCESS) {
      return $code;
    }
    $io->success('Complete html.');

    $io->success('Completed all.');
    return static::SUCCESS;
  }

}
