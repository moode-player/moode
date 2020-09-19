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
 * @version    $Id: MetadataLibrary.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Metadata Library Object</i> lets authors store stream-based,
 * language-attributed, multiply defined, and large metadata attributes in a
 * file.
 *
 * This object supports the same types of metadata as the
 * <i>{@link Zend_Media_Asf_Object_Metadata Metadata Object}</i>, as well as
 * attributes with language IDs, attributes that are defined more than once,
 * large attributes, and attributes with the GUID data type.
 *
 * @todo       Implement better handling of various types of attributes
 *     according to http://msdn.microsoft.com/en-us/library/aa384495(VS.85).aspx
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: MetadataLibrary.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_MetadataLibrary extends Zend_Media_Asf_Object
{
    /** @var Array */
    private $_descriptionRecords = array();

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

        $descriptionRecordsCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $descriptionRecordsCount; $i++) {
            $descriptionRecord = array
                ('languageIndex' => $this->_reader->readUInt16LE(),
                 'streamNumber' => $this->_reader->readUInt16LE());
            $nameLength = $this->_reader->readUInt16LE();
            $dataType = $this->_reader->readUInt16LE();
            $dataLength = $this->_reader->readUInt32LE();
            $descriptionRecord['name'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($nameLength));
            switch ($dataType) {
                case 0: // Unicode string
                    $descriptionRecord['data'] = iconv
                        ('utf-16le', $this->getOption('encoding'),
                         $this->_reader->readString16($dataLength));
                    break;
                case 1: // BYTE array
                    $descriptionRecord['data'] =
                        $this->_reader->read($dataLength);
                    break;
                case 2: // BOOL
                    $descriptionRecord['data'] =
                        $this->_reader->readUInt16LE() == 1;
                    break;
                case 3: // DWORD
                    $descriptionRecord['data'] = $this->_reader->readUInt32LE();
                    break;
                case 4: // QWORD
                    $descriptionRecord['data'] = $this->_reader->readInt64LE();
                    break;
                case 5: // WORD
                    $descriptionRecord['data'] = $this->_reader->readUInt16LE();
                    break;
                case 6: // GUID
                    $descriptionRecord['data'] = $this->_reader->readGuid();
                    break;
                default:
                    break;
            }
            $this->_descriptionRecords[] = $descriptionRecord;
        }
    }

    /**
     * Returns an array of description records. Each record consists of the
     * following keys.
     *
     *   o languageIndex -- Specifies the index into the
     *     {@link LanguageList Language List Object} that identifies the
     *     language of this attribute. If there is no <i>Language List
     *     Object</i> present, this field is zero.
     *
     *   o streamNumber -- Specifies whether the entry applies to a specific
     *     digital media stream or whether it applies to the whole file. A value
     *     of 0 in this field indicates that it applies to the whole file;
     *     otherwise, the entry applies only to the indicated stream number.
     *     Valid values are between 1 and 127.
     *
     *   o name -- Specifies the name that identifies the attribute being
     *     described.
     *
     *   o data -- Specifies the actual metadata being stored.
     *
     * @return Array
     */
    public function getDescriptionRecords() 
    {
        return $this->_descriptionRecords; 
    }

    /**
     * Sets an array of description records. Each record must consist of the
     * following keys.
     *
     *   o languageIndex -- Specifies the index into the <i>Language List
     *     Object</i> that identifies the language of this attribute. If there
     *     is no <i>Language List Object</i> present, this field is zero.
     *
     *   o streamNumber -- Specifies whether the entry applies to a specific
     *     digital media stream or whether it applies to the whole file. A value
     *     of 0 in this field indicates that it applies to the whole file;
     *     otherwise, the entry applies only to the indicated stream number.
     *     Valid values are between 1 and 127.
     *
     *   o name -- Specifies the name that identifies the attribute being
     *     described.
     *
     *   o data -- Specifies the actual metadata being stored.
     *
     * @return Array
     */
    public function setDescriptionRecords($descriptionRecords)
    {
        $this->_descriptionRecords = $descriptionRecords;
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
        $descriptionRecordsCount = count($this->_descriptionRecords);
        $descriptionRecordsWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $descriptionRecordsCount; $i++) {
            $descriptionRecordsWriter
                ->writeUInt16LE
                    ($this->_descriptionRecords[$i]['languageIndex'])
                ->writeUInt16LE
                    ($this->_descriptionRecords[$i]['streamNumber'])
                ->writeUInt16LE(strlen($name = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_descriptionRecords[$i]['name']) . "\0\0"));
            if (is_string($this->_descriptionRecords[$i]['data'])) {
                $chunks = array();
                if (preg_match
                    ("/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{1" .
                     "2}$/i", $this->_descriptionRecords[$i]['data'])) {
                    $descriptionRecordsWriter
                        ->writeUInt16LE(6)
                        ->writeUInt32LE(16)
                        ->write($name)
                        ->writeGuid($this->_descriptionRecords[$i]['data']);
                } else {
                    /* There is no way to distinguish byte arrays from unicode
                     * strings and hence the need for a list of fields of type
                     * byte array */
                    static $byteArray = array (
    "W\0M\0/\0L\0y\0r\0i\0c\0s\0_\0S\0y\0n\0c\0h\0r\0o\0n\0i\0s\0e\0d\0\0\0",
    "W\0M\0/\0P\0i\0c\0t\0u\0r\0e\0\0\0"
                    ); // TODO: Add to the list if you encounter one

                    if (in_array($name, $byteArray)) {
                        $descriptionRecordsWriter
                            ->writeUInt16LE(1)
                            ->writeUInt32LE
                                (strlen($this->_descriptionRecords[$i]['data']))
                            ->write($name)
                            ->write($this->_descriptionRecords[$i]['data']);
                    } else {
                        $value = iconv
                            ($this->getOption('encoding'), 'utf-16le',
                             $this->_descriptionRecords[$i]['data']);
                        $value = ($value ? $value . "\0\0" : '');
                        $descriptionRecordsWriter
                            ->writeUInt16LE(0)
                            ->writeUInt32LE(strlen($value))
                            ->write($name)
                            ->writeString16($value);
                    }
                }
            } else if (is_bool($this->_descriptionRecords[$i]['data'])) {
                $descriptionRecordsWriter
                    ->writeUInt16LE(2)
                    ->writeUInt32LE(2)
                    ->write($name)
                    ->writeUInt16LE
                        ($this->_descriptionRecords[$i]['data'] ? 1 : 0);
            } else if (is_int($this->_descriptionRecords[$i]['data'])) {
                $descriptionRecordsWriter
                    ->writeUInt16LE(3)
                    ->writeUInt32LE(4)
                    ->write($name)
                    ->writeUInt32LE($this->_descriptionRecords[$i]['data']);
            } else if (is_float($this->_descriptionRecords[$i]['data'])) {
                $descriptionRecordsWriter
                    ->writeUInt16LE(4)
                    ->writeUInt32LE(8)
                    ->write($name)
                    ->writeInt64LE($this->_descriptionRecords[$i]['data']);
            } else {
                // Invalid value and there is nothing to be done
                require_once 'Zend/Media/Asf/Exception.php';
                throw new Zend_Media_Asf_Exception('Invalid data type');
            }
        }

        $this->setSize
            (24 /* for header */ + 2 + $descriptionRecordsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt16LE($descriptionRecordsCount)
               ->write($descriptionRecordsWriter->toString());
    }
}
