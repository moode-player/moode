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
 * @version    $Id: NumberFrame.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/TextFrame.php';
/**#@-*/

/**
 * A base class for all the text frames representing an unsigned integer.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: NumberFrame.php 177 2010-03-09 13:13:34Z svollbehr $
 */
abstract class Zend_Media_Id3_NumberFrame
    extends Zend_Media_Id3_TextFrame
{
    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        Zend_Media_Id3_Frame::__construct($reader, $options);

        $this->setEncoding(Zend_Media_Id3_Encoding::ISO88591);

        if ($this->_reader === null) {
            return;
        }

        $this->_reader->skip(1);
        $this->setText($this->_reader->readString8($this->_reader->getSize()));
    }

    /**
     * Returns the integer value of the text.
     *
     * @return integer
     */
    public function getValue()
    {
        return doubleval($this->getText());
    }

    /**
     * Sets the integer value of the text.
     *
     * @param integer $value The integer value of the text.
     */
    public function setValue($value)
    {
        $this->setText(strval($value), Zend_Media_Id3_Encoding::ISO88591);
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $this->setEncoding(Zend_Media_Id3_Encoding::ISO88591);
        parent::_writeData($writer);
    }
}
