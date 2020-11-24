<?php

namespace DevCoding\Mac\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdobeInfoCommand extends AbstractAdobeConsole
{
  protected function isAllowUserOption()
  {
    return false;
  }

  public function configure()
  {
    $this->setName('adobe:info');
    $this->setDescription('Provides information about the installed Adobe application, in JSON format.');
    $this->addArgument('application', InputArgument::REQUIRED);
    $this->addArgument('year', InputArgument::OPTIONAL);
  }

  public function execute(InputInterface $input, OutputInterface $output)
  {
    $app   = strtolower(str_replace(' ', '-', $this->io()->getArgument('application')));
    $year  = $this->io()->getArgument('year');
    $ccApp = $this->getCreativeCloudApp($app, $year);

    if ($ccApp->getPath())
    {
      $this->io()->writeln(json_encode($ccApp, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
    }
    else
    {
      echo '{}';
    }

    return self::EXIT_SUCCESS;
  }
}
