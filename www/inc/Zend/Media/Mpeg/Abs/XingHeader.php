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
 * @subpackage MPEG
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: XingHeader.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Bit/Twiddling.php';
require_once 'Zend/Media/Mpeg/Abs/Object.php';
/**#@-*/

/**
 * This class represents the Xing VBR header which is often found in the first
 * frame of an MPEG Audio Bit Stream.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: XingHeader.php 177 2010-03-09 13:13:34Z svollbehr $
 */
class Zend_Media_Mpeg_Abs_XingHeader extends Zend_Media_Mpeg_Abs_Object
{
    /** @var integer */
    private $_frames = false;

    /** @var integer */
    private $_bytes = false;

    /** @var Array */
    private $_toc = array();

    /** @var integer */
    private $_qualityIndicator = false;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the bitstream.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options Array of options.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $flags = $reader->readUInt32BE();

        if (Zend_Bit_Twiddling::testAnyBits($flags, 0x1)) {
            $this->_frames = $this->_reader->readUInt32BE();
        }
        if (Zend_Bit_Twiddling::testAnyBits($flags, 0x2)) {
            $this->_bytes = $this->_reader->readUInt32BE();
        }
        if (Zend_Bit_Twiddling::testAnyBits($flags, 0x4)) {
            $this->_toc = array_merge(unpack('C*', $this->_reader->read(100)));
        }
        if (Zend_Bit_Twiddling::testAnyBits($flags, 0x8)) {
            $this->_qualityIndicator = $this->_reader->readUInt32BE();
        }
    }

    /**
     * Returns the number of frames in the file.
     *
     * @return integer
     */
    public function getFrames() 
    {
        return $this->_frames; 
    }

    /**
     * Returns the number of bytes in the file.
     *
     * @return integer
     */
    public function getBytes() 
    {
        return $this->_bytes; 
    }

    /**
     * Returns the table of contents array. The returned array has a fixed
     * amount of 100 seek points to the file.
     *
     * @return Array
     */
    public function getToc() 
    {
        return $this->_toc; 
    }

    /**
     * Returns the quality indicator. The indicator is from 0 (best quality) to
     * 100 (worst quality).
     *
     * @return integer
     */
    public function getQualityIndicator() 
    {
        return $this->_qualityIndicator; 
    }

    /**
     * Returns the length of the header in bytes.
     *
     * @return integer
     */
    public function getLength()
    {
        return 4 +
            ($this->_frames !== false ? 4 : 0) +
            ($this->_bytes !== false ? 4 : 0) +
            (empty($this->_toc) ? 0 : 100) +
            ($this->_qualityIndicator !== false ? 4 : 0);
    }
}
