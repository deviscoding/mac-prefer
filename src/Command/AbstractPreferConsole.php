<?php
/**
 * AbstractPreferConsole.php.
 */

namespace DevCoding\Mac\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AbstractPreferConsole.
 *
 * @author  Aaron J <aaron@jonesiscoding.com>
 *
 * @package DevCoding\Mac\Command
 */
abstract class AbstractPreferConsole extends AbstractConfigConsole
{
  const INPUT  = 'in';
  const OUTPUT = 'out';
  const USER   = 'user';
  const EXT    = 'yml';

  /**
   * Must provide a string indicating the filename of the config for this class, without the extension or path.
   *
   * @return string
   */
  abstract protected function getDefaultConfigFileName();

  // region //////////////////////////////////////////////// Execute Methods

  /**
   * Convenience method to avoid repeating ourselves.  Reads the configuration from the input option.
   *
   * @return bool
   */
  protected function executeReadConfig()
  {
    $this->io()->msg('Reading Configuration', 60);
    if ($source = $this->io()->getOption(self::INPUT))
    {
      $this->setConfigFromFile($source);

      if (!$this->hasConfig())
      {
        $this->io()->errorln('[ERROR]');
        $this->io()->errorblk('The specified configuration file could not be parsed.');

        return false;
      }
    }

    $this->io()->successln('[SUCCESS]');

    return true;
  }

  // endregion ///////////////////////////////////////////// End Execute Methods

  // region //////////////////////////////////////////////// Interact Methods

  /**
   * Convenience method to avoid repeating ourselves.  Sets the 'out' option based on the default source extension
   * and the current user.
   *
   * @param string $source
   *
   * @return string
   */
  protected function interactOutput($source)
  {
    if (!$dest = $this->io()->getOption(self::OUTPUT))
    {
      $ext  = !empty($source) ? pathinfo($source, PATHINFO_EXTENSION) : self::EXT;
      $dest = $this->getDefaultConfigPath($ext);

      // Match the extension of the input file in the output file
      $this->io()->getInput()->setOption(self::OUTPUT, $dest);
    }

    return $dest;
  }

  /**
   * Sets the user based on the command line option.
   *
   * @param InputInterface $Input
   *
   * @return $this
   *
   * @throws \Exception
   */
  protected function setUserFromInput(InputInterface $Input)
  {
    // Default the User
    if ($user = $Input->getOption('user'))
    {
      $this->setUser($user);
    }

    return $this;
  }

  // endregion ///////////////////////////////////////////// End Interact Methods

  // region //////////////////////////////////////////////// Config Helper Methods

  /**
   * Returns the path to this command's default configuration file.
   *
   * @param string $ext
   *
   * @return string
   */
  protected function getDefaultConfigPath($ext = self::EXT)
  {
    return sprintf('%s/Preferences/Prefer/%s.%s', $this->getUserLibraryDir(), $this->getDefaultConfigFileName(), $ext);
  }

  protected function setConfigFromUser($name)
  {
    $try[] = $this->getDefaultConfigPath('yml');
    $try[] = $this->getDefaultConfigPath('json');

    foreach ($try as $file)
    {
      if ($config = $this->getConfigFromFile($file))
      {
        $this->config     = $config;
        $this->configFile = $file;
      }
    }

    return $this;
  }

  /**
   * @return $this
   */
  protected function writeConfig()
  {
    $ext = strtolower(pathinfo($this->configFile, PATHINFO_EXTENSION));

    $this->mkdir(dirname($this->configFile));

    switch ($ext)
    {
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

    // Set Proper Ownership
    $owner = posix_getpwuid(fileowner($this->getUserLibraryDir()));
    $group = posix_getgrgid(filegroup($this->getUserLibraryDir()));
    chown($this->configFile, $owner['name']);
    chown(dirname($this->configFile), $owner['name']);
    chgrp($this->configFile, $group['name']);
    chgrp(dirname($this->configFile), $group['name']);

    return $this;
  }

  // endregion ///////////////////////////////////////////// End Config Methods
}
