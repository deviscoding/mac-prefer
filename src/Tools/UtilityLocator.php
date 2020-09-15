<?php

namespace DevCoding\Mac\Tools;

class UtilityLocator
{
  const HANDLES = [
      'screen_sharing'       => 'Screen Sharing',
      'dvd_player'           => 'DVD Player',
      'directory_utility'    => 'Directory Utility',
      'archive_utility'      => 'Archive Utility',
      'folder_actions'       => 'Folder Actions Setup',
      'network_utility'      => 'Network Utility',
      'raid'                 => 'RAID Utility',
      'storage'              => 'Storage Management',
      'wireless_diagnostics' => 'Wireless Diagnostics',
      'image_utility'        => 'System Image Utility'
  ];

  public static function handles($str)
  {
    return array_key_exists($str, self::HANDLES);
  }

  public static function getLatest($str)
  {
    if (self::handles($str))
    {
      return sprintf('/System/Library/CoreServices/Applications/%s.app', self::HANDLES[$str]);
    }

    if ('screen_sharing' === $str)
    {
      return '/System/Library/CoreServices/Applications/Screen Sharing.app';
    }

    return null;
  }

  public static function getReverse($str)
  {
    if (preg_match('#/System/Library/CoreServices/Applications/([A-Za-z\s]+)#', $str, $matches))
    {
      foreach(self::HANDLES as $key => $app)
      {
        if ($matches[1] == $app)
        {
          return $key;
        }
      }
    }

    return null;
  }
}
