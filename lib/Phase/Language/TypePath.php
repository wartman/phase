<?php
namespace Phase\Language {


  class TypePath
  {

    public function __construct(\Std\PhaseArray $ns, string $name, ?\Std\PhaseArray $params = null, Bool $isAbsolute = false, Bool $isNullable = false)
    {
      $this->isNullable = $isNullable;
      $this->isAbsolute = $isAbsolute;
      $this->params = $params;
      $this->name = $name;
      $this->ns = $ns;
      if ($this->params == null)
      {
        $this->params = new \Std\PhaseArray([]);
      }
    }

    public \Std\PhaseArray $ns;

    public string $name;

    public ?\Std\PhaseArray $params;

    public Bool $isAbsolute;

    public Bool $isNullable;

    public function toString()
    {
      $path = $this->ns->concat(new \Std\PhaseArray([$this->name]))->join("::");
      if ($this->isAbsolute)
      {
        $path = "::" . ($path) . "";
      }
      if ($this->isNullable)
      {
        $path = "?" . ($path) . "";
      }
      if ($this->params != null && $this->params->length > 0)
      {
        return "" . ($path) . "<" . ($this->params->map(function ($it = null)
        {
          return $it->toString();
        })->join(", ")) . ">";
      }
      return $path;
    }

  }

}