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
 * @subpackage ID3
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Mllt.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * To increase performance and accuracy of jumps within a MPEG audio file,
 * frames with time codes in different locations in the file might be useful.
 * The <i>MPEG location lookup table</i> frame includes references that the
 * software can use to calculate positions in the file.
 *
 * The MPEG frames between reference describes how much the frame counter should
 * be increased for every reference. If this value is two then the first
 * reference points out the second frame, the 2nd reference the 4th frame, the
 * 3rd reference the 6th frame etc. In a similar way the bytes between reference
 * and milliseconds between reference points out bytes and milliseconds
 * respectively.
 *
 * Each reference consists of two parts; a certain number of bits that describes
 * the difference between what is said in bytes between reference and the
 * reality and a certain number of bits that describes the difference between
 * what is said in milliseconds between reference and the reality.
 *
 * There may only be one MLLT frame in each tag.
 *
 * @todo       Data parsing and write support
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Mllt.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Mllt extends Zend_Media_Id3_Frame
{
    /** @var integer */
    private $_frames;

    /** @var integer */
    private $_bytes;

    /** @var integer */
    private $_milliseconds;

    /** @var Array */
    private $_deviation = array();

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($this->_reader === null) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception('Write not supported yet');
        }

        $this->_frames = Transform::fromInt16BE(substr($this->_data, 0, 2));
        $this->_bytes = Transform::fromInt32BE(substr($this->_data, 2, 3));
        $this->_milliseconds = Transform::fromInt32BE(substr($this->_data, 5, 3));

        $byteDevBits = Transform::fromInt8($this->_data[8]);
        $millisDevBits = Transform::fromInt8($this->_data[9]);

        // $data = substr($this->_data, 10);
    }

    /**
     * Returns the number of MPEG frames between reference.
     *

     * @return integer
     */
    public function getFrames() 
    {
         return $this->_frames; 
    }

    /**
     * Sets the number of MPEG frames between reference.
     *

     * @param integer $frames The number of MPEG frames.
     */
    public function setFrames($frames) 
    {
         $this->_frames = $frames; 
    }

    /**
     * Returns the number of bytes between reference.
     *

     * @return integer
     */
    public function getBytes() 
    {
         return $this->_bytes; 
    }

    /**
     * Sets the number of bytes between reference.
     *

     * @param integer $bytes The number of bytes.
     */
    public function setBytes($bytes) 
    {
         $this->_bytes = $bytes; 
    }

    /**
     * Returns the number of milliseconds between references.
     *

     * @return integer
     */
    public function getMilliseconds() 
    {
         return $this->_milliseconds; 
    }

    /**
     * Sets the number of milliseconds between references.
     *

     * @param integer $milliseconds The number of milliseconds.
     */
    public function setMilliseconds($milliseconds)
    {
        return $this->_milliseconds;
    }

    /**
     * Returns the deviations as an array. Each value is an array containing two
     * values, ie the deviation in bytes, and the deviation in milliseconds,
     * respectively.
     *

     * @return Array
     */
    public function getDeviation() 
    {
         return $this->_deviation; 
    }

    /**
     * Sets the deviations array. The array must consist of arrays, each of
     * which having two values, the deviation in bytes, and the deviation in
     * milliseconds, respectively.
     *

     * @param Array $deviation The deviations array.
     */
    public function setDeviation($deviation) 
    {
         $this->_deviation = $deviation; 
    }
}
