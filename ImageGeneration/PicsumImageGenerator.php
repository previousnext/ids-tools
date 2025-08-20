<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\ImageGeneration;

final class PicsumImageGenerator implements ImageGenerationInterface {

  public static function createSample(int $width, int $height): string {
    return \sprintf('https://picsum.photos/seed/2025-06-04/%d/%d', $width, $height);
  }

}
