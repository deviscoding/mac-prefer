<?php

namespace DevCoding\Mac\Command;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class MenuAddCommand extends AbstractMacConsole
{
  const TEMPLATE_MENU      = '/System/Library/CoreServices/Menu Extras/%s.menu';
  const TEMPLATE_STATUSKEY = '"NSStatusItem Visible com.apple.menuextra.%s"';
  const OPTION_MENU        = 'menu';

  protected function isAllowUserOption()
  {
    return true;
  }

  protected function configure()
  {
    $this->setName('menu:add');
    $this->setDescription('Adds a menubar widget.');
    $this->addArgument('menu', InputArgument::REQUIRED, 'The name of the menu to add.');

    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    if ($menu = $input->getArgument(self::OPTION_MENU))
    {
      $path = $this->getMenuFilePath($menu);
      if (!file_exists($path))
      {
        $alt = $this->getAlteratives($menu, dirname($path));
        if (!empty($alt))
        {
          if (is_string($alt))
          {
            $input->setArgument(self::OPTION_MENU, $alt[0]);
          }
          else
          {
            $msg = sprintf('The menu "%s" was not found.  Did you mean one of: "%s"?', $menu, implode(', ', $alt));
            throw new InvalidArgumentException($msg);
          }
        }
        else
        {
          $msg = sprintf('The menu "%s" was not found.', $menu );

          throw new InvalidArgumentException($msg);
        }

      }
    }

    parent::interact($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();
    $this->io()->msg('Validating Menu File...', 40);
    if ($menuFile = $this->getMenuFile())
    {
      $this->io()->successln('[SUCCESS]');
      try
      {
        $this->io()->msg('Writing menuExtras Default...', 40);
        $this->writeUserDefault('com.apple.systemuiserver', 'menuExtras', sprintf('-array-add "%s"', $menuFile));
        $this->io()->successln('[SUCCESS]');
      }
      catch (\Exception $e)
      {
        $this->io()->errorln('[FAILED]');
        $this->io()->errorblk($e->getMessage());

        return self::EXIT_ERROR;
      }

      try
      {
        $key = $this->getStatusKey();
        $this->io()->msg('Writing Visibility Default...', 40);
        $this->writeUserDefault('com.apple.systemuiserver', $key, '-bool true');
        $this->io()->successln('[SUCCESS]');
      }
      catch (\Exception $e)
      {
        $this->io()->errorln('[FAILED]');
        $this->io()->errorblk($e->getMessage());

        return self::EXIT_ERROR;
      }

      $this->io()->msg('Launching Menu...', 40);
      try
      {
        $launchctl = $this->getBinaryPath('launchctl');
        $open      = $this->getBinaryPath('open');
        $id        = $this->getLaunchUserId();
        $method    = $this->getLaunchMethod();
        $command   = sprintf('%s %s %s %s "%s" > /dev/null 2>&1 &', $launchctl, $method, $id, $open, $menuFile);
        exec($command);
        $this->io()->successln('[SUCCESS]');
        $this->io()->blankln();
      }
      catch (\Exception $e)
      {
        $this->io()->errorln('[FAILED]');
        $this->io()->errorblk($e->getMessage());

        return self::EXIT_ERROR;
      }
    }
    else
    {
      $this->io()->errorln('[ERROR]');
      $this->io()->errorblk(sprintf(
          'The menu "%s" does not exist at "%s"',
          $this->io()->getArgument(self::OPTION_MENU),
          $this->getMenuFilePath($this->io()->getArgument(self::OPTION_MENU))
      ));

      return self::EXIT_ERROR;
    }

    return self::EXIT_SUCCESS;
  }

  protected function writeUserDefault($domain, $key, $value)
  {
    $uDom  = sprintf('%s/Preferences/%s', $this->getUser()->getLibrary(), $domain);
    $uFile = sprintf('%s.plist', $uDom);
    $cmd   = sprintf('%s write "%s" %s %s', $this->getBinaryPath('defaults'), $uDom, $key, $value);

    $P = Process::fromShellCommandline($cmd);
    $P->run();

    if (!$P->isSuccessful())
    {
      $error = $P->getErrorOutput();
      if (empty($error))
      {
        $error = $P->getOutput();
      }

      throw new \Exception($error);
    }

    if (file_exists($uFile))
    {
      $this->setUserAsOwner($uFile, $this->getUser());
    }
    else
    {
      throw new \Exception(sprintf('Setting Default for Domain %s failed.  File does not exist!', $uDom));
    }
  }

  protected function getAlteratives($menu, $dir)
  {
    $threshold    = 1e3;
    $collection   = [];
    $alternatives = [];
    foreach (glob($dir.'/*.menu') as $file)
    {
      $base = basename($file, '.menu');
      if (strtolower($base) == strtolower($menu))
      {
        return $base;
      }
      elseif (false !== stripos($base, $menu))
      {
        $alternatives[$base] = 1;
      }
      else
      {
        $collection[$base] = $file;
      }
    }

    foreach ($collection as $key => $item)
    {
      $exists = isset($alternatives[$key]);
      $lev    = levenshtein($menu, $key);
      if ($lev <= strlen($menu) / 3 || '' !== $menu && false !== strpos($item, $menu))
      {
        $alternatives[$key] = $exists ? $alternatives[$key] + $lev : $lev;
      }
      elseif ($exists)
      {
        $alternatives[$key] += $threshold;
      }
    }

    $alternatives = array_filter($alternatives, function ($lev) use ($threshold) {
      return $lev < 2 * $threshold;
    });
    ksort($alternatives, SORT_NATURAL | SORT_FLAG_CASE);

    return array_keys($alternatives);
  }

  protected function getStatusKey()
  {
    return sprintf(self::TEMPLATE_STATUSKEY, strtolower($this->io()->getArgument(self::OPTION_MENU)));
  }

  protected function getMenuFile()
  {
    $menuFile = $this->getMenuFilePath($this->io()->getArgument(self::OPTION_MENU));

    return file_exists($menuFile) ? $menuFile : null;
  }

  protected function getMenuFilePath($menu)
  {
    return sprintf(self::TEMPLATE_MENU, $menu);
  }
}
