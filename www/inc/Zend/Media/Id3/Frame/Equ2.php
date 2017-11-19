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
 * @version    $Id: Equ2.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Equalisation (2)</i> is another subjective, alignment frame. It allows
 * the user to predefine an equalisation curve within the audio file. There may
 * be more than one EQU2 frame in each tag, but only one with the same
 * identification string.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Equ2.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */
final class Zend_Media_Id3_Frame_Equ2 extends Zend_Media_Id3_Frame
{
    /**
     * Interpolation type that defines that no interpolation is made. A jump
     * from one adjustment level to another occurs in the middle between two
     * adjustment points.
     */
    const BAND = 0;

    /**
     * Interpolation type that defines that interpolation between adjustment
     * points is linear.
     */
    const LINEAR = 1;

    /** @var integer */
    private $_interpolation;

    /** @var string */
    private $_device;

    /** @var Array */
    private $_adjustments = array();

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($this->_reader === null) {
            return;
        }

        $this->_interpolation = $this->_reader->readInt8();
        list ($this->_device) = $this->_explodeString8
            ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(1 + strlen($this->_device) + 1);
        while ($this->_reader->available()) {
            $this->_adjustments
                [(int)($this->_reader->readUInt16BE() / 2)] =
                    $this->_reader->readInt16BE() / 512.0;
        }
        ksort($this->_adjustments);
    }

    /**
     * Returns the interpolation method. The interpolation method describes
     * which method is preferred when an interpolation between the adjustment
     * point that follows.
     *
     * @return integer
     */
    public function getInterpolation() 
    {
         return $this->_interpolation; 
    }

    /**
     * Sets the interpolation method. The interpolation method describes which
     * method is preferred when an interpolation between the adjustment point
     * that follows.
     *
     * @param integer $interpolation The interpolation method code.
     */
    public function setInterpolation($interpolation)
    {
        $this->_interpolation = $interpolation;
    }

    /**
     * Returns the device where the adjustments should apply.
     *
     * @return string
     */
    public function getDevice() 
    {
         return $this->_device; 
    }

    /**
     * Sets the device where the adjustments should apply.
     *
     * @param string $device The device.
     */
    public function setDevice($device) 
    {
         $this->_device = $device; 
    }

    /**
     * Returns the array containing adjustments having frequencies as keys and
     * their corresponding adjustments as values.
     *
     * Adjustment points are ordered by frequency.
     *
     * @return Array
     */
    public function getAdjustments() 
    {
         return $this->_adjustments; 
    }

    /**
     * Adds a volume adjustment setting for given frequency. The frequency can
     * have a value from 0 to 32767 Hz, and the adjustment </> +/- 64 dB with a
     * precision of 0.001953125 dB.
     *
     * @param integer $frequency The frequency, in hertz.
     * @param integer $adjustment The adjustment, in dB.
     */
    public function addAdjustment($frequency, $adjustment)
    {
        $this->_adjustments[$frequency] = $adjustment;
        ksort($this->_adjustments);
    }

    /**
     * Sets the adjustments array. The array must have frequencies as keys and
     * their corresponding adjustments as values. The frequency can have a value
     * from 0 to 32767 Hz, and the adjustment </> +/- 64 dB with a precision of
     * 0.001953125 dB. One frequency should only be described once in the frame.
     *
     * @param Array $adjustments The adjustments array.
     */
    public function setAdjustments($adjustments)
    {
        $this->_adjustments = $adjustments;
        ksort($this->_adjustments);
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeInt8($this->_interpolation)
               ->writeString8($this->_device, 1);
        foreach ($this->_adjustments as $frequency => $adjustment) {
            $writer->writeUInt16BE($frequency * 2)
                   ->writeInt16BE($adjustment * 512);
        }
    }
}
