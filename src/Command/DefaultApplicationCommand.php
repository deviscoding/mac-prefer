<?php

namespace DevCoding\Mac\Command;

use DevCoding\Mac\Tools\AdobeLocator;
use DevCoding\Mac\Tools\UtilityLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultApplicationCommand extends AbstractPreferConsole
{
  /** @var AdobeLocator[]|UtilityLocator[] */
  protected $handlers = [AdobeLocator::class, UtilityLocator::class];

  // region //////////////////////////////////////////////// Symfony Command Methods

  protected function configure()
  {
    $this->setName('defaults:app');
    $this->addOption(self::INPUT, null, InputOption::VALUE_REQUIRED, 'The file from which to take the default apps config.');
    $this->addOption(self::OUTPUT, null, InputOption::VALUE_REQUIRED, 'The file in which to dump the default apps config.');
    $this->addOption(self::USER, null, InputOption::VALUE_REQUIRED, 'The user for which to to run the command.');
    $this->addOption('extension', 'e', InputOption::VALUE_REQUIRED, 'The file extension for which to set the default.');
    $this->addOption('app', 'a', InputOption::VALUE_REQUIRED, 'The application to use for the extension.');
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    $this->setUserFromInput($input);

    // Get Config File Source
    if (!$in = $input->getOption(self::INPUT))
    {
      $in = $this->getDefaultConfigPath();
      if (!file_exists($in) && OutputInterface::VERBOSITY_QUIET !== $output->getVerbosity())
      {
        $app = $input->getOption('app');
        $ext = $input->getOption('extension');

        if (empty($app) || empty($ext))
        {
          $in = $this->io()->ask('What is the path to the configuration file?');
        }
      }

      if (!empty($in))
      {
        $input->setOption(self::INPUT, $in);
      }
    }

    $this->interactOutput($in);
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->io()->blankln();

    // Read the configuration file in
    if (!$this->executeReadConfig())
    {
      return self::EXIT_ERROR;
    }

    // Append the configuration with any specified extension and application
    if ($ext = $this->io()->getOption('extension'))
    {
      if (!$app = $this->io()->getOption('app'))
      {
        if (!array_key_exists($ext, $this->config))
        {
          $this->io()->errorblk('You specified an extension, but no default app was found in your configuration.  You must also specify the application with --app');

          return self::EXIT_ERROR;
        }

        $app = $this->config[$ext];
      }

      $this->config[$ext] = $app;
    }

    // Write the configuration for the user, including the updated app/extension pairs
    if ($dest = $this->io()->getOption('output'))
    {
      // We already read the config, but we want to change the path to output correctly
      $this->configFile = $dest;

      if ($this->hasConfig())
      {
        $this->io()->msg('Saving Configuration', 60);
        $this->writeConfig();
        $this->io()->successln('[SUCCESS]');
      }
    }

    foreach ($this->config as $ext => $app)
    {
      if ($resolved = $this->resolveApplication($app))
      {
        $this->setDefaultForExtension($ext, $resolved);
      }
    }

    $this->io()->successblk('All application defaults were set successfully for the configured extensions.');

    $this->writeConfig();

    return self::EXIT_SUCCESS;
  }

  // endregion ///////////////////////////////////////////// End Symfony Command Methods

  // region //////////////////////////////////////////////// Prefer Console Methods

  protected function getBundleName($app)
  {
    $cmd = sprintf("%s -n kMDItemCFBundleIdentifier -r '%s'", $this->getBinaryPath('mdls'), $app);

    exec($cmd, $output, $retval);

    if (0 !== $retval || empty($output))
    {
      return null;
    }
    else
    {
      return (is_array($output)) ? $output[0] : $output;
    }
  }

  // endregion ///////////////////////////////////////////// End Prefer Console Methods

  // region //////////////////////////////////////////////// Helper Methods

  protected function getDefaultConfigFileName()
  {
    return 'apps';
  }

  protected function getUtiForExtension($ext)
  {
    $map = [
        'com.adobe.pdf'                     => ['pdf'],
        'com.adobe.postscript'              => ['ps'],
        'com.adobe.encapsulated-postscript' => ['eps'],
        'com.adobe.photoshop-image'         => ['psd'],
        'com.adobe.illustrator.ai-image'    => ['ai'],
        'com.compuserve.gif'                => ['gif'],
        'com.microsoft.bmp'                 => ['bmp'],
        'com.microsoft.ico'                 => ['ico'],
        'com.microsoft.word.doc'            => ['doc', 'docx'],
        'com.microsoft.excel.xls'           => ['xls', 'xlsx'],
        'com.microsoft.powerpoint.ppt'      => ['ppt', 'pptx'],
        'com.microsoft.waveform-audio'      => ['wav', 'wave'],
        'com.microsoft.windows-media-wmv'   => ['wmv'],
        'com.apple.keynote.key'             => ['key'],
        'public.xml'                        => ['xml'],
        'public.txt'                        => ['txt'],
        'public.jpeg'                       => ['jpg', 'jpeg'],
        'public.tiff'                       => ['tiff', 'tif'],
        'public.png'                        => ['png'],
        'com.netscape.javascript.source'    => ['js', 'jscript', 'javascript'],
        'public.shell-script'               => ['sh', 'command'],
        'public.python-script'              => ['py'],
        'public.perl-script'                => ['pl', 'pm'],
        'public.ruby-script'                => ['rb', 'rbw'],
        'public.php-script'                 => ['php', 'php3', 'php4', 'ph3', 'ph4', 'phtml'],
        'public.html'                       => ['htm', 'html'],
        'public.c-source'                   => ['c'],
        'com.apple.applescript.script'      => ['scpt'],
    ];

    return $map[$ext];
  }

  protected function isAbsolute($str)
  {
    return '/' === substr($str, 0, 1);
  }

  protected function isApp($str)
  {
    return '.app' === substr($str, -4);
  }

  protected function resolveApplication($item)
  {
    if (is_string($item))
    {
      $str = str_replace('~', $this->getUserDir(), $item);

      if (!$this->isAbsolute($str) && !$this->isApp($str))
      {
        foreach ($this->handlers as $handler)
        {
          if ($handler::handles($str))
          {
            if ($path = $handler::getLatest($str))
            {
              return $path;
            }
          }
        }

        $uAppPath = sprintf('%s/Applications/%s.app', $this->getUserDir(), $str);
        $sAppPath = sprintf('/Applications/%s.app', $str);

        if (is_dir($uAppPath))
        {
          return $uAppPath;
        }

        return $sAppPath;
      }

      return $item;
    }

    return $item;
  }

  protected function setDefaultForExtension($ext, $resolved)
  {
    if ($bundle = $this->getBundleName($resolved))
    {
      if ($uti = $this->getUtiForExtension($ext))
      {
        $cmd = sprintf('%s -s %s %s all', $this->getBinaryPath('duti'), $bundle, $uti);

        exec($cmd, $output, $retval);

        if (0 !== $retval)
        {
          throw new \Exception('failed to set default');
        }
      }
      else
      {
        throw new \Exception('Could not find proper Uniform Type Identifier for this extension.');
      }
    }
    else
    {
      throw new \Exception('Could not find a bundle name for the given application.');
    }

    return $this;
  }
}
