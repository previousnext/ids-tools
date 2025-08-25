<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\ImageGeneration;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

/**
 * Used when DumpHtml is active.
 *
 * @see \PreviousNext\IdsTools\Command\DumpHtml
 */
final class DumperImageGenerator implements ImageGenerationInterface {

  /**
   * @var array<string, string>
   */
  public static array $filePaths = [];

  public static function createSample(int $width, int $height): string {
    $ratio = $width / $height;
    $fileName = match (TRUE) {
      $width <= 128 && $ratio <= 1  => '128px.jpg',
      $width <= 256 && $ratio <= 2.5 => '256px.jpg',
      default => 'ppb.jpg',
    };

    $hash = $width . 'x' . $height;

    if (isset(static::$filePaths[$hash])) {
      return static::$filePaths[$hash];
    }

    // Same as note the other one in DumpHtml.
    // @todo make configurable (TODO#0005)
    $htmlDiskRoot = \Safe\realpath(\getcwd() . '/output/html');
    $destination = \sprintf('%s-%s-%s', $width, $height, $fileName);
    if (\file_exists($htmlDiskRoot . '/' . $destination)) {
      return static::$filePaths[$hash] = \sprintf('/%s', $destination);
    }

    $imagine = new Imagine();
    $source = \sprintf('%s/%s', __DIR__, $fileName);
    $image = $imagine->open($source);
    $image = $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_OUTBOUND);

    $resizedPath = \Safe\tempnam(\sys_get_temp_dir(), 'ids-imggen');
    $image->save($resizedPath);

    \Safe\file_put_contents(
      \sprintf('%s/%s', $htmlDiskRoot, $destination),
      \Safe\file_get_contents($resizedPath),
    );

    return static::$filePaths[$hash] = \sprintf('/%s', $destination);
  }

}
