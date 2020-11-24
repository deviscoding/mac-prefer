<?php

namespace DevCoding\Mac\Tools;

use DevCoding\Mac\Objects\CreativeCloudApp;

class AdobeLocator
{
  const OLDEST  = 2014;
  const HANDLES = [
      'photoshop'     => 'Photoshop',
      'indesign'      => 'InDesign',
      'illustrator'   => 'Illustrator',
      'bridge'        => 'Bridge',
      'acrobat'       => 'Acrobat',
      'after_effects' => 'After Effects',
      'dimension'     => 'Dimension',
      'premiere_pro'  => 'Premiere Pro',
      'xd'            => 'XD',
      'lightroom'     => 'Lightroom',
      'distiller'     => 'Distiller',
  ];

  public static function handles($str)
  {
    return array_key_exists($str, self::HANDLES);
  }

  public static function getLatest($key)
  {
    if (self::handles($key))
    {
      $name = self::HANDLES[$key];

      if ('Acrobat' == $name)
      {
        return self::getLatestAcrobat();
      }

      if ('Distiller' == $name)
      {
        return self::getLatestDistiller();
      }

      $year = date('Y') + 1;

      do
      {
        $App = new CreativeCloudApp($name, $year);

        if ($path = $App->getPath())
        {
          return $path;
        }
        else
        {
          $year = $year - 1;
        }
      } while ($year >= self::OLDEST);
    }

    return null;
  }

  public static function getLatestAcrobat()
  {
    $try = [
        '/Applications/Adobe Acrobat DC/Adobe Acrobat.app',
        '/Applications/Adobe Acrobat XI Pro/Adobe Acrobat Pro.app',
        '/Applications/Adobe Acrobat Reader DC.app',
        '/Applications/Adobe Acrobat.app',
    ];

    foreach ($try as $app)
    {
      if (is_dir($app))
      {
        return $app;
      }
    }

    return null;
  }

  public static function getLatestDistiller()
  {
    $try = [
        '/Applications/Adobe Acrobat DC/Acrobat Distiller.app',
        '/Applications/Adobe Acrobat XI Pro/Acrobat Distiller.app',
    ];

    foreach ($try as $app)
    {
      if (is_dir($app))
      {
        return $app;
      }
    }

    return null;
  }

  public static function getReverse($str)
  {
    if (false !== strpos($str, 'Acrobat Distiller'))
    {
      return 'distiller';
    }

    if (false !== strpos($str, 'Adobe Acrobat'))
    {
      return 'acrobat';
    }

    if (preg_match('#Adobe\s([A-Za-z\s]+)([0-9]+)#', $str, $matches))
    {
      $long = trim(str_replace('CC', '', $matches[1]));

      foreach (self::HANDLES as $key => $app)
      {
        if ($app == $long)
        {
          return $key;
        }
      }
    }

    return null;
  }
}
