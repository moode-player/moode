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
 * @version    $Id: GroupMutualExclusion.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Group Mutual Exclusion Object</i> is used to describe mutual exclusion
 * relationships between groups of streams. This object is organized in terms of
 * records, each containing one or more streams, where a stream in record N
 * cannot coexist with a stream in record M for N != M (however, streams in the
 * same record can coexist). This mutual exclusion object would be used
 * typically for the purpose of language mutual exclusion, and a record would
 * consist of all streams for a particular language.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: GroupMutualExclusion.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_GroupMutualExclusion
    extends Zend_Media_Asf_Object
{
    const MUTEX_LANGUAGE = 'd6e22a00-35da-11d1-9034-00a0c90349be';
    const MUTEX_BITRATE = 'd6e22a01-35da-11d1-9034-00a0c90349be';
    const MUTEX_UNKNOWN = 'd6e22a02-35da-11d1-9034-00a0c90349be';

    /** @var string */
    private $_exclusionType;

    /** @var Array */
    private $_records = array();

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

        $this->_exclusionType = $this->_reader->readGuid();
        $recordCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $recordCount; $i++) {
            $streamNumbersCount = $this->_reader->readUInt16LE();
            $streamNumbers = array();
            for ($j = 0; $j < $streamNumbersCount; $j++) {
                $streamNumbers[] = array
                    ('streamNumbers' => $this->_reader->readUInt16LE());
            }
            $this->_records[] = $streamNumbers;
        }
    }

    /**
     * Returns the nature of the mutual exclusion relationship.
     *
     * @return string
     */
    public function getExclusionType() 
    {
        return $this->_exclusionType; 
    }

    /**
     * Sets the nature of the mutual exclusion relationship.
     *
     * @param string $exclusionType The exclusion type.
     */
    public function setExclusionType($exclusionType)
    {
        $this->_exclusionType = $exclusionType;
    }

    /**
     * Returns an array of records. Each record consists of the following keys.
     *
     *   o streamNumbers -- Specifies the stream numbers for this record. Valid
     *     values are between 1 and 127.
     *
     * @return Array
     */
    public function getRecords() 
    {
        return $this->_records; 
    }

    /**
     * Sets an array of records. Each record is to consist of the following
     * keys.
     *
     *   o streamNumbers -- Specifies the stream numbers for this record. Valid
     *     values are between 1 and 127.
     *
     * @param Array $records The array of records
     */
    public function setRecords($records) 
    {
        $this->_records = $records; 
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
        
        $recordCount = count($this->_records);
        $recordWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $recordCount; $i++) {
            $recordWriter
                ->writeUInt16LE
                    ($streamNumbersCount = count($this->_records[$i]));
            for ($j = 0; $j < $streamNumbersCount; $j++) {
                $recordWriter->writeUInt16LE
                    ($this->_records[$i][$j]['streamNumbers']);
            }
        }

        $this->setSize(24 /* for header */ + $recordWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_exclusionType)
               ->writeUInt16LE($recordCount)
               ->write($recordWriter->toString());
    }
}
