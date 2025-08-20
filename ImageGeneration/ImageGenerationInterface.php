<?php

declare(strict_types=1);

namespace PreviousNext\IdsTools\ImageGeneration;

interface ImageGenerationInterface {

  /**
   * Creates an image and returns a URL suitable for <img src>.
   */
  public static function createSample(int $width, int $height): string;

}
