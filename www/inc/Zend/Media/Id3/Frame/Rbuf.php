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
 * @version    $Id: Rbuf.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * Sometimes the server from which an audio file is streamed is aware of
 * transmission or coding problems resulting in interruptions in the audio
 * stream. In these cases, the size of the buffer can be recommended by the
 * server using the <i>Recommended buffer size</i> frame. If the embedded info
 * flag is set then this indicates that an ID3 tag with the maximum size
 * described in buffer size may occur in the audio stream. In such case the tag
 * should reside between two MPEG frames, if the audio is MPEG encoded. If the
 * position of the next tag is known, offset to next tag may be used. The offset
 * is calculated from the end of tag in which this frame resides to the first
 * byte of the header in the next. This field may be omitted. Embedded tags are
 * generally not recommended since this could render unpredictable behaviour
 * from present software/hardware.
 *
 * For applications like streaming audio it might be an idea to embed tags into
 * the audio stream though. If the clients connects to individual connections
 * like HTTP and there is a possibility to begin every transmission with a tag,
 * then this tag should include a recommended buffer size frame. If the client
 * is connected to a arbitrary point in the stream, such as radio or multicast,
 * then the recommended buffer size frame should be included in every tag.
 *
 * The buffer size should be kept to a minimum. There may only be one RBUF
 * frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Rbuf.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Rbuf extends Zend_Media_Id3_Frame
{
    /**
     * A flag to denote that an ID3 tag with the maximum size described in
     * buffer size may occur in the audio stream.
     */
    const EMBEDDED = 0x1;

    /** @var integer */
    private $_bufferSize;

    /** @var integer */
    private $_infoFlags;

    /** @var integer */
    private $_offset = 0;

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
            return;
        }

        // Who designs frames with 3 byte integers??
        $this->_reader = new Zend_Io_StringReader
            ("\0" . $this->_reader->read($this->_reader->getSize()));

        $this->_bufferSize = $this->_reader->readUInt32BE();
        $this->_infoFlags = $this->_reader->readInt8();
        if ($this->_reader->available()) {
            $this->_offset = $this->_reader->readInt32BE();
        }
    }

    /**
     * Returns the buffer size.
     *
     * @return integer
     */
    public function getBufferSize() 
    {
        return $this->_bufferSize; 
    }

    /**
     * Sets the buffer size.
     *
     * @param integer $size The buffer size.
     */
    public function setBufferSize($bufferSize)
    {
        $this->_bufferSize = $bufferSize;
    }

    /**
     * Checks whether or not the flag is set. Returns <var>true</var> if the
     * flag is set, <var>false</var> otherwise.
     *
     * @param integer $flag The flag to query.
     * @return boolean
     */
    public function hasInfoFlag($flag)
    {
        return ($this->_infoFlags & $flag) == $flag;
    }

    /**
     * Returns the flags byte.
     *
     * @return integer
     */
    public function getInfoFlags() 
    {
        return $this->_infoFlags; 
    }

    /**
     * Sets the flags byte.
     *
     * @param string $flags The flags byte.
     */
    public function setInfoFlags($infoFlags) 
    {
        $this->_infoFlags = $infoFlags; 
    }

    /**
     * Returns the offset to next tag.
     *
     * @return integer
     */
    public function getOffset() 
    {
        return $this->_offset; 
    }

    /**
     * Sets the offset to next tag.
     *
     * @param integer $offset The offset.
     */
    public function setOffset($offset) 
    {
        $this->_offset = $offset; 
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $tmp = new Zend_Io_StringWriter();
        $tmp->writeUInt32BE($this->_bufferSize);

        $writer->write(substr($tmp->toString(), 1, 3))
               ->writeInt8($this->_infoFlags)
               ->writeInt32BE($this->_offset);
    }
}
