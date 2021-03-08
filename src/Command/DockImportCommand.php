<?php

namespace DevCoding\Mac\Command;

use DevCoding\Mac\Objects\DockItem;
use DevCoding\Mac\Objects\DockSpacer;
use DevCoding\Mac\Objects\UserDock;
use DevCoding\Mac\Tools\AdobeLocator;
use DevCoding\Mac\Tools\UtilityLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class DockCommand.
 *
 * @package DevCoding\Mac\Command
 */
class DockImportCommand extends AbstractPreferConsole
{
    /** @var AdobeLocator[]|UtilityLocator[] */
    protected $handlers = [AdobeLocator::class, UtilityLocator::class];

    protected function configure()
    {
        $this->setName('dock:import');
        $this->addOption(self::INPUT, null, InputOption::VALUE_REQUIRED, 'The file from which to take the dock config.');
        $this->addOption(self::OUTPUT, null, InputOption::VALUE_REQUIRED, 'The file in which to dump the dock config.');

        parent::configure();
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        // Get Config File Source
        if (!$in = $this->io()->getOption(self::INPUT)) {
            $in = $this->getDefaultConfigPath();
            if (!file_exists($in) && OutputInterface::VERBOSITY_QUIET !== $this->io()->getVerbosity()) {
                $in = $this->io()->ask('What is the path to the configuration file?');
            }

            $this->io()->getInput()->setOption(self::INPUT, $in);
        }

        $this->interactOutput($in);
    }

    protected function getDefaultConfigFileName()
    {
        return 'dock';
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
        $this->io()->msg('Reading Configuration', 60);
        if ($source = $this->io()->getOption(self::INPUT)) {
            $this->setConfigFromFile($source);

            if (!$this->hasConfig()) {
                $this->io()->errorln('[ERROR]');
                $this->io()->errorblk('The specified configuration file could not be parsed.');

                return self::EXIT_ERROR;
            }
        } else {
            $msg = sprintf('No --input option was specified, and no config was found for the user "%s".', $this->getUser());
            $this->io()->errorln('[ERROR]');
            $this->io()->errorblk($msg);

            return self::EXIT_ERROR;
        }
        $this->io()->successln('[SUCCESS]');

        if ($dest = $this->io()->getOption(self::OUTPUT)) {
            // We already read the config, but we want to change the path to output correctly
            $this->configFile = $dest;

            if ($this->hasConfig() && $this->configFile != $source) {
                $this->io()->msg('Saving Configuration', 60);
                $this->writeConfig();
                $this->io()->successln('[SUCCESS]');
            }
        }

        $this->io()->msg('Parsing Configuration', 60);
        $Dock = new UserDock($this->getUser());
        foreach ($this->config as $key => $values) {
            $isItem = (is_string($values) || (is_array($values) && array_key_exists(DockItem::LINK, $values)));

            if ($isItem) {
                $this->handleItem($Dock, $values);
            } else {
                $this->handleSection($Dock, $values);
            }
        }
        $this->io()->successln('[SUCCESS]');

        $missing = [];
        $this->writeDock($Dock, $missing);

        if (!empty($missing)) {
            $this->io()->blankln();
            $this->io()->infoln('The following dock items are missing, and were not added:');
            foreach ($missing as $item) {
                $this->io()->writeln('  '.$item);
            }
        }

        return self::EXIT_SUCCESS;
    }

    // region //////////////////////////////////////////////// Config Parsing Methods

    /**
     * @param UserDock $Dock
     * @param string   $item
     */
    protected function handleItem(UserDock $Dock, $item)
    {
        $config = (is_array($item)) ? $item : $this->parseItem($item);

        if (!empty($config)) {
            $Dock->addItem($config);
        }
    }

    protected function handleSection(UserDock $Dock, $section)
    {
        $Dock->addSmallSpacer();

        foreach ($section as $value) {
            $this->handleItem($Dock, $value);
        }

        return $this;
    }

    protected function parseItem($item)
    {
        if (is_string($item)) {
            $uDir = $this->getUser()->getDir();
            $str  = str_replace('~', $uDir, $item);

            if (!$this->isAbsolute($str) && !$this->isApp($str)) {
                foreach ($this->handlers as $handler) {
                    if ($handler::handles($str)) {
                        if ($path = $handler::getLatest($str)) {
                            return ['link' => $path];
                        }
                    }
                }

                $uAppPath = sprintf('%s/Applications/%s.app', $uDir, $str);
                $sAppPath = sprintf('/Applications/%s.app', $str);

                if (is_dir($uAppPath)) {
                    return $uAppPath;
                }

                return $sAppPath;
            }

            return $item;
        }

        return $item;
    }

