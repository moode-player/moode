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
 * @version    $Id: Rvad.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Relative volume adjustment</i> frame is a more subjective function
 * than the previous ones. It allows the user to say how much he wants to
 * increase/decrease the volume on each channel while the file is played. The
 * purpose is to be able to align all files to a reference volume, so that you
 * don't have to change the volume constantly. This frame may also be used to
 * balance adjust the audio.
 *
 * There may only be one RVAD frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Rvad.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */
final class Zend_Media_Id3_Frame_Rvad extends Zend_Media_Id3_Frame
{
    /* The required keys. */

    /** @var string */
    const right = 'right';

    /** @var string */
    const left = 'left';

    /** @var string */
    const peakRight = 'peakRight';

    /** @var string */
    const peakLeft = 'peakLeft';

    /* The optional keys. */

    /** @var string */
    const rightBack = 'rightBack';

    /** @var string */
    const leftBack = 'leftBack';

    /** @var string */
    const peakRightBack = 'peakRightBack';

    /** @var string */
    const peakLeftBack = 'peakLeftBack';

    /** @var string */
    const center = 'center';

    /** @var string */
    const peakCenter = 'peakCenter';

    /** @var string */
    const bass = 'bass';

    /** @var string */
    const peakBass = 'peakBass';

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

        $flags = $this->_reader->readInt8();
        $descriptionBits = $this->_reader->readInt8();
        if ($descriptionBits <= 8 || $descriptionBits > 16) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('Unsupported description bit size of: ' . $descriptionBits);
        }

        $this->_adjustments[self::right] =
            ($flags & 0x1) == 0x1 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::left] =
            ($flags & 0x2) == 0x2 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::peakRight] = $this->_reader->readUInt16BE();
        $this->_adjustments[self::peakLeft] = $this->_reader->readUInt16BE();

        if (!$this->_reader->available()) {
            return;
        }

        $this->_adjustments[self::rightBack] =
            ($flags & 0x4) == 0x4 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::leftBack] =
            ($flags & 0x8) == 0x8 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::peakRightBack] =
            $this->_reader->readUInt16BE();
        $this->_adjustments[self::peakLeftBack] =
            $this->_reader->readUInt16BE();

        if (!$this->_reader->available()) {
            return;
        }

        $this->_adjustments[self::center] =
            ($flags & 0x10) == 0x10 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::peakCenter] = $this->_reader->readUInt16BE();

        if (!$this->_reader->available()) {
            return;
        }

        $this->_adjustments[self::bass] =
            ($flags & 0x20) == 0x20 ?
             $this->_reader->readUInt16BE() : -$this->_reader->readUInt16BE();
        $this->_adjustments[self::peakBass] = $this->_reader->readUInt16BE();
    }

    /**
     * Returns the array containing the volume adjustments. The array must
     * contain the following keys: right, left, peakRight, peakLeft. It may
     * optionally contain the following keys: rightBack, leftBack,
     * peakRightBack, peakLeftBack, center, peakCenter, bass, and peakBass.
     *
     * @return Array
     */
    public function getAdjustments() 
    {
        return $this->_adjustments; 
    }

    /**
     * Sets the array of volume adjustments. The array must contain the
     * following keys: right, left, peakRight, peakLeft. It may optionally
     * contain the following keys: rightBack, leftBack, peakRightBack,
     * peakLeftBack, center, peakCenter, bass, and peakBass.
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
        $writer->writeInt8($flags = 0);
        if ($this->_adjustments[self::right] > 0)
            $flags = $flags | 0x1;
        if ($this->_adjustments[self::left] > 0)
            $flags = $flags | 0x2;
        $writer->writeInt8(16)
               ->writeUInt16BE(abs($this->_adjustments[self::right]))
               ->writeUInt16BE(abs($this->_adjustments[self::left]))
               ->writeUInt16BE(abs($this->_adjustments[self::peakRight]))
               ->writeUInt16BE(abs($this->_adjustments[self::peakLeft]));

        if (isset($this->_adjustments[self::rightBack]) &&
            isset($this->_adjustments[self::leftBack]) &&
            isset($this->_adjustments[self::peakRightBack]) &&
            isset($this->_adjustments[self::peakLeftBack])) {
            if ($this->_adjustments[self::rightBack] > 0)
                $flags = $flags | 0x4;
            if ($this->_adjustments[self::leftBack] > 0)
                $flags = $flags | 0x8;
            $writer->writeUInt16BE(abs($this->_adjustments[self::rightBack]))
                   ->writeUInt16BE(abs($this->_adjustments[self::leftBack]))
                   ->writeUInt16BE
                (abs($this->_adjustments[self::peakRightBack]))
                   ->writeUInt16BE
                (abs($this->_adjustments[self::peakLeftBack]));
        }

        if (isset($this->_adjustments[self::center]) &&
            isset($this->_adjustments[self::peakCenter])) {
            if ($this->_adjustments[self::center] > 0)
                $flags = $flags | 0x10;
            $writer->writeUInt16BE(abs($this->_adjustments[self::center]))
                   ->writeUInt16BE(abs($this->_adjustments[self::peakCenter]));
        }

        if (isset($this->_adjustments[self::bass]) &&
                isset($this->_adjustments[self::peakBass])) {
            if ($this->_adjustments[self::bass] > 0)
                $flags = $flags | 0x20;
            $writer->writeUInt16BE(abs($this->_adjustments[self::bass]))
                   ->writeUInt16BE(abs($this->_adjustments[self::peakBass]));
        }
        $writer->setOffset(0);
        $writer->writeInt8($flags);
    }
}
