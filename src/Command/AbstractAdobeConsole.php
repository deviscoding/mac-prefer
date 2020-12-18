<?php

namespace DevCoding\Mac\Command;

use DevCoding\Mac\Objects\CreativeCloudApp;

class AbstractAdobeConsole extends AbstractMacConsole
{
  protected function isAllowUserOption()
  {
    return true;
  }

  /**
   * @param      $app
   * @param null $year
   *
   * @return CreativeCloudApp
   */
  protected function getCreativeCloudApp($app, $year = null)
  {
    return $year ? new CreativeCloudApp($app, $year) : new CreativeCloudApp($app);
  }

  protected function getBackupPath($app, $year = null)
  {
    if ($year)
    {
      return sprintf('%s/Preferences/Prefer/CC/%s/%s', $this->getUser()->getLibrary(), $app, $year);
    }
    else
    {
      return sprintf('%s/Preferences/Prefer/CC/%s', $this->getUser()->getLibrary(), $app);
    }
  }

  /**
   * @param CreativeCloudApp $ccApp
   * @param string           $dest
   *
   * @return bool
   *
   * @throws \Exception
   */
  protected function doPreferenceBackup($ccApp, $dest)
  {
    $dest = $dest.'/tmp';
    if (!is_dir($dest))
    {
      if (!file_exists($dest))
      {
        @mkdir($dest, 0777, true);
      }
      else
      {
        throw new \Exception('Could not create backup destination directory');
      }
    }

    $userDir = $this->getUser()->getDir();
    foreach ($ccApp->getPreferences() as $preference)
    {
      $path = sprintf('%s/%s', $userDir, $preference);
      if (file_exists($path))
      {
        $cmd = sprintf('rsync -aP --ignore-times "%s" "%s/"', $path, $dest);
        exec($cmd, $output, $retval);
        if (0 !== $retval)
        {
          throw new \Exception('An error was encountered backing up preferences: ');
        }
      }
    }

    $date    = date('Ymd-Hi');
    $zipFile = sprintf('backup-%s.zip', $date);
    $zipPath = dirname($dest).'/'.$zipFile;
    $cmd     = sprintf('cd "%s" && zip -r "%s" ./* && cd -', $dest, $zipPath);
    exec($cmd, $output, $retval);
    if ($retval || !file_exists($zipPath))
    {
      throw new \Exception('Could not compress the backup after copying.');
    }
    else
    {
      $this->rrmdir($dest);
    }

    return true;
  }
}
