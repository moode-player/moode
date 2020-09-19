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
 * @version    $Id: Sylt.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
require_once 'Zend/Media/Id3/Language.php';
require_once 'Zend/Media/Id3/Timing.php';
/**#@-*/

/**
 * The <i>Synchronised lyrics/text</i> frame is another way of incorporating the
 * words, said or sung lyrics, in the audio file as text, this time, however,
 * in sync with the audio. It might also be used to describing events e.g.
 * occurring on a stage or on the screen in sync with the audio.
 *
 * There may be more than one SYLT frame in each tag, but only one with the
 * same language and content descriptor.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sylt.php 273 2012-08-21 17:22:52Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Sylt extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding, Zend_Media_Id3_Language,
        Zend_Media_Id3_Timing
{
    /**
     * The list of content types.
     *
     * @var Array
     */
    public static $types = array
        ('Other', 'Lyrics', 'Text transcription', 'Movement/Part name',
         'Events', 'Chord', 'Trivia', 'URLs to webpages', 'URLs to images');

    /** @var integer */
    private $_encoding;

    /** @var string */
    private $_language = 'und';

    /** @var integer */
    private $_format = Zend_Media_Id3_Timing::MPEG_FRAMES;

    /** @var integer */
    private $_type = 0;

    /** @var string */
    private $_description;

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

        $this->setEncoding
            ($this->getOption('encoding', Zend_Media_Id3_Encoding::UTF8));

        if ($this->_reader === null) {
            return;
        }

        $encoding = $this->_reader->readUInt8();
        $this->_language = strtolower($this->_reader->read(3));
        if ($this->_language == 'xxx' || trim($this->_language, "\0") == '') {
            $this->_language = 'und';
        }
        $this->_format = $this->_reader->readUInt8();
        $this->_type = $this->_reader->readUInt8();

        $offset = $this->_reader->getOffset();
        switch ($encoding) {
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                list($this->_description) =
                    $this->_explodeString16
                        ($this->_reader->read($this->_reader->getSize()), 2);
                if ($this->_reader->getSize() >= $offset + strlen($this->_description) + 2) {
                    $this->_reader->setOffset
                        ($offset + strlen($this->_description) + 2);
                }
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                list($this->_description) =
                    $this->_explodeString8
                        ($this->_reader->read($this->_reader->getSize()), 2);
                if ($this->_reader->getSize() >= $offset + strlen($this->_description) + 1) {
                    $this->_reader->setOffset
                        ($offset + strlen($this->_description) + 1);
                }
                break;
        }
        $this->_description = $this->_convertString($this->_description, $encoding);

        while ($this->_reader->available()) {
            $offset = $this->_reader->getOffset();
            switch ($encoding) {
                case self::UTF16:
                    // break intentionally omitted
                case self::UTF16BE:
                    list($syllable) =
                        $this->_explodeString16
                            ($this->_reader->read
                             ($this->_reader->getSize()), 2);
                    $this->_reader->setOffset
                        ($offset + strlen($syllable) + 2);
                    break;
                case self::UTF8:
                    // break intentionally omitted
                default:
                    list($syllable) =
                        $this->_explodeString8
                            ($this->_reader->read
                             ($this->_reader->getSize()), 2);
                    $this->_reader->setOffset
                        ($offset + strlen($syllable) + 1);
                    break;
            }
            $this->_events
                [$this->_reader->readUInt32BE()] =
                    $this->_convertString($syllable, $encoding);
        }
        ksort($this->_events);
    }

    /**
     * Returns the text encoding.
     *
     * All the strings read from a file are automatically converted to the
     * character encoding specified with the <var>encoding</var> option. See
     * {@link Zend_Media_Id3v2} for details. This method returns that character
     * encoding, or any value set after read, translated into a string form
     * regarless if it was set using a {@link Zend_Media_Id3_Encoding} constant
     * or a string.
     *
     * @return integer
     */
    public function getEncoding()
    {
        return $this->_translateIntToEncoding($this->_encoding);
    }

    /**
     * Sets the text encoding.
     *
     * All the string written to the frame are done so using given character
     * encoding. No conversions of existing data take place upon the call to
     * this method thus all texts must be given in given character encoding.
     *
     * The character encoding parameter takes either a
     * {@link Zend_Media_Id3_Encoding} constant or a character set name string
     * in the form accepted by iconv. The default character encoding used to
     * write the frame is 'utf-8'.
     *
     * @see Zend_Media_Id3_Encoding
     * @param integer $encoding The text encoding.
     */
    public function setEncoding($encoding)
    {
        $this->_encoding = $this->_translateEncodingToInt($encoding);
    }

    /**
     * Returns the language code as specified in the
     * {@link http://www.loc.gov/standards/iso639-2/ ISO-639-2} standard.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * Sets the text language code as specified in the
     * {@link http://www.loc.gov/standards/iso639-2/ ISO-639-2} standard.
     *
     * @see Zend_Media_Id3_Language
     * @param string $language The language code.
     */
    public function setLanguage($language)
    {
        $language = strtolower($language);
        if ($language == 'xxx') {
            $language = 'und';
        }
        $this->_language = substr($language, 0, 3);
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
     * Returns the content type code.
     *
     * @return integer
     */
    public function getType() 
    {
        return $this->_type; 
    }

    /**
     * Sets the content type code.
     *
     * @param integer $type The content type code.
     */
    public function setType($type) 
    {
        $this->_type = $type; 
    }

    /**
     * Returns the content description.
     *
     * @return string
     */
    public function getDescription() 
    {
         return $this->_description; 
    }

    /**
     * Sets the content description text using given encoding. The description
     * language and encoding must be that of the actual text.
     *
     * @param string $description The content description text.
     * @param string $language The language code.
     * @param integer $encoding The text encoding.
     */
    public function setDescription
        ($description, $language = null, $encoding = null)
    {
        $this->_description = $description;
        if ($language !== null) {
            $this->setLanguage($language);
        }
        if ($encoding !== null) {
            $this->setEncoding($encoding);
        }
    }

    /**
     * Returns the syllable events with their timestamps.
     *
     * @return Array
     */
    public function getEvents() 
    {
        return $this->_events; 
    }

    /**
     * Sets the syllable events with their timestamps using given encoding.
     *
     * The text language and encoding must be that of the description text.
     *
     * @param Array $text The test string.
     * @param string $language The language code.
     * @param integer $encoding The text encoding.
     */
    public function setEvents($events, $language = null, $encoding = null)
    {
        $this->_events = $events;
        if ($language !== null) {
            $this->setLanguage($language);
        }
        if ($encoding !== null) {
            $this->setEncoding($encoding);
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
        $writer->writeUInt8($this->_encoding)
               ->write($this->_language)
               ->writeUInt8($this->_format)
               ->writeUInt8($this->_type);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $writer->writeString16
                    ($this->_description,
                     Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_description, null, 1);
                break;
            default:
                $writer->writeString8($this->_description, 1);
                break;
        }
        foreach ($this->_events as $timestamp => $syllable) {
            switch ($this->_encoding) {
                case self::UTF16LE:
                    $writer->writeString16
                        ($syllable, Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1);
                    break;
                case self::UTF16:
                    // break intentionally omitted
                case self::UTF16BE:
                    $writer->writeString16($syllable, null, 1);
                    break;
                default:
                    $writer->writeString8($syllable, 1);
                    break;
            }
            $writer->writeUInt32BE($timestamp);
        }
    }
}
