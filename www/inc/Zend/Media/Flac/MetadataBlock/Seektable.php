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
 * @subpackage FLAC
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Seektable.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
/**#@-*/

/**
 * This class represents the seektable metadata block. This is an optional block for storing seek points. It is possible
 * to seek to any given sample in a FLAC stream without a seek table, but the delay can be unpredictable since the
 * bitrate may vary widely within a stream. By adding seek points to a stream, this delay can be significantly reduced.
 * Each seek point takes 18 bytes, so 1% resolution within a stream adds less than 2k. There can be only one SEEKTABLE
 * in a stream, but the table can have any number of seek points. There is also a special 'placeholder' seekpoint which
 * will be ignored by decoders but which can be used to reserve space for future seek point insertion.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Seektable.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_Seektable extends Zend_Media_Flac_MetadataBlock
{
    /** @var Array */
    private $_seekpoints = array();

    /**
     * Constructs the class with given parameters and parses object related data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);

        $seekpointCount = $this->getSize() / 18;
        for ($i = 0; $i < $seekpointCount; $i++) {
            $this->_seekpoints[] = array(
                'sampleNumber'    => $this->_reader->readInt64BE(),
                'offset'          => $this->_reader->readInt64BE(),
                'numberOfSamples' => $this->_reader->readUInt16BE()
            );
        }
    }
    
    /**
     * Returns the seekpoint table. The array consists of items having three keys.
     *
     *   o sampleNumber    --  Sample number of first sample in the target frame, or 0xFFFFFFFFFFFFFFFF for a
     *                         placeholder point.
     *   o offset          --  Offset (in bytes) from the first byte of the first frame header to the first byte of the
     *                         target frame's header.
     *   o numberOfSamples --  Number of samples in the target frame.
     *
     * @return Array
     */
    public function getSeekpoints()
    {
        return $this->_seekpoints;
    }
}
