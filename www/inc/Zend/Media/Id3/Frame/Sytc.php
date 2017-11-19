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
 * @version    $Id: Sytc.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Timing.php';
/**#@-*/

/**
 * For a more accurate description of the tempo of a musical piece, the
 * <i>Synchronised tempo codes</i> frame might be used.
 *
 * The tempo data consists of one or more tempo codes. Each tempo code consists
 * of one tempo part and one time part. The tempo is in BPM described with one
 * or two bytes. If the first byte has the value $FF, one more byte follows,
 * which is added to the first giving a range from 2 - 510 BPM, since $00 and
 * $01 is reserved. $00 is used to describe a beat-free time period, which is
 * not the same as a music-free time period. $01 is used to indicate one single
 * beat-stroke followed by a beat-free period.
 *
 * The tempo descriptor is followed by a time stamp. Every time the tempo in the
 * music changes, a tempo descriptor may indicate this for the player. All tempo
 * descriptors must be sorted in chronological order. The first beat-stroke in
 * a time-period is at the same time as the beat description occurs. There may
 * only be one SYTC frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sytc.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Sytc extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Timing
{
    /** Describes a beat-free time period. */
    const BEAT_FREE = 0x00;

    /** Indicate one single beat-stroke followed by a beat-free period. */
    const SINGLE_BEAT = 0x01;

    /** @var integer */
    private $_format = Zend_Media_Id3_Timing::MPEG_FRAMES;

    /** @var Array */
    private $_events = array();

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

        $offset = 0;
        $this->_format = $this->_reader->readUInt8();
        while ($this->_reader->available()) {
            $tempo = $this->_reader->readUInt8();
            if ($tempo == 0xff)
                $tempo += $this->_reader->readUInt8();
            $this->_events[$this->_reader->readUInt32BE()] = $tempo;
        }
        ksort($this->_events);
    }

    /**
     * Returns the timing format.
     *
     * @return integer
     */
    public function getFormat() 
    {
        return $this->_format; 
    }

    /**
     * Sets the timing format.
     *
     * @see Zend_Media_Id3_Timing
     * @param integer $format The timing format.
     */
    public function setFormat($format) 
    {
        $this->_format = $format; 
    }

    /**
     * Returns the time-bpm tempo events.
     *
     * @return Array
     */
    public function getEvents() 
    {
        return $this->_events; 
    }

    /**
     * Sets the time-bpm tempo events.
     *
     * @param Array $events The time-bpm tempo events.
     */
    public function setEvents($events)
    {
        $this->_events = $events;
        ksort($this->_events);
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_format);
        foreach ($this->_events as $timestamp => $tempo) {
            if ($tempo >= 0xff) {
                $writer->writeUInt8(0xff)
                       ->writeUInt8($tempo - 0xff);
            } else {
                $writer->writeUInt8($tempo);
            }
            $writer->writeUInt32BE($timestamp);
        }
    }
}
