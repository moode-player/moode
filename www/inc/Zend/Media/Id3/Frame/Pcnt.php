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
 * @version    $Id: Pcnt.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Play counter</i> is simply a counter of the number of times a file has
 * been played. The value is increased by one every time the file begins to
 * play. There may only be one PCNT frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Pcnt.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Pcnt extends Zend_Media_Id3_Frame
{
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

        if ($this->_reader->getSize() > 4) {
            $this->_counter = $this->_reader->readInt64BE(); // UInt64
        } else {
            $this->_counter = $this->_reader->readUInt32BE();
        }
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
        if ($this->_counter > 4294967295) {
            $writer->writeInt64BE($this->_counter); // UInt64
        } else {
            $writer->writeUInt32BE($this->_counter);
        }
    }
}
