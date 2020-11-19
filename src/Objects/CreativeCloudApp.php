<?php
/**
 * CreativeCloudApp.php
 */

namespace DevCoding\Mac\Objects;

/**
 * PHP object representing an Adobe Creative Cloud application.
 *
 * Class CreativeCloudApp
 *
 * @author  Aaron M Jones <am@jonesiscoding.com>
 * @package DevCoding\Mac\Objects
 */
class CreativeCloudApp implements \JsonSerializable {

  const PATH_TEMPLATES = [
      '/Applications/Adobe {name} {year}/Adobe {name} {year}.app/Contents/Info.plist',
      '/Applications/Adobe {name} {year}/Adobe {name}.app/Contents/Info.plist',
      '/Applications/Adobe {name}/Adobe {name}.app/Contents/Info.plist',
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
   * {@inheritDoc}
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
        'uninstall'   => $this->getUninstall()
    ];
  }

  /**
   * @param string $app   Lowercase, dash separated
   * @param year   $year
   */
  public function __construct($app, $year = null) {
    $this->application = $app;
    $this->year = $year;
  }

  /**
   * Returns a string for uninstalling the application from a Mac
   *
   * @return string
   */
  public function getUninstall()
  {
    return str_replace(['{sap}', '{version}'], [$this->getSap(), $this->getBaseVersion()], self::UNINSTALL);
  }

  /**
   * Returns the SAP code for the application, as defined by Adobe and taken from cc.json
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
   * @return string[]   Returns an array of relative paths, relative to a Mac user folder
   */
  public function getPreferences()
  {
    $prefs = [];
    $info  = $this->getAppInfo($this->application);
    foreach($info['preferences'] as $pr)
    {
      $prefs[] = str_replace(['{name}', '{year}', '{version}'], [$this->getName(false), $this->getYear(), $this->getBaseVersion()], $pr);
    }

    return $prefs;
  }

  /**
   * Returns the base version of this application, based on data from cc.json
   *
   * @return string
   */
  public function getBaseVersion()
  {
    if (empty($this->baseVersion))
    {
      $info = $this->getAppInfo($this->application);
      $crit = ($year = $this->getYear()) ? $year : $this->getName();

      foreach ($info['baseVersions'] as $ver => $vYear)
      {
        if ($vYear == $crit)
        {
          $this->baseVersion = $ver;
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
        $plist = sprintf("%s/Contents/Info.plist", $path);
        $cmd = sprintf('/usr/bin/defaults read "%s" CFBundleVersion', $plist);

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
   * @param bool $onlyInstalled  Only return values for applications that are installed.
   *
   * @return string|null
   */
  public function getFullName($onlyInstalled = true)
  {
    return ($name = $this->getName($onlyInstalled)) ? trim($name . ' ' . $this->getYear()) : null;
  }

  /**
   * Returns the name of this application.
   *
   * @param bool $onlyInstalled  Only return values for applications that are installed.
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
      else
      {
        if (is_dir($values['path']))
        {
          $this->path = $values['path'];
          $this->name = $values['name'];
        }
        elseif (!$onlyInstalled)
        {
          return $values['path'];
        }
      }
    }

    return $this->name;
  }

  /**
   * Returns the posix path of this application
   *
   * @param bool $onlyInstalled  Only return values for applications that are installed.
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
      else
      {
        $values = $this->getEstimatedPathAndName();
        $path   = sprintf('/Applications/%s', $values['path']);

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
   * Returns the estimated path and name of this application, derived from the 'path' key in cc.json
   *
   * @return string[]  An array with two keys, 'path' and 'name'
   */
  private function getEstimatedPathAndName()
  {
    // I guess we'll estimate, since the application isn't installed
    $info = $this->getAppInfo($this->application);
    $name = $this->getYear() < 2020 ? $info['names'][1] : $info['names'][0];
    $path = str_replace(['{name}', '{year}'], [$name, $this->getYear()], $info['path']);

    return [ 'name' => $name, 'path' => $path ];
  }

  /**
   * Returns the path of this application, as verified to exist.
   *
   * @return string[]|null An array with two keys, 'path' and 'name'
   */
  private function getInstalledPathAndName()
  {
    $info = $this->getAppInfo($this->application);
    foreach($info['names'] as $name)
    {
      foreach(self::PATH_TEMPLATES as $tmpl)
      {
        $file = str_replace(['{name}', '{year}'], [$name, $this->getYear()], $tmpl);

        if (file_exists($file))
        {
          return [
              'path' => dirname(dirname($file)),
              'name' => $name
          ];
        }
      }
    }

    return null;
  }

  /**
   * Returns an array of info for the given app, taken from cc.json
   *
   * @param string $str   The application key, such as 'photoshop' or 'after-effects'
   *
   * @return array|null
   */
  private function getAppInfo($str)
  {
    if (empty($this->appInfo))
    {
      $json = json_decode(file_get_contents(__DIR__.' ../../../resources/config/cc.json'), true);
      foreach($json as $key => $info)
      {
        if ($key == strtolower($str))
        {
          $this->appInfo = $info;
        }
      }
    }

    return $this->appInfo;
  }
}