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
 * @version    $Id: Equa.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Equalisation</i> frame is another subjective, alignment frame. It
 * allows the user to predefine an equalisation curve within the audio file.
 * There may only be one EQUA frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Equa.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */
final class Zend_Media_Id3_Frame_Equa extends Zend_Media_Id3_Frame
{
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

        $adjustmentBits = $this->_reader->readInt8();
        if ($adjustmentBits <= 8 || $adjustmentBits > 16) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('Unsupported adjustment bit size of: ' . $adjustmentBits);
        }

        while ($this->_reader->available()) {
            $frequency = $this->_reader->readUInt16BE();
            $this->_adjustments[($frequency & 0x7fff)] =
                ($frequency & 0x8000) == 0x8000 ?
                $this->_reader->readUInt16BE() :
                -$this->_reader->readUInt16BE();
        }
        ksort($this->_adjustments);
    }

    /**
     * Returns the array containing adjustments having frequencies as keys and
     * their corresponding adjustments as values.
     *
     * @return Array
     */
    public function getAdjustments() 
    {
         return $this->_adjustments; 
    }

    /**
     * Adds a volume adjustment setting for given frequency. The frequency can
     * have a value from 0 to 32767 Hz.
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
     * from 0 to 32767 Hz. One frequency should only be described once in the
     * frame.
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
        $writer->writeInt8(16);
        foreach ($this->_adjustments as $frequency => $adjustment) {
            $writer->writeUInt16BE
                ($adjustment > 0 ? $frequency | 0x8000 : $frequency & ~0x8000)
                   ->writeUInt16BE(abs($adjustment));
        }
    }
}
