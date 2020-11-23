<?php


namespace DevCoding\Mac\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AdobeTransferCommand extends AbstractAdobeConsole
{
  public function configure()
  {
    $this
        ->setName('adobe:transfer')
        ->setDescription('Transfers the preferences from one year of a Creative Cloud application to another year.')
        ->addArgument('application', InputArgument::REQUIRED)
        ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The source to copy preferences FROM')
        ->addOption('to', null, InputOption::VALUE_REQUIRED, 'The destination to copy preferences TO')
    ;

    // Adds the User Option
    parent::configure();
  }

  public function interact(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();
    parent::interact($input, $output);

    if (!$app = $this->io()->getArgument('application'))
    {
      $app = $this->io()->ask('What Adobe application should we transfer preferences for?');
    }

    // Sanitize the Application Input
    if (!empty($app))
    {
      // Strip out the year
      if (preg_match('#(2[0-9]{3})#', $app, $matches))
      {
        $dYr = $matches[1];
        $app = str_replace($dYr, '', $app);
      }

      // Sanitize the input so it is is 'after-effects' not 'Adobe After Effects CC'
      $app = str_replace(' ', '-', trim(str_replace(['cc', 'adobe'], '', strtolower($app))));
      $this->io()->getInput()->setArgument('application', $app);
    }

    if (!$from = $this->io()->getOption('from'))
    {
      $default = isset($dYr) ? $dYr : null;

      if ($from = $this->io()->ask('What year should we copy preferences FROM?', $default))
      {
        $this->io()->getInput()->setOption('from',$from);
      }
    }

    if (!$to = $this->io()->getOption('to'))
    {
      if ($to = $this->io()->ask('What year should we copy preferences TO?'))
      {
        $this->io()->getInput()->setOption('to', $to);
      }
    }
  }

  public function validate(InputInterface $input, OutputInterface $output)
  {
    if ($from = $input->getOption('from'))
    {
      if (!is_numeric($from) || (int)$from < 2015)
      {
        throw new \Exception('You must use a year greater than 2015 for the "--from" option.');
      }
    }
    else
    {
      throw new \Exception('You must use the "--from" option to indicate the year to copy preferences from.');
    }

    if ($to = $input->getOption('to'))
    {
      if (!is_numeric($to) || (int)$to < 2015)
      {
        throw new \Exception('You must use a year greater than 2015 for the "--from" option.');
      }
    }
    else
    {
      throw new \Exception('You must use the "--from" option to indicate the year to copy preferences from.');
    }

    if ((int)$from == (int)$to)
    {
      throw new \Exception('You must indicate a different year for the "--from" and "--to" options.');
    }
  }

  public function execute(InputInterface $input, OutputInterface $output)
  {
    try
    {
      $this->validate($input, $output);
    }
    catch(\Exception $e)
    {
      $this->io()->errorblk($e->getMessage());

      return self::EXIT_ERROR;
    }

    $app  = $this->io()->getArgument('application');
    $from = $this->io()->getOption('from');
    $to   = $this->io()->getOption('to');
    $src  = $this->getCreativeCloudApp($app, $from);
    $dst  = $this->getCreativeCloudApp($app, $to);
    $this->io()->blankln();

    // Get Source Preferences
    $this->io()->msg('Locating Source Preferences', 50);
    $srcPrefs = $src->getPreferences();
    if (!empty($srcPrefs))
    {
      $this->io()->successln('[DONE]');
    }
    else
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk(sprintf('Could not locate preferences for %s', $src->getFullName(false)));

      return self::EXIT_ERROR;
    }

    // Version Replacement
    $this->io()->msg('Verifying Application Versions', 50);
    $srcVer = $src->getBaseVersion();
    $dstVer = $dst->getBaseVersion();
    if (empty($srcVer) || empty($dstVer))
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk(sprintf('Could not verify versions for %s', $src->getName(false)));

