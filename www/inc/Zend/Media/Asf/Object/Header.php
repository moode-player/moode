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
 * @version    $Id: Header.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object/Container.php';
/**#@-*/

/**
 * The role of the header object is to provide a well-known byte sequence at the
 * beginning of ASF files and to contain all the information that is needed to
 * properly interpret the information within the data object. The header object
 * can optionally contain metadata such as bibliographic information.
 *
 * Of the three top-level ASF objects, the header object is the only one that
 * contains other ASF objects. The header object may include a number of
 * standard objects including, but not limited to:
 *
 *  o File Properties Object -- Contains global file attributes.
 *  o Stream Properties Object -- Defines a digital media stream and its
 *    characteristics.
 *  o Header Extension Object -- Allows additional functionality to be added to
 *    an ASF file while maintaining backward compatibility.
 *  o Content Description Object -- Contains bibliographic information.
 *  o Script Command Object -- Contains commands that can be executed on the
 *    playback timeline.
 *  o Marker Object -- Provides named jump points within a file.
 *
 * Note that objects in the header object may appear in any order. To be valid,
 * the header object must contain a
 * {@link Zend_Media_Asf_Object_FileProperties File Properties Object}, a
 * {@link Zend_Media_Asf_Object_HeaderExtension Header Extension Object}, and at
 * least one {@link Zend_Media_Asf_Object_StreamProperties Stream Properties
 * Object}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Header.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_Header extends Zend_Media_Asf_Object_Container
{
    /** @var integer */
    private $_reserved1;

    /** @var integer */
    private $_reserved2;

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_reader->skip(4);
        $this->_reserved1 = $this->_reader->readInt8();
        $this->_reserved2 = $this->_reader->readInt8();
        $this->constructObjects
            (array
             (self::FILE_PROPERTIES => 'FileProperties',
                self::STREAM_PROPERTIES => 'StreamProperties',
                self::HEADER_EXTENSION => 'HeaderExtension',
                self::CODEC_LIST => 'CodecList',
                self::SCRIPT_COMMAND => 'ScriptCommand',
                self::MARKER => 'Marker',
                self::BITRATE_MUTUAL_EXCLUSION => 'BitrateMutualExclusion',
                self::ERROR_CORRECTION => 'ErrorCorrection',
                self::CONTENT_DESCRIPTION => 'ContentDescription',
                self::EXTENDED_CONTENT_DESCRIPTION =>
                    'ExtendedContentDescription',
                self::CONTENT_BRANDING => 'ContentBranding',
                self::STREAM_BITRATE_PROPERTIES => 'StreamBitrateProperties',
                self::CONTENT_ENCRYPTION => 'ContentEncryption',
                self::EXTENDED_CONTENT_ENCRYPTION =>
                    'ExtendedContentEncryption',
                self::DIGITAL_SIGNATURE => 'DigitalSignature',
                self::PADDING => 'Padding'));
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
        $objectsWriter = new Zend_Io_StringWriter();
        foreach ($this->getObjects() as $objects) {
            foreach ($objects as $object) {
                $object->write($objectsWriter);
            }
        }
        
        $this->setSize
            (24 /* for header */ + 6 + $objectsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt32LE($this->getObjectCount())
               ->writeInt8($this->_reserved1)
               ->writeInt8($this->_reserved2)
               ->write($objectsWriter->toString());
    }
}
