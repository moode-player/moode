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
 * @version    $Id: Marker.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Marker Object</i> class.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Marker.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Marker extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_reserved1;

    /** @var integer */
    private $_reserved2;

    /** @var string */
    private $_name;

    /** @var Array */
    private $_markers = array();

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_reserved1 = $this->_reader->readGuid();
        $markersCount = $this->_reader->readUInt32LE();
        $this->_reserved2 = $this->_reader->readUInt16LE();
        $nameLength = $this->_reader->readUInt16LE();
        $this->_name = iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($nameLength));
        for ($i = 0; $i < $markersCount; $i++) {
            $marker = array
                ('offset' => $this->_reader->readInt64LE(),
                 'presentationTime' => $this->_reader->readInt64LE());
            $this->_reader->skip(2);
            $marker['sendTime'] = $this->_reader->readUInt32LE();
            $marker['flags'] = $this->_reader->readUInt32LE();
            $descriptionLength = $this->_reader->readUInt32LE();
            $marker['description'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($descriptionLength));
            $this->_markers[] = $marker;
        }
    }

    /**
     * Returns the name of the Marker Object.
     *
     * @return Array
     */
    public function getName() 
    {
        return $this->_name; 
    }

    /**
     * Returns the name of the Marker Object.
     *
     * @param string $name The name.
     */
    public function setName($name) 
    {
        $this->_name = $name; 
    }

    /**
     * Returns an array of markers. Each entry consists of the following keys.
     *
     *   o offset -- Specifies a byte offset into the <i>Data Object</i> to the
     *     actual position of the marker in the <i>Data Object</i>. ASF parsers
     *     must seek to this position to properly display data at the specified
     *     marker <i>Presentation Time</i>.
     *
     *   o presentationTime -- Specifies the presentation time of the marker, in
     *     100-nanosecond units.
     *
     *   o sendTime -- Specifies the send time of the marker entry, in
     *     milliseconds.
     *
     *   o flags -- Flags are reserved and should be set to 0.
     *
     *   o description -- Specifies a description of the marker entry.
     *
     * @return Array
     */
    public function getMarkers() 
    {
        return $this->_markers; 
    }

    /**
     * Sets the array of markers. Each entry is to consist of the following
     * keys.
     *
     *   o offset -- Specifies a byte offset into the <i>Data Object</i> to the
     *     actual position of the marker in the <i>Data Object</i>. ASF parsers
     *     must seek to this position to properly display data at the specified
     *     marker <i>Presentation Time</i>.
     *
     *   o presentationTime -- Specifies the presentation time of the marker, in
     *     100-nanosecond units.
     *
     *   o sendTime -- Specifies the send time of the marker entry, in
     *     milliseconds.
     *
     *   o flags -- Flags are reserved and should be set to 0.
     *
     *   o description -- Specifies a description of the marker entry.
     *
     * @param Array $markers The array of markers.
     */
    public function setMarkers($markers) 
    {
        $this->_markers = $markers; 
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
        
        $name = iconv
            ($this->getOption('encoding'), 'utf-16le', $this->_name) . "\0\0";
        $markersCount = count($this->_markers);
        $markersWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $markersCount; $i++) {
            $markersWriter
                ->writeInt64LE($this->_markers[$i]['offset'])
                ->writeInt64LE($this->_markers[$i]['presentationTime'])
                ->writeUInt16LE
                    (12 + ($descriptionLength = strlen($description = iconv
                     ('utf-16le', $this->getOption('encoding'),
                      $this->_markers[$i]['description']) . "\0\0")))
                ->writeUInt32LE($this->_markers[$i]['sendTime'])
                ->writeUInt32LE($this->_markers[$i]['flags'])
                ->writeUInt32LE($descriptionLength)
                ->writeString16($description);
        }

        $this->setSize
            (24 /* for header */ + 24 + strlen($name) +
             $markersWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_reserved1)
               ->writeUInt32LE($markersCount)
               ->writeUInt16LE($this->_reserved2)
               ->writeUInt16LE(strlen($name))
               ->writeString16($name)
               ->write($markersWriter->toString());
    }
}