      return self::EXIT_ERROR;
    }
    else
    {
      $this->io()->successln('[DONE]');
    }

    // Year Replacement
    $this->io()->msg('Verifying Application Year', 50);
    $srcYear = $src->getYear();
    $dstYear = $dst->getYear();
    if ((!empty($srcYear) && empty($dstYear)) || (empty($srcYear) && !empty($dstYear)))
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk(sprintf('Could not verify years for %s', $src->getName(false)));

      return self::EXIT_ERROR;
    }
    else
    {
      $this->io()->successln('[DONE]');
    }

    // Name Replacement
    $this->io()->msg('Verifying Application Names', 50);
    $srcName = $src->getName(false);
    $dstName = $dst->getName(false);
    if (empty($srcName) || empty($dstName))
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk('Could not verify names for these applications.');

      return self::EXIT_ERROR;
    }
    else
    {
      $this->io()->successln('[DONE]');
    }

    // Prep Search & Replace
    $search  = [$srcVer, $srcName];
    $replace = [$dstVer, $dstName];
    if (!empty($srcYear))
    {
      $search[]  = $srcYear;
      $replace[] = $dstYear;
    }

    // Backup Previous Destination Preference
    $this->io()->msg('Backing Up Destination Preferences', 50);
    try
    {
      $this->doPreferenceBackup($dst, $this->getBackupPath($app, $to));
    }
    catch(\Exception $e)
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk($e->getMessage());

      return self::EXIT_ERROR;
    }
    $this->io()->successln('[DONE]');

    // Copy Preferences
    $this->io()->msg(sprintf('Copying %s Prefs from %s to %s', $srcName, $from, $to),50);
    try
    {
      $this->copyPreferences($srcPrefs, $search, $replace);
    }
    catch(\Exception $e)
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk($e->getMessage());

      return self::EXIT_ERROR;
    }
    $this->io()->successln('[DONE]');
    $this->io()->blankln();
    $this->io()->successln(sprintf(
        'Preferences have successfully been copied from Adobe %s to Adobe %s.',
        $src->getFullName(false),
        $dst->getFullName(false)
    ));
    $this->io()->blankln();

    return self::EXIT_SUCCESS;
  }

  protected function copyPreferences($srcPrefs, $search, $replace)
  {
    $uDir = $this->getUser()->getDir();
    foreach($srcPrefs as $srcPref)
    {
      $dstPref = str_replace($search, $replace, $srcPref);
      $srcPath = sprintf("%s/%s", $uDir, $srcPref);
      $dstPath = sprintf("%s/%s", $uDir, $dstPref);

      if (file_exists($srcPath))
      {
        // Use Rsync for Directories
        if (is_dir($srcPath))
        {
          if (!is_dir($dstPath))
          {
            if (!@mkdir($dstPath, 0777, true))
            {
              throw new \Exception(sprintf('Destination Path "%s" could not be created!', $dstPath));
            }
          }

          $cmd = sprintf('rsync -aP --ignore-times "%s/" "%s/"', $srcPath, $dstPath);
          exec($cmd,$output,$retval);

          if ($retval !== 0)
          {
            throw new \Exception(sprintf('An error was encountered copying preference directory "%s"', implode("\n",$output)));
          }
        }
        elseif(is_file($srcPath))
        {
          if(is_file($dstPref)) { unlink($dstPath); }
          if (!copy($srcPath, $dstPath))
          {
            throw new \Exception(sprintf('An error was encountered copying %s', $srcPath));
          }
          else
          {
            try
            {
              $owner = posix_getpwuid(fileowner($srcPath));
              $group = posix_getgrgid(filegroup($srcPath));
              chown($dstPath, $owner['name']);
              chgrp($dstPath, $group['name']);
            }
            catch(\Exception $e)
            {
              throw new \Exception(sprintf('An error was encountered setting permissions on "%s"',$dstPath));
            }
          }
        }
      }
    }
  }
}