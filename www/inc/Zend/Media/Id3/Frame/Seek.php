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
 * @version    $Id: Seek.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Seek</i> frame indicates where other tags in a file/stream can be
 * found. The minimum offset to next tag is calculated from the end of this tag
 * to the beginning of the next. There may only be one seek frame in a tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Seek.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      ID3v2.4.0
 */
final class Zend_Media_Id3_Frame_Seek extends Zend_Media_Id3_Frame
{
    /** @var integer */
    private $_minOffset;

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

        $this->_minOffset = $this->_reader->readInt32BE();
    }

    /**
     * Returns the minimum offset to next tag in bytes.
     *
     * @return integer
     */
    public function getMinimumOffset() 
    {
        return $this->_minOffset; 
    }

    /**
     * Sets the minimum offset to next tag in bytes.
     *
     * @param integer $minOffset The minimum offset.
     */
    public function setMinimumOffset($minOffset)
    {
        $this->_minOffset = $minOffset;
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeInt32BE($this->_minOffset);
    }
}
