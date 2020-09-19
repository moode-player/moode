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
 * @version    $Id: Aspi.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * Audio files with variable bit rates are intrinsically difficult to deal with
 * in the case of seeking within the file. The <i>Audio seek point index</i> or
 * ASPI frame makes seeking easier by providing a list a seek points within the
 * audio file. The seek points are a fractional offset within the audio data,
 * providing a starting point from which to find an appropriate point to start
 * decoding. The presence of an ASPI frame requires the existence of a
 * {@link Zend_Media_Id3_Frame_Tlen TLEN} frame, indicating the duration of the
 * file in milliseconds. There may only be one audio seek point index frame in
 * a tag.
 *
 * @todo       Data parsing and write support
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Aspi.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */
final class Zend_Media_Id3_Frame_Aspi extends Zend_Media_Id3_Frame
{
    /** @var integer */
    private $_dataStart;

    /** @var integer */
    private $_dataLength;

    /** @var integer */
    private $_size;

    /** @var Array */
    private $_fractions = array();

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

        $this->_dataStart = $this->_reader->readInt32BE();
        $this->_dataLength = $this->_reader->readInt32BE();
        $this->_size = $this->_reader->readInt16BE();

        $bitsPerPoint = $this->_reader->readInt8($this->_data[10]);
        /*for ($i = 0, $offset = 11; $i < $this->_size; $i++) {
            if ($bitsPerPoint == 16) {
                $this->_fractions[$i] = substr($this->_data, $offset, 2);
                $offset += 2;
            } else {
                $this->_fractions[$i] = substr($this->_data, $offset, 1);
                $offset ++;
            }
        }*/
    }

    /**
     * Returns the byte offset from the beginning of the file.
     *
     * @return integer
     */
    public function getDataStart() 
    {
         return $this->_dataStart; 
    }

    /**
     * Sets the byte offset from the beginning of the file.
     *
     * @param integer $dataStart The offset.
     */
    public function setDataStart($dataStart) 
    {
         $this->_dataStart = $dataStart; 
    }

    /**
     * Returns the byte length of the audio data being indexed.
     *
     * @return integer
     */
    public function getDataLength() 
    {
         return $this->_dataLength; 
    }

    /**
     * Sets the byte length of the audio data being indexed.
     *
     * @param integer $dataLength The length.
     */
    public function setDataLength($dataLength)
    {
        $this->_dataLength = $dataLength;
    }

    /**
     * Returns the number of index points in the frame.
     *
     * @return integer
     */
    public function getSize() 
    {
         return count($this->_fractions); 
    }

    /**
     * Returns the numerator of the fraction representing a relative position in
     * the data or <var>false</var> if index not defined. The denominator is 2
     * to the power of b.
     *
     * @param integer $index The fraction numerator.
     * @return integer
     */
    public function getFractionAt($index)
    {
        if (isset($this->_fractions[$index])) {
            return $this->_fractions[$index];
        }
        return false;
    }
}
