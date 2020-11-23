<?php

namespace DevCoding\Mac\Command;

use CFPropertyList\CFPropertyList;
use DevCoding\Mac\Tools\AdobeLocator;
use DevCoding\Mac\Tools\UtilityLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DockCommand.
 *
 * @package DevCoding\Mac\Command
 */
class DockDumpCommand extends AbstractPreferConsole
{
  /** @var AdobeLocator[]|UtilityLocator[] */
  protected $handlers = [AdobeLocator::class, UtilityLocator::class];

  // region //////////////////////////////////////////////// Symfony Console Methods

  protected function configure()
  {
    parent::configure();

    $this->setName('dock:dump');
    $this->addOption(self::INPUT, null, InputOption::VALUE_REQUIRED, 'The plist file to use for the source.');
    $this->addOption(self::OUTPUT, null, InputOption::VALUE_REQUIRED, 'The file to which to dump the dock config.');
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    parent::interact($input, $output);

    // Get PList Source
    if (!$source = $input->getOption(self::INPUT))
    {
      $source = sprintf('%s/Preferences/com.apple.dock.plist', $this->getUserLibraryDir());

      if (file_exists($source))
      {
        $input->setOption(self::INPUT, $source);
      }
    }

    if (!$input->getOption(self::OUTPUT))
    {
      $input->setOption(self::OUTPUT, $this->getDefaultConfigPath());
    }
  }

  /**
   * @param InputInterface  $input
   * @param OutputInterface $output
   *
   * @return int|void
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();

    // Set the config file property
    $this->configFile = $this->io()->getOption(self::OUTPUT);

    // Parse the plist file into an array configuration
    if ($source = $this->io()->getOption(self::INPUT))
    {
      $this->io()->msg('Getting Dock Config from OS', 30);
      if ($array = $this->getPListAsArray($source))
      {
        $this->io()->successln('[SUCCESS]');
        $this->io()->msg('Parsing Dock Config', 30);

        if (array_key_exists('persistent-apps', $array))
        {
          $apps = $this->parseDock($array['persistent-apps'], 'persistent-apps');
        }

        if (array_key_exists('persistent-others', $array))
        {
          $start = isset($apps) ? count($apps) + 1 : 0;
          $other = $this->parseDock($array['persistent-others'], 'persistent-others', $start);

          $apps = isset($apps) ? $apps + $other : $other;
        }

        $this->io()->successln('[SUCCESS]');
      }
      else
      {
        $this->io()->errorln('[ERROR]');
      }
    }

    if (!empty($apps))
    {
      $this->io()->msg('Saving Configuration', 30);
      $this->config = $apps;
      $this->writeConfig();
      $this->io()->successln('[SUCCESS]');
    }

    return self::EXIT_SUCCESS;
  }

  // region //////////////////////////////////////////////// Config Parsing Methods

  protected function getDefaultConfigFileName()
  {
    return 'dock';
  }

  protected function parseDock($in, $section = 'persistent-apps', $start = 0)
  {
    $x      = $start;
    $gId    = 'group'.$x;
    $groups = [];

    foreach ($in as $item)
    {
      if (array_key_exists('tile-type', $item))
      {
        if ('small-spacer-tile' == $item['tile-type'])
        {
          ++$x;
          $gId = 'group'.$x;
        }
        elseif ('file-tile' == $item['tile-type'])
        {
          $fileData = $item['tile-data']['file-data'];

          if ('persistent-apps' == $section)
          {
            $groups[$gId][] = $this->parseUrlString($fileData['_CFURLString']);
          }
          else
          {
            $groups[$gId][] = [
                'link'    => $this->parseUrlString($fileData['_CFURLString']),
                'section' => str_replace('persistent-', '', $section),
            ];
          }
        }
        elseif ('directory-tile' == $item['tile-type'])
        {
          $groups[$gId][] = $this->parseDirectoryTile($item['tile-data'], $section);
        }
      }
    }

    return $groups;
  }

  /**
   * @param        $tileData
   * @param string $section
   *
   * @return array
   */
  protected function parseDirectoryTile($tileData, $section = 'persistent-apps')
  {
    $fileData = $tileData['file-data'];
    $config   = [
        'link'    => $this->parseUrlString($fileData['_CFURLString']),
        'section' => str_replace('persistent-', '', $section),
    ];

    if (array_key_exists('displayas', $tileData))
    {
      switch ($tileData['displayas'])
      {
        case 0:
          $config['display'] = 'stack';
          break;
        case 1:
        default:
          $config['display'] = 'folder';
          break;
      }
    }

    if (array_key_exists('showas', $tileData))
    {
      switch ($tileData['showas'])
      {
        case 1:
          $config['view'] = 'fan';
          break;
        case 2:
          $config['view'] = 'grid';
          break;
        case 3:
          $config['view'] = 'list';
          break;
        case 4:
        default:
          $config['view'] = 'auto';
          break;
      }
    }

    if (array_key_exists('arrangement', $tileData))
    {
      switch ($tileData['arrangement'])
      {
        case 1:
          $config['sort'] = 'name';
          break;
        case 2:
          $config['sort'] = 'dateadded';
          break;
        case 3:
          $config['sort'] = 'datemodified';
          break;
        case 4:
          $config['sort'] = 'datecreated';
          break;
        case 5:
        default:
          $config['sort'] = 'kind';
          break;
      }
    }

    return $config;
  }

  /**
   * @param string $str
   *
   * @return string
   */
  protected function parseUrlString($str)
  {
    if (preg_match('#file:///Applications/(.*).app/#', $str, $matches))
    {
      // Application
      return $this->matchReverse($matches[1]);
    }
    elseif (preg_match('#file:///Users/(.*)/Applications/(.*).app/#', $str, $matches))
    {
      // User Applications
      return urldecode(sprintf('~/Applications/%s', $matches[2]));
    }
    elseif (preg_match('#file://(.*)$#', $str, $matches))
    {
      // Other Paths
      return $this->matchReverse($matches[1]);
    }

    return $str;
  }

  // region //////////////////////////////////////////////// PList Methods

  protected function matchReverse($str)
  {
    $app = urldecode($str);

    foreach ($this->handlers as $handler)
    {
      if ($key = $handler::getReverse($app))
      {
        return $key;
      }
    }

    return $app;
  }

  protected function getPListAsArray($plist)
  {
    if (is_file($plist))
    {
      $PList = new CFPropertyList($plist);

      return $PList->toArray();
    }

    return null;
  }

  protected function readDock()
  {
  }

  // endregion ///////////////////////////////////////////// End PList Methods
}
