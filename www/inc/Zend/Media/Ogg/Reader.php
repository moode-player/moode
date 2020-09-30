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
 * @subpackage Ogg
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Reader.php 239 2011-06-04 09:35:48Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
require_once 'Zend/Io/FileReader.php';
require_once 'Zend/Media/Ogg/Page.php';
/**#@-*/

/**
 * This class is a Zend_Io_Reader specialization that can read a file containing the Ogg bitstream format version 0 as
 * described in {@link http://tools.ietf.org/html/rfc3533 RFC3533}. It is a general, freely-available encapsulation
 * format for media streams. It is able to encapsulate any kind and number of video and audio encoding formats as well
 * as other data streams in a single bitstream.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Ogg
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Reader.php 239 2011-06-04 09:35:48Z svollbehr $
 * @todo       Currently supports only one logical bitstream
 */
final class Zend_Media_Ogg_Reader extends Zend_Io_Reader
{
    /** @var Array */
    private $_pages = array();

    /** @var integer */
    private $_currentPageNumber = 0;

    /** @var integer */
    private $_currentPagePosition = 0;

    /**
     * Constructs the Ogg class with given file.
     *
     * @param string $filename The path to the file.
     * @throws Zend_Io_Exception if an error occur in stream handling.
     * @throws Zend_Media_Ogg_Exception if an error occurs in Ogg bitstream reading.
     */
    public function __construct($filename)
    {
        $reader = new Zend_Io_FileReader($filename);
        $fileSize = $reader->getSize();
        while ($reader->getOffset() < $fileSize) {
            $this->_pages[] = array(
                'offset' => $reader->getOffset(),
                'page'   => $page = new Zend_Media_Ogg_Page($reader)
            );
            $this->_size += $page->getPageSize();
            $reader->skip($page->getPageSize());
        }
        $reader->setOffset
            ($this->_pages[$this->_currentPageNumber]['offset'] +
             $this->_pages[$this->_currentPageNumber]['page']->getHeaderSize());
        $this->_fd = $reader->getFileDescriptor();
    }

    /**
     * Overwrite the method to return the current point of operation within the Ogg bitstream.
     *
     * @return integer
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function getOffset()
    {
        $offset = 0;
        for ($i = 0; $i < $this->_currentPageNumber; $i++) {
            $offset += $this->_pages[$i]['page']->getPageSize();
        }
        return $offset += $this->_currentPagePosition;
    }

    /**
     * Overwrite the method to set the point of operation within the Ogg bitstream.
     *
     * @param integer $offset The new point of operation.
     * @return void
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function setOffset($offset)
    {
        $streamSize = 0;
        for ($i = 0, $pageCount = count($this->_pages); $i < $pageCount; $i++) {
            if (($streamSize + $this->_pages[$i]['page']->getPageSize()) >= $offset) {
                $this->_currentPageNumber = $i;
                $this->_currentPagePosition = $offset - $streamSize;
                parent::setOffset
                    ($this->_pages[$i]['offset'] + $this->_pages[$i]['page']->getHeaderSize() +
                     $this->_currentPagePosition);
                break;
            }
            $streamSize += $this->_pages[$i]['page']->getPageSize();
        }
    }

    /**
     * Overwrite the method to jump <var>size</var> amount of bytes in the Ogg bitstream.
     *
     * @param integer $size The amount of bytes to jump within the Ogg bitstream.
     * @return void
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function skip($size)
    {
        $currentPageSize = $this->_pages[$this->_currentPageNumber]['page']->getPageSize();
        if (($this->_currentPagePosition + $size) >= $currentPageSize) {
            parent::skip
                (($currentPageSize - $this->_currentPagePosition) +
                  $this->_pages[++$this->_currentPageNumber]['page']->getHeaderSize() +
                 ($this->_currentPagePosition = ($size - ($currentPageSize - $this->_currentPagePosition))));
        } else {
            $this->_currentPagePosition += $size;
            parent::skip($size);
        }
    }

    /**
     * Overwrite the method to read bytes within the Ogg bitstream.
     *
     * @param integer $length The amount of bytes to read within the Ogg bitstream.
     * @return string
     * @throws Zend_Io_Exception if an I/O error occurs
     */
    public function read($length)
    {
        $currentPageSize = $this->_pages[$this->_currentPageNumber]['page']->getPageSize();
        if (($this->_currentPagePosition + $length) >= $currentPageSize) {
            $buffer = parent::read($currentPageSize - $this->_currentPagePosition);
            parent::skip($this->_pages[++$this->_currentPageNumber]['page']->getHeaderSize());
            return $buffer . parent::read
                ($this->_currentPagePosition = ($length - ($currentPageSize - $this->_currentPagePosition)));
        } else {
            $buffer = parent::read($length);
            $this->_currentPagePosition += $length;
            return $buffer;
        }
    }

    /**
     * Returns the underlying Ogg page at given number.
     *
     * @param integer $pageNumber The number of the page to return.
     * @return Zend_Media_Ogg_Page
     */
    public function getPage($pageNumber)
    {
        return $this->_pages[$pageNumber]['page'];
    }

    /**
     * Returns the underlying Ogg page number.
     *
     * @return integer
     */
    public function getCurrentPageNumber()
    {
        return $this->_currentPageNumber;
    }
    
    /**
     * Returns the underlying Ogg page position, in bytes.
     *
     * @return integer
     */
    public function getCurrentPagePosition()
    {
        return $this->_currentPagePosition;
    }
}
