<?php


namespace DevCoding\Mac\Command;


use DevCoding\Command\Base\AbstractConsole;
use DevCoding\Mac\Objects\CreativeCloudApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AdobeBackupCommand extends AbstractAdobeConsole
{
  public function configure()
  {
    $this->setName('adobe:backup');
    $this->setDescription('Backs up the preferences of an Adobe Creative Cloud application.');
    $this->addArgument('application', InputArgument::REQUIRED);
    $this->addArgument('year', InputArgument::OPTIONAL);

    parent::configure();
  }

  public function execute(InputInterface $input, OutputInterface $output)
  {
    $app   = strtolower(str_replace(" ", "-", $this->io()->getArgument('application')));
    $year  = $this->io()->getArgument('year');
    $ccApp = $this->getCreativeCloudApp($app, $year);
    $this->io()->blankln();

    $this->io()->msg('Locating Adobe Application', 50);
    if ($ccApp->getPath())
    {
      $this->io()->successln('[DONE]');

      try
      {
        $this->io()->msg('Backing Up '.$ccApp->getFullName().' Preferences',50);
        $this->doPreferenceBackup($ccApp, $this->getBackupPath($app, $year));
        $this->io()->successln('[DONE]');
      }
      catch(\Exception $e)
      {
        $this->io()->errorln('[ERROR]');
        $this->io()->errorblk($e->getMessage());

        return self::EXIT_ERROR;
      }
    }
    else
    {
      $this->io()->errorln('[ERROR]');

      $this->io()->errorblk('Could not locate the requested application.');

      return self::EXIT_ERROR;
    }

    return self::EXIT_SUCCESS;
  }
}