<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Time.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/DateFrame.php';
/**#@-*/

/**
 * The <i>Time</i> frame contains the time for the recording in the HHMM format.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Time.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */
final class Zend_Media_Id3_Frame_Time extends Zend_Media_Id3_DateFrame
{
    private $_hours;
    private $_minutes;

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options, 'HHmm');
        $this->_hours = substr($this->getText(), 0, 2);
        $this->_minutes = substr($this->getText(), 2, 2);
    }

    /**
     * Returns the hour.
     *
     * @return integer
     */
    public function getHour()
    {
        return intval($this->_hours);
    }

    /**
     * Sets the hour.
     *
     * @param integer $hours The hours.
     */
    public function setHour($hours)
    {
        $this->setText
            (($this->_hours = str_pad(strval($hours), 2, "0", STR_PAD_LEFT)) .
             ($this->_minutes ? $this->_minutes : '00'),
             Zend_Media_Id3_Encoding::ISO88591);
    }

    /**
     * Returns the minutes.
     *
     * @return integer
     */
    public function getMinute()
    {
        return intval($this->_minutes);
    }

    /**
     * Sets the minutes.
     *
     * @param integer $minutes The minutes.
     */
    public function setMinute($minutes)
    {
        $this->setText
            (($this->_hours ? $this->_hours : '00') .
             ($this->_minutes =
                  str_pad(strval($minutes), 2, "0", STR_PAD_LEFT)),
             Zend_Media_Id3_Encoding::ISO88591);
    }
}
