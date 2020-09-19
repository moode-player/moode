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
 * @version    $Id: VbriHeader.php 234 2011-05-25 14:49:36Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Bit/Twiddling.php';
require_once 'Zend/Media/Mpeg/Abs/Object.php';
/**#@-*/

/**
 * This class represents the Fraunhofer IIS VBR header which is often found in
 * the first frame of an MPEG Audio Bit Stream.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: VbriHeader.php 234 2011-05-25 14:49:36Z svollbehr $
 */
class Zend_Media_Mpeg_Abs_VbriHeader extends Zend_Media_Mpeg_Abs_Object
{
    /** @var integer */
    private $_version;

    /** @var integer */
    private $_delay;

    /** @var integer */
    private $_qualityIndicator;

    /** @var integer */
    private $_bytes;

    /** @var integer */
    private $_frames;

    /** @var Array */
    private $_toc = array();

    /** @var integer */
    private $_tocFramesPerEntry;

    /** @var integer */
    private $_length;

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
        
        $offset = $this->_reader->getOffset();
        $this->_version = $this->_reader->readUInt16BE();
        $this->_delay = $this->_reader->readUInt16BE();
        $this->_qualityIndicator = $this->_reader->readUInt16BE();
        $this->_bytes = $this->_reader->readUInt32BE();
        $this->_frames = $this->_reader->readUInt32BE();
        $tocEntries = $this->_reader->readUInt16BE();
        $tocEntryScale = $this->_reader->readUInt16BE();
        $tocEntrySize = $this->_reader->readUInt16BE();
        $this->_tocFramesPerEntry = $this->_reader->readUInt16BE();
        $this->_toc = array_merge(unpack(($tocEntrySize == 1) ? 'C*' :
            ($tocEntrySize == 2) ? 'n*' : 'N*',
            $this->_reader->read($tocCount * $tocEntrySize)));
        foreach ($this->_toc as $key => $value) {
            $this->_toc[$key] = $tocEntryScale * $value;
        }
        $this->_length = $this->_reader->getOffset() - $offset;
    }

    /**
     * Returns the header version.
     *
     * @return integer
     */
    public function getVersion() 
    {
        return $this->_version; 
    }

    /**
     * Returns the delay.
     *
     * @return integer
     */
    public function getDelay() 
    {
        return $this->_delay; 
    }

    /**
     * Returns the quality indicator. Return value varies from 0 (best quality)
     * to 100 (worst quality).
     *
     * @return integer
     */
    public function getQualityIndicator() 
    {
        return $this->_qualityIndicator; 
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
     * Returns the number of frames in the file.
     *
     * @return integer
     */
    public function getFrames() 
    {
        return $this->_frames; 
    }

    /**
     * Returns the table of contents array.
     *
     * @return Array
     */
    public function getToc() 
    {
        return $this->_toc; 
    }

    /**
     * Returns the number of frames per TOC entry.
     *
     * @return integer
     */
    public function getTocFramesPerEntry() 
    {
        return $this->_tocFramesPerEntry; 
    }

    /**
     * Returns the length of the header in bytes.
     *
     * @return integer
     */
    public function getLength() 
    {
        return $this->_length; 
    }
}
