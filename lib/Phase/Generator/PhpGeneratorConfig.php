<?php
namespace Phase\Generator {


  class PhpGeneratorConfig
  {

    public function __construct(int $phpVersion)
    {
      $this->phpVersion = $phpVersion;
    }

    public int $phpVersion;

  }

}