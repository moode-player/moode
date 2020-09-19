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
 * @version    $Id: Popm.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The purpose of the <i>Popularimeter</i> frame is to specify how good an audio
 * file is. Many interesting applications could be found to this frame such as a
 * playlist that features better audio files more often than others or it could
 * be used to profile a person's taste and find other good files by comparing
 * people's profiles. The frame contains the email address to the user, one
 * rating byte and a four byte play counter, intended to be increased with one
 * for every time the file is played.
 *
 * The rating is 1-255 where 1 is worst and 255 is best. 0 is unknown. If no
 * personal counter is wanted it may be omitted. When the counter reaches all
 * one's, one byte is inserted in front of the counter thus making the counter
 * eight bits bigger in the same away as the play counter
 * {@link Zend_Media_Id3_Frame_Pcnt PCNT}. There may be more than one POPM frame
 * in each tag, but only one with the same email address.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Popm.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Popm extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var integer */
    private $_rating = 0;

    /** @var integer */
    private $_counter = 0;

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

        list ($this->_owner) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(strlen($this->_owner) + 1);
        $this->_rating = $this->_reader->readUInt8();

        if ($this->_reader->getSize() - strlen($this->_owner) - 2 > 4) {
            $this->_counter =
                $this->_reader->readInt64BE(); // UInt64
        } else if ($this->_reader->available() > 0) {
            $this->_counter = $this->_reader->readUInt32BE();
        }
    }

    /**
     * Returns the owner identifier string.
     *
     * @return string
     */
    public function getOwner() 
    {
         return $this->_owner; 
    }

    /**
     * Sets the owner identifier string.
     *
     * @param string $owner The owner identifier string.
     */
    public function setOwner($owner) 
    {
         return $this->_owner = $owner; 
    }

    /**
     * Returns the user rating.
     *
     * @return integer
     */
    public function getRating() 
    {
         return $this->_rating; 
    }

    /**
     * Sets the user rating.
     *
     * @param integer $rating The user rating.
     */
    public function setRating($rating) 
    {
         $this->_rating = $rating; 
    }

    /**
     * Returns the counter.
     *
     * @return integer
     */
    public function getCounter() 
    {
         return $this->_counter; 
    }

    /**
     * Adds counter by one.
     */
    public function addCounter() 
    {
         $this->_counter++; 
    }

    /**
     * Sets the counter value.
     *
     * @param integer $counter The counter value.
     */
    public function setCounter($counter) 
    {
         $this->_counter = $counter; 
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeString8($this->_owner, 1)
               ->writeInt8($this->_rating);
        if ($this->_counter > 0xffffffff) {
            $writer->writeInt64BE($this->_counter);
        } else if ($this->_counter > 0) {
            $writer->writeUInt32BE($this->_counter);
        }
    }
}
