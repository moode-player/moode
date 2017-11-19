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
 * @version    $Id: StreamPrioritization.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Stream Prioritization Object</i> indicates the author's intentions as
 * to which streams should or should not be dropped in response to varying
 * network congestion situations. There may be special cases where this
 * preferential order may be ignored (for example, the user hits the 'mute'
 * button). Generally it is expected that implementations will try to honor the
 * author's preference.
 *
 * The priority of each stream is indicated by how early in the list that
 * stream's stream number is listed (in other words, the list is ordered in
 * terms of decreasing priority).
 *
 * The Mandatory flag field shall be set if the author wants that stream kept
 * 'regardless'. If this flag is not set, then that indicates that the stream
 * should be dropped in response to network congestion situations. Non-mandatory
 * streams must never be assigned a higher priority than mandatory streams.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: StreamPrioritization.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_StreamPrioritization
    extends Zend_Media_Asf_Object
{
    /** @var Array */
    private $_priorityRecords = array();

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

        $priorityRecordCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $priorityRecordCount; $i++) {
            $this->_priorityRecords[] = array
                ('streamNumber' => $this->_reader->readUInt16LE(),
                 'flags'        => $this->_reader->readUInt16LE());
        }
    }

    /**
     * Returns an array of records. Each record consists of the following keys.
     *
     *   o streamNumber -- Specifies the stream number. Valid values are between
     *     1 and 127.
     *
     *   o flags -- Specifies the flags. The mandatory flag is the bit 1 (LSB).
     *
     * @return Array
     */
    public function getPriorityRecords() 
    {
        return $this->_priorityRecords; 
    }

    /**
     * Sets the array of records. Each record consists of the following keys.
     *
     *   o streamNumber -- Specifies the stream number. Valid values are between
     *     1 and 127.
     *
     *   o flags -- Specifies the flags. The mandatory flag is the bit 1 (LSB).
     *
     * @param Array $priorityRecords The array of records.
     */
    public function setPriorityRecords($priorityRecords)
    {
        $this->_priorityRecords = $priorityRecords;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $priorityRecordCount = count($this->_priorityRecords);

        $this->setSize
            (24 /* for header */ + 2 + $priorityRecordCount * 4);

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt16LE($priorityRecordCount);
        for ($i = 0; $i < $priorityRecordCount; $i++) {
            $writer->writeUInt16LE($this->_priorityRecords[$i]['streamNumber'])
                   ->writeUInt16LE($this->_priorityRecords[$i]['flags']);
        }
    }
}
