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
 * @version    $Id: Etco.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Timing.php';
/**#@-*/

/**
 * The <i>Event timing codes</i> allows synchronisation with key events in the
 * audio.
 *
 * The events are an array of timestamp and type pairs. The time stamp is set to
 * zero if directly at the beginning of the sound or after the previous event.
 * All events are sorted in chronological order.
 *
 * The events 0xe0-ef are for user events. You might want to synchronise your
 * music to something, like setting off an explosion on-stage, activating a
 * screensaver etc.
 *
 * There may only be one ETCO frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Etco.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Etco extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Timing
{
    /**
     * The list of event types.
     *
     * @var Array
     */
    public static $types = array
        ('Padding', 'End of initial silence', 'Intro start', 'Main part start',
         'Outro start', 'Outro end', 'Verse start','Refrain start',
         'Interlude start', 'Theme start', 'Variation start', 'Key change',
         'Time change', 'Momentary unwanted noise', 'Sustained noise',
         'Sustained noise end', 'Intro end', 'Main part end', 'Verse end',
         'Refrain end', 'Theme end', 'Profanity', 'Profanity end',

         0xe0 => 'User event', 'User event', 'User event', 'User event',
         'User event', 'User event', 'User event', 'User event', 'User event',
         'User event', 'User event', 'User event', 'User event', 'User event',

         0xfd => 'Audio end (start of silence)', 'Audio file ends',
         'One more byte of events follows');

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

        $this->_format = $this->_reader->readUInt8();
        while ($this->_reader->available()) {
            $data = $this->_reader->readUInt8();
            $this->_events[$this->_reader->readUInt32BE()] = $data;
            if ($data == 0xff) {
                break;
            }
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
     * Returns the events as an associated array having the timestamps as keys
     * and the event types as values.
     *
     * @return Array
     */
    public function getEvents() 
    {
         return $this->_events; 
    }

    /**
     * Sets the events using given format. The value must be an associated array
     * having the timestamps as keys and the event types as values.
     *
     * @param Array $events The events array.
     * @param integer $format The timing format.
     */
    public function setEvents($events, $format = null)
    {
        $this->_events = $events;
        if ($format !== null) {
            $this->setFormat($format);
        }
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
        foreach ($this->_events as $timestamp => $type) {
            $writer->writeUInt8($type)
                   ->writeUInt32BE($timestamp);
        }
    }
}
