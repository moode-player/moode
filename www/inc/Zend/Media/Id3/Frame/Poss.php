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
 * @version    $Id: Poss.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Timing.php';
/**#@-*/

/**
 * The <i>Position synchronisation frame</i> delivers information to the
 * listener of how far into the audio stream he picked up; in effect, it states
 * the time offset from the first frame in the stream. There may only be one
 * POSS frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Poss.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Poss extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Timing
{
    /** @var integer */
    private $_format = Zend_Media_Id3_Timing::MPEG_FRAMES;

    /** @var integer */
    private $_position;

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

        $this->_format = $this->_reader->readUInt8();
        $this->_position = $this->_reader->readUInt32BE();
    }

    /**
     * Returns the timing format.
     *
     * @return integer
     */
    public function getFormat() 
    {
        return $this->_format; 
    }

    /**
     * Sets the timing format.
     *
     * @see Zend_Media_Id3_Timing
     * @param integer $format The timing format.
     */
    public function setFormat($format) 
    {
        $this->_format = $format; 
    }

    /**
     * Returns the position where in the audio the listener starts to receive,
     * i.e. the beginning of the next frame.
     *
     * @return integer
     */
    public function getPosition() 
    {
        return $this->_position; 
    }

    /**
     * Sets the position where in the audio the listener starts to receive,
     * i.e. the beginning of the next frame, using given format.
     *
     * @param integer $position The position.
     * @param integer $format The timing format.
     */
    public function setPosition($position, $format = null)
    {
        $this->_position = $position;
        if ($format !== null) {
            $this->setFormat($format);
        }
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_format)
               ->writeUInt32BE($this->_position);
    }
}
