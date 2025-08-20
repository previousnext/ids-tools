<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\ImageGeneration;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

final class DrupalImageGenerator implements ImageGenerationInterface {

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

    $destination = \sprintf('public://%dx%d-' . $fileName, $width, $height);
    if (\file_exists($destination)) {
      return static::$filePaths[$hash] = static::fileUrlGenerator()->generateAbsoluteString(
        uri: $destination,
      );
    }

    $imagine = new Imagine();
    $source = \sprintf('%s/%s', __DIR__, $fileName);
    $image = $imagine->open($source);
    $image = $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_OUTBOUND);
    $source = static::fileSystem()->tempnam('temporary://', 'ids-imggen');
    if (FALSE === \is_string($source)) {
      throw new \LogicException('Failed to save tempfile.');
    }
    $image->save($source);

    static::fileSystem()->copy(
      source: $source,
      destination: $destination,
      fileExists: FileExists::Error,
    );
    return static::$filePaths[$hash] = static::fileUrlGenerator()->generateAbsoluteString(
      uri: $destination,
    );
  }

  private static function fileSystem(): FileSystemInterface {
    return \Drupal::service(FileSystemInterface::class);
  }

  private static function fileUrlGenerator(): FileUrlGeneratorInterface {
    return \Drupal::service(FileUrlGeneratorInterface::class);
  }

}
