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
 * @subpackage ASF
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: CodecList.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Codec List Object</i> provides user-friendly information about the
 * codecs and formats used to encode the content found in the ASF file.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: CodecList.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_CodecList extends Zend_Media_Asf_Object
{
    const VIDEO_CODEC = 0x1;
    const AUDIO_CODEC = 0x2;
    const UNKNOWN_CODEC = 0xffff;

    /** @var string */
    private $_reserved;

    /** @var Array */
    private $_entries = array();

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $this->_reserved = $this->_reader->readGuid();
        $codecEntriesCount = $this->_reader->readUInt32LE();
        for ($i = 0; $i < $codecEntriesCount; $i++) {
            $entry = array('type' => $this->_reader->readUInt16LE());
            $codecNameLength = $this->_reader->readUInt16LE() * 2;
            $entry['codecName'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($codecNameLength));
            $codecDescriptionLength = $this->_reader->readUInt16LE() * 2;
            $entry['codecDescription'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($codecDescriptionLength));
            $codecInformationLength = $this->_reader->readUInt16LE();
            $entry['codecInformation'] =
                $this->_reader->read($codecInformationLength);
            $this->_entries[] = $entry;
        }
    }

    /**
     * Returns the array of codec entries. Each record consists of the following
     * keys.
     *
     *   o type -- Specifies the type of the codec used. Use one of the
     *     following values: VIDEO_CODEC, AUDIO_CODEC, or UNKNOWN_CODEC.
     *
     *   o codecName -- Specifies the name of the codec used to create the
     *     content.
     *
     *   o codecDescription -- Specifies the description of the format used to
     *     create the content.
     *
     *   o codecInformation -- Specifies an opaque array of information bytes
     *     about the codec used to create the content. The meaning of these
     *     bytes is determined by the codec.
     *
     * @return Array
     */
    public function getEntries()
    {
        return $this->_entries; 
    }

    /**
     * Sets the array of codec entries. Each record must consist of the
     * following keys.
     *
     *   o codecName -- Specifies the name of the codec used to create the
     *     content.
     *
     *   o codecDescription -- Specifies the description of the format used to
     *     create the content.
     *
     *   o codecInformation -- Specifies an opaque array of information bytes
     *     about the codec used to create the content. The meaning of these
     *     bytes is determined by the codec.
     *
     * @param Array $entries The array of codec entries.
     */
    public function setEntries($entries)
    {
        $this->_entries = $entries; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        require_once 'Zend/Io/StringWriter.php';
        $codecEntriesCount = count($this->_entries);
        $codecEntriesWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $codecEntriesCount; $i++) {
            $codecEntriesWriter
                ->writeUInt16LE($this->_entries[$i]['type'])
                ->writeUInt16LE(strlen($codecName = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_entries[$i]['codecName']) . "\0\0") / 2)
                ->writeString16($codecName)
                ->writeUInt16LE(strlen($codecDescription = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_entries[$i]['codecDescription']) . "\0\0") / 2)
                ->writeString16($codecDescription)
                ->writeUInt16LE(strlen($this->_entries[$i]['codecInformation']))
                ->write($this->_entries[$i]['codecInformation']);
        }

        $this->setSize
            (24 /* for header */ + 20 + $codecEntriesWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_reserved)
               ->writeUInt32LE($codecEntriesCount)
               ->write($codecEntriesWriter->toString());
    }
}
