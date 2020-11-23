<?php

namespace DevCoding\Mac\Command;

use Symfony\Component\Yaml\Yaml;

class AbstractConfigConsole extends AbstractMacConsole
{
  protected $config;
  protected $configFile;

  protected function isAllowUserOption()
  {
    return true;
  }

  protected function hasConfig()
  {
    return !is_null($this->config);
  }

  protected function setConfigFromFile($file)
  {
    if ($config = $this->getConfigFromFile($file))
    {
      $this->configFile = $file;
      $this->config     = $config;
    }
  }

  protected function setConfigFromUser($name)
  {
    $userDir = $this->getUser()->getDir();
    $try[]   = sprintf('%s/.%s.yml', $userDir, $name);
    $try[]   = sprintf('%s/.%s.json', $userDir, $name);

    foreach ($try as $file)
    {
      if ($config = $this->getConfigFromFile($file))
      {
        $this->config     = $config;
        $this->configFile = $file;
      }
    }

    return $this;
  }

  protected function getConfigFromFiles($files)
  {
    foreach ($files as $file)
    {
      if ($config = $this->getConfigFromFile($file))
      {
        return $config;
      }
    }

    return null;
  }

  protected function getConfigFromFile($file)
  {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    switch ($ext)
    {
      case 'yml':
      case 'yaml':
        return $this->getConfigFromYaml($file);
      case 'json':
        return $this->getConfigFromJson($file);
      default:
        return null;
    }
  }

  protected function getConfigFromJson($file)
  {
    if (is_file($file))
    {
      return json_decode(file_get_contents($file), true);
    }

    return null;
  }

  protected function getConfigFromYaml($file)
  {
    if (is_file($file))
    {
      return Yaml::parseFile($file);
    }

    return null;
  }
}
