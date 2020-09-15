<?php

namespace DevCoding\Mac\Objects;

class UserDock
{
  /** @var string */
  protected $user;
  /** @var DockItem[] */
  protected $items = [];
  /** @var string */
  protected $tmpl = '/Users/%s/Library/Preferences/com.apple.dock.plist';

  /**
   * @param $user
   */
  public function __construct($user)
  {
    $this->user = $user;
  }

  public function getFile()
  {
    return sprintf($this->tmpl, $this->user);
  }

  /**
   * @return DockItem[]
   */
  public function getItems()
  {
    return $this->items;
  }

  public function isEmpty()
  {
    return empty($this->items);
  }

  public function addSmallSpacer()
  {
    $this->items[] = new DockSpacer();

    return $this;
  }

  public function addItem($item)
  {
    if ($item instanceof DockItem)
    {
      $this->items[] = $item;
    }
    elseif (is_array($item))
    {
      $this->items[] = DockItem::fromArray($item);
    }
    elseif (is_string($item))
    {
      $this->items[] = new DockItem($item);
    }

    return $this;
  }

  public function isLast(DockItem $dockItem)
  {
    $index = count($this->items) - 1;

    return $dockItem->getLink() === $this->items[$index]->getLink();
  }
}
