<?php

namespace DevCoding\Mac\Objects;

class DockItem
{
  const SECTION = 'section';
  const VIEW    = 'view';
  const DISPLAY = 'display';
  const SORT    = 'sort';
  const LINK    = 'link';
  const LABEL   = 'label';

  protected $section = 'apps';
  protected $view    = 'auto';
  protected $display = 'folder';
  protected $sort    = 'name';
  protected $label;
  protected $link;

  /**
   * @param string $section
   * @param string $view
   * @param string $display
   * @param string $sort
   * @param        $link
   */
  public function __construct($link, $section = 'apps', $display = 'folder', $view = 'auto', $sort = 'name')
  {
    $this->link = $link;
    $this->setSection($section);
    $this->setDisplay($display);
    $this->setView($view);
    $this->setSort($sort);
  }

  public static function fromArray($arr)
  {
    if (array_key_exists(self::LINK, $arr))
    {
      $DockItem = new DockItem($arr[self::LINK]);

      if (array_key_exists(self::SECTION, $arr))
      {
        $DockItem->setSection($arr[self::SECTION]);
      }

      if (array_key_exists(self::DISPLAY, $arr))
      {
        $DockItem->setDisplay($arr[self::DISPLAY]);
      }

      if (array_key_exists(self::VIEW, $arr))
      {
        $DockItem->setView($arr[self::VIEW]);
      }

      if (array_key_exists(self::SORT, $arr))
      {
        $DockItem->setSort($arr[self::SORT]);
      }

      if (array_key_exists(self::LABEL, $arr))
      {
        $DockItem->setLabel($arr[self::LABEL]);
      }

      return $DockItem;
    }

    print_r($arr);

    throw new \Exception(sprintf('The key %s is required.', self::LINK));
  }

  /**
   * @return string
   */
  public function getSection()
  {
    return $this->section;
  }

  /**
   * @return string
   */
  public function getView()
  {
    return $this->view;
  }

  /**
   * @return string
   */
  public function getDisplay()
  {
    return $this->display;
  }

  /**
   * @return string
   */
  public function getSort()
  {
    return $this->sort;
  }

  /**
   * @return string
   */
  public function getLink()
  {
    return $this->link;
  }

  /**
   * @return mixed
   */
  public function getLabel()
  {
    return $this->label;
  }

  /**
   * @param mixed $label
   *
   * @return DockItem
   */
  public function setLabel($label)
  {
    $this->label = $label;

    return $this;
  }

  /**
   * @param string $section
   *
   * @return DockItem
   */
  public function setSection($section)
  {
    $this->section = $section;

    return $this;
  }

  /**
   * @param string $view
   *
   * @return DockItem
   */
  public function setView($view)
  {
    $this->view = $view;

    return $this;
  }

  /**
   * @param string $display
   *
   * @return DockItem
   */
  public function setDisplay($display)
  {
    $this->display = $display;

    return $this;
  }

  /**
   * @param string $sort
   *
   * @return DockItem
   */
  public function setSort($sort)
  {
    $this->sort = $sort;

    return $this;
  }
}
