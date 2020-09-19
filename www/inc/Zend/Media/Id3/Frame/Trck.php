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
 * @version    $Id: Trck.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/TextFrame.php';
/**#@-*/

/**
 * The <i>Track number/Position in set</i> frame is a numeric string containing
 * the order number of the audio-file on its original recording. This may be
 * extended with a '/' character and a numeric string containing the total
 * number of tracks/elements on the original recording. E.g. '4/9'.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Trck.php 273 2012-08-21 17:22:52Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Trck extends Zend_Media_Id3_TextFrame
{
    private $_number;
    private $_total;

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

        @list ($this->_number, $this->_total) = explode("/", $this->getText());
    }

    /**
     * Returns the number.
     *
     * @return integer
     */
    public function getNumber()
    {
        return intval($this->_number);
    }

    /**
     * Sets the number.
     *
     * @param integer $number The number.
     */
    public function setNumber($part)
    {
        $this->setText
            ($this->_number = strval($part) .
             ($this->_total ? '/' . $this->_total : ''),
             Zend_Media_Id3_Encoding::ISO88591);
    }

    /**
     * Returns the total number.
     *
     * @return integer
     */
    public function getTotal()
    {
        return intval($this->_total);
    }

    /**
     * Sets the total number.
     *
     * @param integer $total The total number.
     */
    public function setTotal($total)
    {
        $this->setText
            (($this->_number ? $this->_number : '?') . "/" .
             ($this->_total = strval($total)),
             Zend_Media_Id3_Encoding::ISO88591);
    }
}
