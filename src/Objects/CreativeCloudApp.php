<?php
/**
 * CreativeCloudApp.php.
 */

namespace DevCoding\Mac\Objects;

/**
 * PHP object representing an Adobe Creative Cloud application.
 *
 * Class CreativeCloudApp
 *
 * @author  Aaron M Jones <am@jonesiscoding.com>
 *
 * @package DevCoding\Mac\Objects
 */
class CreativeCloudApp implements \JsonSerializable
{
  const PATH_TEMPLATES = [
      '/Applications/Adobe {name} {year}/Adobe {name} {year}.app/Contents/Info.plist',
      '/Applications/Adobe {name} {year}/Adobe {name}.app/Contents/Info.plist',
      '/Applications/Adobe {name}/Adobe {name}.app/Contents/Info.plist',
      '/Applications/Adobe {name} CC/Adobe {name}.app/Contents/Info.plist',
  ];

  const UNINSTALL = '/Library/Application\ Support/Adobe/Adobe\ Desktop\ Common/HDBox/Setup --uninstall=1 --sapCode={sap} --baseVersion={version} --deleteUserPreferences=false --platform=osx10-64';

  /** @var array */
  protected $appInfo;
  /** @var string */
  protected $application;
  /** @var string */
  protected $baseVersion;
  /** @var string */
  protected $path;
  /** @var string */
  protected $year;
  /** @var string */
  protected $name;
  /** @var string */
  protected $version;

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize()
  {
    return [
        'name'        => $this->getName(),
        'full_name'   => $this->getFullName(),
        'sap'         => $this->getSap(),
        'preferences' => $this->getPreferences(),
        'path'        => $this->getPath(),
        'year'        => $this->getYear(),
        'baseVersion' => $this->getBaseVersion(),
        'version'     => $this->getVersion(),
        'uninstall'   => $this->getUninstall(),
    ];
  }

  /**
   * @param string $app  Lowercase, dash separated
   * @param year   $year
   */
  public function __construct($app, $year = null)
  {
    $this->application = $app;
    $this->year        = $year;
  }

  /**
   * Returns a string for uninstalling the application from a Mac.
   *
   * @return string
   */
  public function getUninstall()
  {
    return str_replace(['{sap}', '{version}'], [$this->getSap(), $this->getBaseVersion()], self::UNINSTALL);
  }

  /**
   * Returns the SAP code for the application, as defined by Adobe and taken from cc.json.
   *
   * @return string
   */
  public function getSap()
  {
    $info = $this->getAppInfo($this->application);

    return isset($info['sap']) ? $info['sap'] : null;
  }

  /**
   * Returns the year of this application, if applicable.  Note that some Creative Cloud apps do not use a year.
   *
   * @return string
   */
  public function getYear()
  {
    return $this->year;
  }

  /**
   * @return string[] Returns an array of relative paths, relative to a Mac user folder
   */
  public function getPreferences()
  {
    $prefs = [];
    if ($info = $this->getAppInfo($this->application))
    {
      if (!empty($info['preferences']))
      {
        foreach ($info['preferences'] as $pr)
        {
          $prefs[] = str_replace(['{name}', '{year}', '{version}'], [$this->getName(false), $this->getYear(), $this->getBaseVersion()], $pr);
        }
      }
    }

    return $prefs;
  }

  /**
   * Returns the base version of this application, based on data from cc.json.
   *
   * @return string
   */
  public function getBaseVersion()
  {
    if (empty($this->baseVersion))
    {
      if ($info = $this->getAppInfo($this->application))
      {
        $crit = ($year = $this->getYear()) ? $year : $this->getName();

        foreach ($info['baseVersions'] as $ver => $vYear)
        {
          if ($vYear == $crit)
          {
            $this->baseVersion = $ver;
          }
        }
      }
    }

    return $this->baseVersion;
  }

  /**
   * @return string
   */
  public function getVersion()
  {
    if (empty($this->version))
    {
      if ($path = $this->getPath())
      {
        $plist = sprintf('%s/Contents/Info.plist', $path);
        $cmd   = sprintf('/usr/bin/defaults read "%s" CFBundleVersion', $plist);

        if ($bundleVersion = shell_exec($cmd))
        {
          $this->version = trim($bundleVersion);
        }
      }
    }

    return $this->version;
  }

  /**
   * Returns the name of this application, including the year if applicable.
   *
   * @param bool $onlyInstalled only return values for applications that are installed
   *
   * @return string|null
   */
  public function getFullName($onlyInstalled = true)
  {
    return ($name = $this->getName($onlyInstalled)) ? trim($name.' '.$this->getYear()) : null;
  }

  /**
   * Returns the name of this application.
   *
   * @param bool $onlyInstalled only return values for applications that are installed
   *
   * @return string
   */
  public function getName($onlyInstalled = true)
  {
    if (empty($this->name))
    {
      if ($values = $this->getInstalledPathAndName())
      {
        $this->path = $values['path'];
        $this->name = $values['name'];
      }
      elseif ($values = $this->getEstimatedPathAndName())
      {
        if (is_dir($values['path']))
        {
          $this->path = $values['path'];
          $this->name = $values['name'];
        }
        elseif (!$onlyInstalled)
        {
          return $values['name'];
        }
      }
    }

    return $this->name;
  }

