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
 * @subpackage ASF
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ContentDescription.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Content Description Object</i> lets authors record well-known data
 * describing the file and its contents. This object is used to store standard
 * bibliographic information such as title, author, copyright, description, and
 * rating information. This information is pertinent to the entire file.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ContentDescription.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ContentDescription
    extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_title;

    /** @var string */
    private $_author;

    /** @var string */
    private $_copyright;

    /** @var string */
    private $_description;

    /** @var string */
    private $_rating;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $titleLen = $this->_reader->readUInt16LE();
        $authorLen = $this->_reader->readUInt16LE();
        $copyrightLen = $this->_reader->readUInt16LE();
        $descriptionLen = $this->_reader->readUInt16LE();
        $ratingLen = $this->_reader->readUInt16LE();

        $this->_title = iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($titleLen));
        $this->_author =  iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($authorLen));
        $this->_copyright =  iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($copyrightLen));
        $this->_description =  iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($descriptionLen));
        $this->_rating =  iconv
            ('utf-16le', $this->getOption('encoding'),
             $this->_reader->readString16($ratingLen));
    }

    /**
     * Returns the title information.
     *
     * @return string
     */
    public function getTitle() 
    {
        return $this->_title; 
    }

    /**
     * Sets the title information.
     *
     * @param string $title The title information.
     */
    public function setTitle($title) 
    {
        $this->_title = $title; 
    }

    /**
     * Returns the author information.
     *
     * @return string
     */
    public function getAuthor() 
    {
        return $this->_author; 
    }

    /**
     * Sets the author information.
     *
     * @param string $author The author information.
     */
    public function setAuthor($author) 
    {
        $this->_author = $author; 
    }

    /**
     * Returns the copyright information.
     *
     * @return string
     */
    public function getCopyright() 
    {
        return $this->_copyright; 
    }

    /**
     * Sets the copyright information.
     *
     * @param string $copyright The copyright information.
     */
    public function setCopyright($copyright) 
    {
        $this->_copyright = $copyright; 
    }

    /**
     * Returns the description information.
     *
     * @return string
     */
    public function getDescription() 
    {
        return $this->_description; 
    }

    /**
     * Sets the description information.
     *
     * @param string $description The description information.
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Returns the rating information.
     *
     * @return string
     */
    public function getRating() 
    {
        return $this->_rating; 
    }

    /**
     * Sets the rating information.
     *
     * @param string $rating The rating information.
     */
    public function setRating($rating) 
    {
        $this->_rating = $rating; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        $title = iconv
            ($this->getOption('encoding'), 'utf-16le',
             $this->_title ? $this->_title . "\0" : '');
        $author =  iconv
            ($this->getOption('encoding'), 'utf-16le',
             $this->_author ? $this->_author . "\0" : '');
        $copyright =  iconv
            ($this->getOption('encoding'), 'utf-16le',
             $this->_copyright ? $this->_copyright . "\0" : '');
        $description =  iconv
            ($this->getOption('encoding'), 'utf-16le',
             $this->_description ? $this->_description . "\0" : '');
        $rating =  iconv
            ($this->getOption('encoding'), 'utf-16le',
             $this->_rating ? $this->_rating . "\0" : '');

        require_once 'Zend/Io/StringWriter.php';
        $buffer = new Zend_Io_StringWriter();
        $buffer->writeUInt16LE(strlen($title))
               ->writeUInt16LE(strlen($author))
               ->writeUInt16LE(strlen($copyright))
               ->writeUInt16LE(strlen($description))
               ->writeUInt16LE(strlen($rating))
               ->writeString16($title)
               ->writeString16($author)
               ->writeString16($copyright)
               ->writeString16($description)
               ->writeString16($rating);

        $this->setSize(24 /* for header */ + $buffer->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->write($buffer->toString());
    }
}
