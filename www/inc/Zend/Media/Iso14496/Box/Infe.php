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
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS'
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Infe.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Item Information Entry Box</i> contains the entry information.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Infe.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Infe extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_itemId;

    /** @var integer */
    private $_itemProtectionIndex;

    /** @var string */
    private $_itemName;

    /** @var string */
    private $_contentType;

    /** @var string */
    private $_contentEncoding;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_itemId = $this->_reader->readUInt16BE();
        $this->_itemProtectionIndex = $this->_reader->readUInt16BE();
        list($this->_itemName, $this->_contentType, $this->_contentEncoding) =
            preg_split
                ("/\\x00/", $this->_reader->read
                 ($this->getOffset() + $this->getSize() -
                  $this->_reader->getOffset()));
    }

    /**
     * Returns the item identifier. The value is either 0 for the primary
     * resource (e.g. the XML contained in an
     * {@link Zend_Media_Iso14496_Box_Xml XML Box}) or the ID of the item for
     * which the following information is defined.
     *
     * @return integer
     */
    public function getItemId()
    {
        return $this->_itemId;
    }

    /**
     * Sets the item identifier. The value must be either 0 for the primary
     * resource (e.g. the XML contained in an
     * {@link Zend_Media_Iso14496_Box_Xml XML Box}) or the ID of the item for
     * which the following information is defined.
     *
     * @param integer $itemId The item identifier.
     */
    public function setItemId($itemId)
    {
        $this->_itemId = $itemId;
    }

    /**
     * Returns the item protection index. The value is either 0 for an
     * unprotected item, or the one-based index into the
     * {@link Zend_Media_Iso14496_Box_Ipro Item Protection Box} defining the
     * protection applied to this item (the first box in the item protection box
     * has the index 1).
     *
     * @return integer
     */
    public function getItemProtectionIndex()
    {
        return $this->_itemProtectionIndex;
    }

    /**
     * Sets the item protection index. The value must be either 0 for an
     * unprotected item, or the one-based index into the
     * {@link Zend_Media_Iso14496_Box_Ipro Item Protection Box} defining the
     * protection applied to this item (the first box in the item protection box
     * has the index 1).
     *
     * @param integer $itemProtectionIndex The index.
     */
    public function setItemProtectionIndex($itemProtectionIndex)
    {
        $this->_itemProtectionIndex = $itemProtectionIndex;
    }

    /**
     * Returns the symbolic name of the item.
     *
     * @return string
     */
    public function getItemName()
    {
        return $this->_itemName;
    }

    /**
     * Sets the symbolic name of the item.
     *
     * @param string $itemName The item name.
     */
    public function setItemName($itemName)
    {
        $this->_itemName = $itemName;
    }

    /**
     * Returns the MIME type for the item.
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->_contentType;
    }

    /**
     * Sets the MIME type for the item.
     *
     * @param string $contentType The content type.
     */
    public function setContentType($contentType)
    {
        $this->_contentType = $contentType;
    }

    /**
     * Returns the optional content encoding type as defined for
     * Content-Encoding for HTTP /1.1. Some possible values are <i>gzip</i>,
     * <i>compress</i> and <i>deflate</i>. An empty string indicates no content
     * encoding.
     *
     * @return string
     */
    public function getContentEncoding()
    {
        return $this->_contentEncoding;
    }

    /**
     * Sets the optional content encoding type as defined for
     * Content-Encoding for HTTP /1.1. Some possible values are <i>gzip</i>,
     * <i>compress</i> and <i>deflate</i>. An empty string indicates no content
     * encoding.
     *
     * @param string $contentEncoding The content encoding.
     */
    public function setContentEncoding($contentEncoding)
    {
        $this->_contentEncoding = $contentEncoding;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 7 + strlen($this->_itemName) +
            strlen($this->_contentType) + strlen($this->_contentEncoding);
    }

    /**
     * Writes the box data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        parent::_writeData($writer);
        $writer->writeUInt16BE($this->_itemId)
               ->writeUInt16BE($this->_itemProtectionIndex)
               ->writeString8($this->_itemName, 1)
               ->writeString8($this->_contentType, 1)
               ->writeString8($this->_contentEncoding, 1);
    }
}
