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
 * @version    $Id: StreamBitrateProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Stream Bitrate Properties Object</i> defines the average bit rate of
 * each digital media stream.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: StreamBitrateProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_StreamBitrateProperties
    extends Zend_Media_Asf_Object
{
    /** @var Array */
    private $_bitrateRecords = array();

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

        $bitrateRecordsCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $bitrateRecordsCount; $i++) {
            $this->_bitrateRecords[] = array
                ('streamNumber' =>
                     ($tmp = $this->_reader->readInt16LE()) & 0x1f,
                 'flags' => $tmp >> 5,
                 'averageBitrate' => $this->_reader->readUInt32LE());
        }
    }

    /**
     * Returns an array of bitrate records. Each record consists of the
     * following keys.
     *
     *   o streamNumber -- Specifies the number of this stream described by this
     *     record. 0 is an invalid stream. Valid values are between 1 and 127.
     *
     *   o flags -- These bits are reserved and should be set to 0.
     *
     *   o averageBitrate -- Specifies the average bit rate of the stream in
     *     bits per second. This value should include an estimate of ASF packet
     *     and payload overhead associated with this stream.
     *
     * @return Array
     */
    public function getBitrateRecords() 
    {
        return $this->_bitrateRecords; 
    }

    /**
     * Sets an array of bitrate records. Each record consists of the following
     * keys.
     *
     *   o streamNumber -- Specifies the number of this stream described by this
     *     record. 0 is an invalid stream. Valid values are between 1 and 127.
     *
     *   o flags -- These bits are reserved and should be set to 0.
     *
     *   o averageBitrate -- Specifies the average bit rate of the stream in bits
     *     per second. This value should include an estimate of ASF packet and
     *     payload overhead associated with this stream.
     *
     * @param Array $bitrateRecords The array of bitrate records.
     */
    public function setBitrateRecords($bitrateRecords)
    {
        $this->_bitrateRecords = $bitrateRecords;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $bitrateRecordsCount = count($this->_bitrateRecords);

        $this->setSize
            (24 /* for header */ + 2 + $bitrateRecordsCount * 6);

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt16LE($bitrateRecordsCount);
        for ($i = 0; $i < $bitrateRecordsCount; $i++) {
            $writer->writeUInt16LE
                        (($this->_bitrateRecords[$i]['flags'] << 5) |
                         ($this->_bitrateRecords[$i]['streamNumber'] & 0x1f))
                   ->writeUInt32LE
                        ($this->_bitrateRecords[$i]['averageBitrate']);
        }
    }
}