  /**
   * Returns the posix path of this application.
   *
   * @param bool $onlyInstalled only return values for applications that are installed
   *
   * @return string
   */
  public function getPath($onlyInstalled = true)
  {
    if (empty($this->path))
    {
      if ($values = $this->getInstalledPathAndName())
      {
        $this->path = $values['path'];
        $this->name = $values['name'];
      }
      elseif ($values = $this->getEstimatedPathAndName())
      {
        $path = sprintf('/Applications/%s', $values['path']);

        if (is_dir($path))
        {
          $this->path = $path;
          $this->name = $values['name'];
        }
        elseif (!$onlyInstalled)
        {
          return $values['path'];
        }
      }
    }

    return $this->path;
  }

  /**
   * Returns the estimated path and name of this application, derived from the 'path' key in cc.json.
   *
   * @return string[] An array with two keys, 'path' and 'name'
   */
  private function getEstimatedPathAndName()
  {
    // I guess we'll estimate, since the application isn't installed
    if ($info = $this->getAppInfo($this->application))
    {
      $name = ($this->getYear() && $this->getYear() < 2020) ? $info['names'][1] : $info['names'][0];
      $path = str_replace(['{name}', '{year}'], [$name, $this->getYear()], $info['path']);

      return ['name' => $name, 'path' => $path];
    }

    return null;
  }

  /**
   * Returns the path of this application, as verified to exist.
   *
   * @return string[]|null An array with two keys, 'path' and 'name'
   */
  private function getInstalledPathAndName()
  {
    if ($info = $this->getAppInfo($this->application))
    {
      foreach ($info['names'] as $name)
      {
        foreach (self::PATH_TEMPLATES as $tmpl)
        {
          $file = str_replace(['{name}', '{year}'], [$name, $this->getYear()], $tmpl);

          if (file_exists($file))
          {
            return [
                'path' => dirname(dirname($file)),
                'name' => $name,
            ];
          }
        }
      }
    }

    return null;
  }

  /**
   * Returns an array of info for the given app, taken from cc.json.
   *
   * @param string $str The application key, such as 'photoshop' or 'after-effects'
   *
   * @return array|null
   */
  private function getAppInfo($str)
  {
    if (empty($this->appInfo))
    {
      $norm = $this->normalizeKey($str);
      $json = json_decode(file_get_contents($this->getProjectRoot().'/resources/config/cc.json'), true);
      foreach ($json as $key => $info)
      {
        if (empty($this->appInfo) && $key == $norm)
        {
          $this->appInfo = $info;
        }
      }

      if (empty($this->appInfo))
      {
        if ($info = $this->getAppInfoFromAdbArg($str))
        {
          $this->appInfo = $info;
        }
      }
    }

    return $this->appInfo;
  }

  private function getAppInfoFromAdbArg($str)
  {
    $dir   = '/Library/Application Support/Adobe/Uninstall';
    $years = ['2015', '2017', '2018', '2018', '2019', '2020', '2021', '2022'];
    $bVer  = [];
    $names = [];
    $paths = [];

    if (is_dir($dir))
    {
      foreach (glob($dir.'/*.adbarg') as $adbarg)
      {
        $contents = file_get_contents($adbarg);
        $lines    = explode("\n", $contents);
        $tInfo    = [];
        unset($tYear);
        foreach ($lines as $line)
        {
          if (preg_match('#^--([^=]+)=(.*)$#', $line, $matches))
          {
            $key         = $matches[1];
            $tInfo[$key] = $matches[2];
          }
        }

        if (!empty($tInfo['productName']))
        {
          $tStr = $this->normalizeKey($tInfo['productName']);
          if ($tStr == $str)
          {
            $nme = $tInfo['productName'];
            $sap = !empty($tInfo['sapCode']) ? $tInfo['sapCode'] : null;
            $ver = !empty($tInfo['productVersion']) ? $tInfo['productVersion'] : null;

            foreach (self::PATH_TEMPLATES as $template)
            {
              foreach ($years as $year)
              {
                $plist = str_replace(['{name}', '{year}'], [$nme, $year], $template);

                if (file_exists($plist))
                {
                  if (false !== strpos($plist, $year))
                  {
                    $tYear = $year;
                  }

                  $paths[] = dirname(dirname($template));
                }
              }
            }

            $names[]    = $tInfo['productName'];
            $bVer[$ver] = isset($tYear) ? $tYear : $tInfo['productName'];
          }
        }
      }
    }

    if (isset($sap) && !empty($names) && !empty($bVer) && !empty($paths))
    {
      return ['sap' => $sap, 'names' => $names, 'baseVersions' => $bVer, 'path' => array_unique($paths)];
    }

    return null;
  }

  private function normalizeKey($key)
  {
    return str_replace([' ', '_'], '-', strtolower($key));
  }

  private function getProjectRoot()
  {
    if ($phar = \Phar::running(true))
    {
      return $phar;
    }
    else
    {
      $dir = __DIR__;
      while (!file_exists($dir.'/composer.json'))
      {
        if ($dir === dirname($dir))
        {
          throw new \Exception('The project directory could not be determined.  You must have a "composer.json" file in the project root!');
        }

        $dir = dirname($dir);
      }

      return $dir;
    }
  }
}
