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
 * @version    $Id: HeaderExtension.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object/Container.php';
/**#@-*/

/**
 * The <i>Header Extension Object</i> allows additional functionality to be
 * added to an ASF file while maintaining backward compatibility. The Header
 * Extension Object is a container containing zero or more additional extended
 * header objects.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: HeaderExtension.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_HeaderExtension
    extends Zend_Media_Asf_Object_Container
{
    /** @var string */
    private $_reserved1;

    /** @var integer */
    private $_reserved2;

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
        $this->_reserved2 = $this->_reader->readUInt16LE();
        $this->_reader->skip(4);
        $this->constructObjects
            (array
             (self::EXTENDED_STREAM_PROPERTIES => 'ExtendedStreamProperties',
                self::ADVANCED_MUTUAL_EXCLUSION => 'AdvancedMutualExclusion',
                self::GROUP_MUTUAL_EXCLUSION => 'GroupMutualExclusion',
                self::STREAM_PRIORITIZATION  => 'StreamPrioritization',
                self::BANDWIDTH_SHARING  => 'BandwidthSharing',
                self::LANGUAGE_LIST  => 'LanguageList',
                self::METADATA  => 'Metadata',
                self::METADATA_LIBRARY => 'MetadataLibrary',
                self::INDEX_PARAMETERS  => 'IndexParameters',
                self::MEDIA_OBJECT_INDEX_PARAMETERS =>
                    'MediaObjectIndexParameters',
                self::TIMECODE_INDEX_PARAMETERS => 'TimecodeIndexParameters',
                self::COMPATIBILITY => 'Compatibility',
                self::ADVANCED_CONTENT_ENCRYPTION =>
                    'AdvancedContentEncryption',
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
            (24 /* for header */ + 22 + $objectsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_reserved1)
               ->writeUInt16LE($this->_reserved2)
               ->writeUInt32LE($objectsWriter->getSize())
               ->write($objectsWriter->toString());
    }
}
