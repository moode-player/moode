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
 * @version    $Id: ContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Content Encryption Object</i> lets authors protect content by using
 * Microsoft® Digital Rights Manager version 1.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ContentEncryption.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ContentEncryption
    extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_secretData;

    /** @var string */
    private $_protectionType;

    /** @var string */
    private $_keyId;

    /** @var string */
    private $_licenseUrl;

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

        $secretDataLength = $this->_reader->readUInt32LE();
        $this->_secretData = $this->_reader->read($secretDataLength);
        $protectionTypeLength = $this->_reader->readUInt32LE();
        $this->_protectionType =
            $this->_reader->readString8($protectionTypeLength);
        $keyIdLength = $this->_reader->readUInt32LE();
        $this->_keyId = $this->_reader->readString8($keyIdLength);
        $licenseUrlLength = $this->_reader->readUInt32LE();
        $this->_licenseUrl = $this->_reader->readString8($licenseUrlLength);
    }

    /**
     * Returns the secret data.
     *
     * @return string
     */
    public function getSecretData() 
    {
        return $this->_secretData; 
    }

    /**
     * Sets the secret data.
     *
     * @param string $secretData The secret data.
     */
    public function setSecretData($secretData)
    {
        $this->_secretData = $secretData;
    }

    /**
     * Returns the type of protection mechanism used. The value of this field
     * is set to 'DRM'.
     *
     * @return string
     */
    public function getProtectionType() 
    {
        return $this->_protectionType; 
    }

    /**
     * Sets the type of protection mechanism used. The value of this field
     * is to be set to 'DRM'.
     *
     * @param string $protectionType The protection mechanism used.
     */
    public function setProtectionType($protectionType)
    {
        $this->_protectionType = $protectionType;
    }

    /**
     * Returns the key ID used.
     *
     * @return string
     */
    public function getKeyId() 
    {
        return $this->_keyId; 
    }

    /**
     * Sets the key ID used.
     *
     * @param string $keyId The key ID used.
     */
    public function setKeyId($keyId) 
    {
        $this->_keyId = $keyId; 
    }

    /**
     * Returns the URL from which a license to manipulate the content can be
     * acquired.
     *
     * @return string
     */
    public function getLicenseUrl() 
    {
        return $this->_licenseUrl; 
    }

    /**
     * Returns the URL from which a license to manipulate the content can be
     * acquired.
     *
     * @param string $licenseUrl The URL from which a license can be acquired.
     */
    public function setLicenseUrl($licenseUrl)
    {
        $this->_licenseUrl = $licenseUrl;
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        require_once 'Zend/Io/StringWriter.php';
        $buffer = new Zend_Io_StringWriter();
        $buffer->writeUInt32LE(strlen($this->_secretData))
               ->write($this->_secretData)
               ->writeUInt32LE($len = strlen($this->_protectionType) + 1)
               ->writeString8($this->_protectionType, $len)
               ->writeUInt32LE($len = strlen($this->_keyId) + 1)
               ->writeString8($this->_keyId, $len)
               ->writeUInt32LE($len = strlen($this->_licenseUrl) + 1)
               ->writeString8($this->_licenseUrl, $len);

        $this->setSize(24 /* for header */ + $buffer->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->write($buffer->toString());
    }
}
