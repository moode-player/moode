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
 * @version    $Id: Aenc.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Audio encryption</i> indicates if the actual audio stream is
 * encrypted, and by whom.
 *
 * The identifier is a URL containing an email address, or a link to a location
 * where an email address can be found, that belongs to the organisation
 * responsible for this specific encrypted audio file. Questions regarding the
 * encrypted audio should be sent to the email address specified. There may be
 * more than one AENC frame in a tag, but only one with the same owner
 * identifier.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Aenc.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Aenc extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_owner;

    /** @var integer */
    private $_previewStart;

    /** @var integer */
    private $_previewLength;

    /** @var string */
    private $_encryptionInfo;

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

        list($this->_owner) = $this->_explodeString8
            ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset(strlen($this->_owner) + 1);
        $this->_previewStart = $this->_reader->readUInt16BE();
        $this->_previewLength = $this->_reader->readUInt16BE();
        $this->_encryptionInfo =
            $this->_reader->read($this->_reader->getSize());
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
     * Returns the pointer to an unencrypted part of the audio in frames.
     *
     * @return integer
     */
    public function getPreviewStart() 
    {
         return $this->_previewStart; 
    }

    /**
     * Sets the pointer to an unencrypted part of the audio in frames.
     *
     * @param integer $previewStart The pointer to an unencrypted part.
     */
    public function setPreviewStart($previewStart)
    {
        $this->_previewStart = $previewStart;
    }

    /**
     * Returns the length of the preview in frames.
     *
     * @return integer
     */
    public function getPreviewLength() 
    {
         return $this->_previewLength; 
    }

    /**
     * Sets the length of the preview in frames.
     *
     * @param integer $previewLength The length of the preview.
     */
    public function setPreviewLength($previewLength)
    {
        $this->_previewLength = $previewLength;
    }

    /**
     * Returns the encryption info.
     *
     * @return string
     */
    public function getEncryptionInfo() 
    {
         return $this->_encryptionInfo; 
    }

    /**
     * Sets the encryption info binary string.
     *
     * @param string $encryptionInfo The data string.
     */
    public function setEncryptionInfo($encryptionInfo)
    {
        $this->_encryptionInfo = $encryptionInfo;
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
               ->writeUInt16BE($this->_previewStart)
               ->writeUInt16BE($this->_previewLength)
               ->write($this->_encryptionInfo);
    }
}
