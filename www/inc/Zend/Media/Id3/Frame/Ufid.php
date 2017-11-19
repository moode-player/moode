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
 * @version    $Id: Ufid.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Unique File Identifier frame</i>'s purpose is to be able to identify
 * the audio file in a database, that may provide more information relevant to
 * the content. Since standardisation of such a database is beyond this document,
 * all UFID frames begin with an 'owner identifier' field. It is a null-
 * terminated string with a URL containing an email address, or a link to
 * a location where an email address can be found, that belongs to the
 * organisation responsible for this specific database implementation.
 * Questions regarding the database should be sent to the indicated email
 * address. The URL should not be used for the actual database queries. The
 * string "http://www.id3.org/dummy/ufid.html" should be used for tests. The
 * 'Owner identifier' must be non-empty (more than just a termination). The
 * 'Owner identifier' is then followed by the actual identifier, which may be
 * up to 64 bytes. There may be more than one "UFID" frame in a tag, but only
 * one with the same 'Owner identifier'.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Arlo Kleijweg <arlo.kleijweg@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ufid.php 273 2012-08-21 17:22:52Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Ufid extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var string */
    private $_fileIdentifier;

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

        list($this->_owner, $this->_fileIdentifier) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
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
        $this->_owner = $owner;
    }

    /**
     * Returns the identifier binary data associated with the frame.
     *
     * @return string
     */
    public function getFileIdentifier()
    {
        return $this->_fileIdentifier;
    }

    /**
     * Sets the identifier binary data associated with the frame.
     *
     * @param string $fileIdentifier The file identifier binary data string.
     */
    public function setFileIdentifier($fileIdentifier)
    {
        $this->_fileIdentifier = $fileIdentifier;
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
               ->write($this->_fileIdentifier);
    }
}
