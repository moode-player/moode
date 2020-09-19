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
 * @version    $Id: ContentBranding.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Content Branding Object</i> stores branding data for an ASF file,
 * including information about a banner image and copyright associated with the
 * file.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ContentBranding.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ContentBranding extends Zend_Media_Asf_Object
{
    /** Indicates that there is no banner */
    const TYPE_NONE = 0;

    /** Indicates that the data represents a bitmap */
    const TYPE_BMP = 1;

    /** Indicates that the data represents a JPEG */
    const TYPE_JPEG = 2;

    /** Indicates that the data represents a GIF */
    const TYPE_GIF = 3;

    /** @var integer */
    private $_bannerImageType;

    /** @var string */
    private $_bannerImageData;

    /** @var string */
    private $_bannerImageUrl;

    /** @var string */
    private $_copyrightUrl;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_bannerImageType = $this->_reader->readUInt32LE();
        $bannerImageDataSize = $this->_reader->readUInt32LE();
        $this->_bannerImageData = $this->_reader->read($bannerImageDataSize);
        $bannerImageUrlLength = $this->_reader->readUInt32LE();
        $this->_bannerImageUrl = $this->_reader->read($bannerImageUrlLength);
        $copyrightUrlLength = $this->_reader->readUInt32LE();
        $this->_copyrightUrl = $this->_reader->read($copyrightUrlLength);
    }

    /**
     * Returns the type of data contained in the <i>Banner Image Data</i>. Valid
     * values are 0 to indicate that there is no banner image data; 1 to
     * indicate that the data represent a bitmap; 2 to indicate that the data
     * represents a JPEG; and 3 to indicate that the data represents a GIF. If
     * this value is set to 0, then the <i>Banner Image Data Size field is set
     * to 0, and the <i>Banner Image Data</i> field is empty.
     *
     * @return integer
     */
    public function getBannerImageType() 
    {
        return $this->_bannerImageType; 
    }

    /**
     * Sets the type of data contained in the <i>Banner Image Data</i>. Valid
     * values are 0 to indicate that there is no banner image data; 1 to
     * indicate that the data represent a bitmap; 2 to indicate that the data
     * represents a JPEG; and 3 to indicate that the data represents a GIF. If
     * this value is set to 0, then the <i>Banner Image Data Size field is set
     * to 0, and the <i>Banner Image Data</i> field is empty.
     *
     * @param integer $bannerImageType The type of data.
     */
    public function setBannerImageType($bannerImageType)
    {
        $this->_bannerImageType = $bannerImageType;
    }

    /**
     * Returns the entire banner image, including the header for the appropriate
     * image format.
     *
     * @return string
     */
    public function getBannerImageData() 
    {
        return $this->_bannerImageData; 
    }

    /**
     * Sets the entire banner image, including the header for the appropriate
     * image format.
     *
     * @param string $bannerImageData The entire banner image.
     */
    public function setBannerImageData($bannerImageData)
    {
        $this->_bannerImageData = $bannerImageData;
    }

    /**
     * Returns, if present, a link to more information about the banner image.
     *
     * @return string
     */
    public function getBannerImageUrl() 
    {
        return $this->_bannerImageUrl; 
    }

    /**
     * Sets a link to more information about the banner image.
     *
     * @param string $bannerImageUrl The link.
     */
    public function setBannerImageUrl($bannerImageUrl)
    {
        $this->_bannerImageUrl = $bannerImageUrl;
    }

    /**
     * Returns, if present, a link to more information about the copyright for
     * the content.
     *
     * @return string
     */
    public function getCopyrightUrl() 
    {
        return $this->_copyrightUrl; 
    }

    /**
     * Sets a link to more information about the copyright for the content.
     *
     * @param string $copyrightUrl The copyright link.
     */
    public function setCopyrightUrl($copyrightUrl)
    {
        $this->_copyrightUrl = $copyrightUrl;
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
        $buffer->writeUInt32LE($this->_bannerImageType)
               ->writeUInt32LE(count($this->_bannerImageData))
               ->write($this->_bannerImageData)
               ->writeUInt32LE(count($this->_bannerImageUrl))
               ->write($this->_bannerImageUrl)
               ->writeUInt32LE(count($this->_copyrightUrl))
               ->write($this->_copyrightUrl);

        $this->setSize(24 /* for header */ + $buffer->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->write($buffer->toString());
    }
}