    /**
     * @return $this
     */
    protected function writeConfig()
    {
        $ext = strtolower(pathinfo($this->configFile, PATHINFO_EXTENSION));

        switch ($ext) {
      case 'yml':
      case 'yaml':
        file_put_contents($this->configFile, Yaml::dump($this->config));
        break;
      case 'json':
        file_put_contents($this->configFile, json_encode($this->config));
        break;
      default:
        break;
    }

        return $this;
    }

    // region //////////////////////////////////////////////// DockUtil Methods

    protected function clearDock(UserDock $Dock, $restart = true)
    {
        $dockutil = $this->getBinaryPath('dockutil');
        $dockFile = $Dock->getFile();

        if ($restart) {
            $cmd = sprintf('%s --remove all --no-restart %s', $dockutil, $dockFile);
        } else {
            $cmd = sprintf('%s --remove all --no-restart %s', $dockutil, $dockFile);
        }

        exec($cmd, $output, $retval);

        if (0 === !$retval) {
            throw new \Exception(sprintf('Unable to clear the dock plist located at: %s', $dockFile));
        }

        sleep(1);

        return $this;
    }

    protected function writeDock(UserDock $Dock, &$missing = '')
    {
        // Clear Dock
        $this->io()->msg('Clearing Dock', 60);
        $this->clearDock($Dock);
        $this->io()->successln('[DONE]');

        // Add Each
        $this->io()->msgln('Adding Items...');
        $dockutil = $this->getBinaryPath('dockutil');
        foreach ($Dock->getItems() as $DockItem) {
            $isExisting = true;
            if ($DockItem instanceof DockSpacer) {
                $cmd = sprintf("%s --add '' --type %s --section apps --no-restart '%s'", $dockutil, $DockItem->getType(), $Dock->getFile());
            } else {
                $link = $DockItem->getLink();
                $cmd  = sprintf('%s --add "%s" --section %s', $dockutil, $link, $DockItem->getSection());

                // Is it a file or folder?
                if ($this->isFolder($link)) {
                    $cmd .= sprintf(' --view %s --display %s --sort %s', $DockItem->getView(), $DockItem->getDisplay(), $DockItem->getSort());
                } elseif ($this->isUrl($link)) {
                    $cmd .= sprintf(' --label %s', $DockItem->getLabel());
                }

                if ($Dock->isLast($DockItem)) {
                    $cmd .= sprintf(' "%s"', $Dock->getFile());
                } else {
                    $cmd .= sprintf(' --no-restart "%s"', $Dock->getFile());
                }

                // Does it exist?
                if (!$this->isUrl($link)) {
                    if (!$this->isExisting($link)) {
                        $isExisting = false;
                        $missing[]  = $link;
                    }
                }
            }

            // Run dockutil command
            if ($isExisting) {
                $this->io()->info('  Adding ');
                $this->io()->write($DockItem->getLink(), null, 51);
                exec($cmd, $output, $retval);
                sleep(2);
                $this->io()->successln('[DONE]');
                if (0 !== $retval) {
                    $this->io()->errorln('    Error Running: '.$cmd);
                }
            }
        }

        return $this;
    }

    // endregion ///////////////////////////////////////////// End DockUtil Methods

    // region //////////////////////////////////////////////// Test Helpers

    protected function isAbsolute($str)
    {
        return '/' === substr($str, 0, 1);
    }

    protected function isApp($str)
    {
        return '.app' === substr($str, -4);
    }

    protected function isUrl($str)
    {
        return preg_match('/([a-z]+):\/\/(.*)/', $str);
    }

    protected function isFolder($str)
    {
        if ($this->isUrl($str) || $this->isApp($str) || (file_exists($str) && !is_dir($str))) {
            // If it has a .app extension, a URL scheme, or it exists and isn't a directory...
            return false;
        } else {
            // If it doesn't exist, isn't a .app or a URL, we can only determine by the existance of an extension
            return empty(pathinfo($str, PATHINFO_EXTENSION));
        }
    }

    protected function isExisting($str)
    {
        return file_exists($str);
    }
}
