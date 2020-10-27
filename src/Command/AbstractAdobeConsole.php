<?php


namespace DevCoding\Mac\Command;


use DevCoding\Command\Base\AbstractMacConsole;
use DevCoding\Mac\Objects\CreativeCloudApp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractAdobeConsole extends AbstractMacConsole
{
  public function configure()
  {
    $this->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'The user to run the command for.  Defaults to current user.');
  }

  public function interact(InputInterface $input, OutputInterface $output)
  {
    if ($input->hasOption('user'))
    {
      if ($user = $input->getOption('user'))
      {
        $this->setUser($user);
      }
    }
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
      return sprintf("%s/Preferences/Prefer/CC/%s/%s", $this->getUserLibraryDir(), $app, $year);
    }
    else
    {
      return sprintf("%s/Preferences/Prefer/CC/%s", $this->getUserLibraryDir(), $app);
    }
  }

  /**
   * @param CreativeCloudApp $ccApp
   * @param                  $dest
   *
   * @return bool
   */
  protected function doPreferenceBackup(CreativeCloudApp $ccApp, $dest)
  {
    $dest = $dest . '/tmp';
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

    $userDir = $this->getUserDir();
    foreach($ccApp->getPreferences() as $preference)
    {
      $path = sprintf("%s/%s", $userDir, $preference);
      if (file_exists($path))
      {
        $cmd = sprintf('rsync -aP --ignore-times "%s" "%s/"',$path,$dest);
        exec($cmd,$output,$retval);
        if ($retval !== 0)
        {
          throw new \Exception('An error was encountered backing up preferences: ');
        }
      }
    }

    $date = date( 'Ymd-Hi' );
    $zipFile = sprintf( 'backup-%s.zip', $date );
    $zipPath = dirname($dest) . '/' . $zipFile;
    $cmd = sprintf( 'cd "%s" && zip -r "%s" ./* && cd -', $dest, $zipPath );
    exec( $cmd,$output, $retval );
    if( $retval || !file_exists( $zipPath ) )
    {
      throw new \Exception( "Could not compress the backup after copying." );
    }
    else
    {
      $this->rrmdir($dest);
    }

    return true;
  }

  private function rrmdir($src)
  {
    if (is_dir($src))
    {
      $inTmp  = (strpos($src, '/tmp') === 0 && $src !== '/tmp');
      $inUser = (strpos($src, $this->getUserDir()) === 0 && $src !== $this->getUserDir());

      if (!$inTmp && !$inUser)
      {
        throw new \Exception(sprintf('The directory "%s" cannot be deleted with this application.',$src));
      }

      $dir = opendir($src);
      while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
          $full = $src . '/' . $file;
          if ( is_dir($full) ) {
            $this->rrmdir($full);
          }
          else {
            unlink($full);
          }
        }
      }
      closedir($dir);
      rmdir($src);
    }
  }

  private function rcopy( $src, $dst )
  {
    if (!is_dir($dst))
    {
      @mkdir( $dst );
    }

    if (is_dir($src))
    {
      $dir = opendir( $src );
      while( false !== ( $file = readdir( $dir ) ) )
      {
        if( ( $file != '.' ) && ( $file != '..' ) )
        {
          if( is_dir( $src . '/' . $file ) )
          {
            $this->rcopy( $src . '/' . $file, $dst . '/' . $file );
          }
          else
          {
            if( !copy( $src . '/' . $file, $dst . '/' . $file ) )
            {
              throw new \Exception( "Could not copy\n" . $src . "\nto\n" . $dst );
            }
          }
        }
      }
      closedir( $dir );
    }
    elseif(is_file($src))
    {
      copy($src, $dst.'/');
    }
  }
}