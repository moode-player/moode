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
 * @version    $Id: AdvancedContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Advanced Content Encryption Object</i> lets authors protect content by
 * using Next Generation Windows Media Digital Rights Management for Network
 * Devices.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: AdvancedContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_AdvancedContentEncryption
    extends Zend_Media_Asf_Object
{
    const WINDOWS_MEDIA_DRM_NETWORK_DEVICES =
        '7a079bb6-daa4-4e12-a5ca-91d3 8dc11a8d';

    /** @var Array */
    private $_contentEncryptionRecords = array();

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
        $contentEncryptionRecordsCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $contentEncryptionRecordsCount; $i++) {
            $entry = array('systemId' => $this->_reader->readGuid(),
                'systemVersion' => $this->_reader->readUInt32LE(),
                'streamNumbers' => array());
            $encryptedObjectRecordCount = $this->_reader->readUInt16LE();
            for ($j = 0; $j < $encryptedObjectRecordCount; $j++) {
                $this->_reader->skip(4);
                $entry['streamNumbers'][] = $this->_reader->readUInt16LE();
            }
            $dataCount = $this->_reader->readUInt32LE();
            $entry['data'] = $this->_reader->read($dataCount);
            $this->_contentEncryptionRecords[] = $entry;
        }
    }

    /**
     * Returns an array of content encryption records. Each record consists of
     * the following keys.
     *
     *   o systemId -- Specifies the unique identifier for the content
     *     encryption system.
     *
     *   o systemVersion -- Specifies the version of the content encryption
     *     system.
     *
     *   o streamNumbers -- An array of stream numbers a particular Content
     *     Encryption Record is associated with. A value of 0 in this field
     *     indicates that it applies to the whole file; otherwise, the entry
     *     applies only to the indicated stream number.
     *
     *   o data -- The content protection data for this Content Encryption
     *     Record.
     *
     * @return Array
     */
    public function getContentEncryptionRecords()
    {
        return $this->_contentEncryptionRecords;
    }

    /**
     * Sets the array of content encryption records. Each record must consist of
     * the following keys.
     *
     *   o systemId -- Specifies the unique identifier for the content
     *     encryption system.
     *
     *   o systemVersion -- Specifies the version of the content encryption
     *     system.
     *
     *   o streamNumbers -- An array of stream numbers a particular Content
     *     Encryption Record is associated with. A value of 0 in this field
     *     indicates that it applies to the whole file; otherwise, the entry
     *     applies only to the indicated stream number.
     *
     *   o data -- The content protection data for this Content Encryption
     *     Record.
     *
     * @param Array $contentEncryptionRecords The array of content encryption
     *        records.
     */
    public function setContentEncryptionRecords($contentEncryptionRecords)
    {
        $this->_contentEncryptionRecords = $contentEncryptionRecords;
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
        $contentEncryptionRecordsCount =
            count($this->_contentEncryptionRecords);
        $contentEncryptionRecordsWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $contentEncryptionRecordsCount; $i++) {
            $contentEncryptionRecordsWriter
                ->writeGuid($this->_contentEncryptionRecords['systemId'])
                ->writeUInt32LE
                    ($this->_contentEncryptionRecords['systemVersion'])
                ->writeUInt16LE
                    ($encryptedObjectRecordCount =
                     $this->_contentEncryptionRecords['streamNumbers']);
            for ($j = 0; $j < $encryptedObjectRecordCount; $j++) {
                $contentEncryptionRecordsWriter
                    ->writeUInt16LE(1)
                    ->writeUInt16LE(2)
                    ->writeUInt16LE
                        ($this->_contentEncryptionRecords['streamNumbers'][$j]);
            }
            $contentEncryptionRecordsWriter
                ->writeUInt32LE
                    (strlen($this->_contentEncryptionRecords['data']))
                ->write($this->_contentEncryptionRecords['data']);
        }

        $this->setSize
            (24 /* for header */ + 2 +
             $contentEncryptionRecordsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt16LE($contentEncryptionRecordsCount)
               ->write($contentEncryptionRecordsWriter->toString());
    }
}
