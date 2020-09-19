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
 * @version    $Id: Rva2.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Relative volume adjustment (2)</i> frame is a more subjective frame
 * than the previous ones. It allows the user to say how much he wants to
 * increase/decrease the volume on each channel when the file is played. The
 * purpose is to be able to align all files to a reference volume, so that you
 * don't have to change the volume constantly. This frame may also be used to
 * balance adjust the audio.
 * 
 * The volume adjustment is encoded in a way giving the scale of +/- 64 dB with
 * a precision of 0.001953125 dB.
 *
 * There may be more than one RVA2 frame in each tag, but only one with the same
 * identification string.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Rva2.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */
final class Zend_Media_Id3_Frame_Rva2 extends Zend_Media_Id3_Frame
{
    /**
     * The channel type key.
     *
     * @see $types
     * @var string
     */
    const channelType = 'channelType';

    /**
     * The volume adjustment key. Adjustments are +/- 64 dB with a precision of
     * 0.001953125 dB.
     *
     * @var string
     */
    const volumeAdjustment = 'volumeAdjustment';

    /**
     * The peak volume key.
     *
     * @var string
     */
    const peakVolume = 'peakVolume';

    /**
     * The list of channel types.
     *
     * @var Array
     */
    public static $types = array
        ('Other', 'Master volume', 'Front right', 'Front left', 'Back right',
         'Back left', 'Front centre', 'Back centre', 'Subwoofer');

    /** @var string */
    private $_device;

    /** @var Array */
    private $_adjustments;

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

        list ($this->_device) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(strlen($this->_device) + 1);

        for ($i = $j = 0; $i < 9; $i++) {
            $this->_adjustments[$i] = array
                (self::channelType => $this->_reader->readInt8(),
                 self::volumeAdjustment =>
                     $this->_reader->readInt16BE() / 512.0);
            $bitsInPeak = $this->_reader->readInt8();
            $bytesInPeak = $bitsInPeak > 0 ? ceil($bitsInPeak / 8) : 0;
            switch ($bytesInPeak) {
                case 8:
                    $this->_adjustments[$i][self::peakVolume] =
                        $this->_reader->readInt64BE();
                    break;
                case 4:
                    $this->_adjustments[$i][self::peakVolume] =
                        $this->_reader->readUInt32BE();
                    break;
                case 2:
                    $this->_adjustments[$i][self::peakVolume] =
                        $this->_reader->readUInt16BE();
                    break;
                case 1:
                    $this->_adjustments[$i][self::peakVolume] =
                        $this->_reader->readUInt8();
                    break;
                default:
                    break;
            }
        }
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
     * Returns the array containing volume adjustments for each channel. Volume
     * adjustments are arrays themselves containing the following keys:
     * channelType, volumeAdjustment, peakVolume.
     *
     * @return Array
     */
    public function getAdjustments() 
    {
         return $this->_adjustments; 
    }

    /**
     * Sets the array of volume adjustments for each channel. Each volume
     * adjustment is an array too containing the following keys: channelType,
     * volumeAdjustment, peakVolume.
     *
     * @param Array $adjustments The volume adjustments array.
     */
    public function setAdjustments($adjustments)
    {
        $this->_adjustments = $adjustments;
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeString8($this->_device, 1);
        foreach ($this->_adjustments as $channel) {
            $writer->writeInt8($channel[self::channelType])
                   ->writeInt16BE($channel[self::volumeAdjustment] * 512);
            if (abs($channel[self::peakVolume]) <= 0xff) {
                $writer->writeInt8(8)
                       ->writeUInt8($channel[self::peakVolume]);
            } else if (abs($channel[self::peakVolume]) <= 0xffff) {
                $writer->writeInt8(16)
                       ->writeUInt16BE($channel[self::peakVolume]);
            } else if (abs($channel[self::peakVolume]) <= 0xffffffff) {
                $writer->writeInt8(32)
                       ->writeUInt32BE($channel[self::peakVolume]);
            } else {
                $writer->writeInt8(64)
                       ->writeInt64BE($channel[self::peakVolume]); // UInt64
            }
        }
    }
}
