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
 * @version    $Id: Mcdi.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * This frame is intended for music that comes from a CD, so that the CD can be
 * identified in databases such as the CDDB. The frame consists of a binary dump
 * of the Table Of Contents, TOC, from the CD, which is a header of 4 bytes and
 * then 8 bytes/track on the CD plus 8 bytes for the lead out, making a
 * maximum of 804 bytes. The offset to the beginning of every track on the CD
 * should be described with a four bytes absolute CD-frame address per track,
 * and not with absolute time. When this frame is used the presence of a valid
 * {@link Zend_Media_Id3_Frame_Trck TRCK} frame is required, even if the CD's
 * only got one track. It is recommended that this frame is always added to tags
 * originating from CDs.
 *
 * There may only be one MCDI frame in each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Mcdi.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Mcdi extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_data;

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

        $this->_data = $this->_reader->read($this->_reader->getSize());
    }

    /**
     * Returns the CD TOC binary dump.
     *
     * @return string
     */
    public function getData() 
    {
        return $this->_data; 
    }

    /**
     * Sets the CD TOC binary dump.
     *
     * @param string $data The CD TOC binary dump string.
     */
    public function setData($data) 
    {
        $this->_data = $data; 
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->write($this->_data);
    }
}
