<?php

namespace DevCoding\Mac\Objects;

class DockSpacer extends DockItem
{
  /**
   * @param string $type
   * @param string $section
   */
  public function __construct($type = 'small-spacer', $section = 'apps')
  {
    parent::__construct($type, $section);
  }

  /**
   * @return string
   */
  public function getType()
  {
    return $this->getLink();
  }
}
