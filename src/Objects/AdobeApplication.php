<?php

namespace DevCoding\Mac\Objects;

class AdobeApplication
{
  protected $year;
  protected $long;
  protected $application;

  /**
   * @param $year
   * @param $long
   */
  public function __construct($long, $year = null)
  {
    $this->long = $long;
    $this->year = $year;
  }

  /**
   * @return int
   */
  public function getYear()
  {
    return $this->year;
  }

  /**
   * @param int $year
   *
   * @return AdobeApplication
   */
  public function setYear($year)
  {
    $this->year = $year;

    return $this;
  }

  /**
   * @return string
   */
  public function getLong()
  {
    return $this->long;
  }

  /**
   * @param string $long
   *
   * @return AdobeApplication
   */
  public function setLong($long)
  {
    $this->long = $long;

    return $this;
  }

  /**
   * @return string
   *
   * @throws Exception
   */
  public function getApplication()
  {
    if (empty($this->application))
    {
      $long = $this->getLong();
      $year = $this->getYear();
      $try  = [
          sprintf('/Applications/Adobe %s %s/Adobe %s %s.app', $long, $year, $long, $year),
          sprintf('/Applications/Adobe %s CC %s/Adobe %s CC %s.app', $long, $year, $long, $year),
          sprintf('/Applications/Adobe %s/Adobe %s.app', $long, $long),
      ];

      foreach ($try as $path)
      {
        if (empty($this->application))
        {
          if (is_dir($path))
          {
            $this->application = $path;
          }
        }
      }

      if (empty($this->application))
      {
        throw new \Exception('Could not detect the application path format!');
      }
    }

    return $this->application;
  }
}
