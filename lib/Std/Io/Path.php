<?php
namespace Std\Io {

  use Std\StringTools;

  class Path
  {

    public function __construct(string $path)
    {
      $this->backslash = false;
      if ($path === "." || $path === "..")
      {
        $this->dir = $path;
        $this->file = "";
        return;
      }
      $c1 = StringTools::lastIndexOf($path, "/");
      $c2 = StringTools::lastIndexOf($path, "\\");
      if ($c1 < $c2)
      {
        $this->dir = (new \Std\PhaseString($path))->substr(0, $c2);
        $path = (new \Std\PhaseString($path))->substr($c2 + 1);
        $this->backslash = true;
      }
      else
      {
        if ($c2 < $c1)
        {
          $this->dir = (new \Std\PhaseString($path))->substr(0, $c1);
          $path = (new \Std\PhaseString($path))->substr($c1 + 1);
        }
        else
        {
          $this->dir = null;
        }
      }
      $cp = StringTools::lastIndexOf($path, ".");
      if ($cp !== -1)
      {
        $this->ext = (new \Std\PhaseString($path))->substr($cp + 1);
        $this->file = (new \Std\PhaseString($path))->substr(0, $cp);
      }
      else
      {
        $this->ext = null;
        $this->file = $path;
      }
    }

    static public function of(string $subject)
    {
      return new Path($subject);
    }

    static public function join(\Std\PhaseArray $parts):Path
    {
      $paths = $parts->filter(function ($it = null)
      {
        return $it !== null && $it !== "";
      });
      if ($paths->length === 0)
      {
        return new Path("");
      }
      $path = $paths[0];
      for ($i = 1; $i < $paths->length; $i++)
      {
        $path = static::addTrailingSlash($path);
        $path = $path . $paths[$i];
      }
      return (new Path($path))->normalized();
    }

    static public function addTrailingSlash(string $path):string
    {
      if ((new \Std\PhaseString($path))->length === 0)
      {
        return "/";
      }
      $c1 = StringTools::lastIndexOf($path, "/");
      $c2 = StringTools::lastIndexOf($path, "\\");
      if ($c1 < $c2)
      {
        if ($c2 !== (new \Std\PhaseString($path))->length - 1)
        {
          return $path . "\\";
        }
        return $path;
      }
      else
      {
        if ($c1 !== (new \Std\PhaseString($path))->length - 1)
        {
          return $path . "/";
        }
        return $path;
      }
    }

    public ?string $dir;

    public string $file;

    public ?string $ext;

    public Bool $backslash;

    public function with(string $part):Path
    {
      return Path::join(new \Std\PhaseArray([$this->toString(), $part]));
    }

    public function toString():string
    {
      return $this->__toString();
    }

    public function __toString():string
    {
      $slash = $this->backslash ? "\\" : "/";
      $dir = $this->dir === null ? "" : "" . ($this->dir) . "" . ($slash) . "";
      $ext = $this->ext === null ? "" : "." . ($this->ext) . "";
      return "" . ($dir) . "" . ($this->file) . "" . ($ext) . "";
    }

    public function withoutExtension():Path
    {
      $path = clone($this);
      $path->ext = null;
      return $path;
    }

    public function withoutDirectory():Path
    {
      $path = clone($this);
      $path->dir = null;
      return $path;
    }

    public function getDirectory():string
    {
      return $this->dir === null ? "" : $this->dir;
    }

    public function getExtension():string
    {
      return $this->ext === null ? "" : $this->ext;
    }

    public function withExtension(string $ext):Path
    {
      $path = clone($this);
      $path->ext = $ext;
      return $path;
    }

    public function normalized():Path
    {
      $slash = "/";
      $path = (new \Std\PhaseString($this->toString()))->split("\\")->join($slash);
      if ($path === $slash)
      {
        return new Path($slash);
      }
      $target = new \Std\PhaseArray([]);
      foreach ((new \Std\PhaseString($path))->split($slash) as $token)
      {
        if ($token === ".." && $target->length > 0 && $target[$target->length - 1] !== "..")
        {
          $target->pop();
        }
        else
        {
          if ($token === "")
          {
            if ($target->length > 0 || StringTools::charAt($path, 0) === "/")
            {
              $target->push($token);
            }
          }
          else
          {
            if ($token !== ".")
            {
              $target->push($token);
            }
          }
        }
      }
      return new Path($target->join($slash));
    }

  }

}