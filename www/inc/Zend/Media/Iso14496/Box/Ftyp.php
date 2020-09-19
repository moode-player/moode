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
 * @version    $Id: Ftyp.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * The <i>File Type Box</i> is placed as early as possible in the file (e.g.
 * after any obligatory signature, but before any significant variable-size
 * boxes such as a {@link Zend_Media_Iso14496_Box_Moov Movie Box},
 * {@link Zend_Media_Iso14496_Box_Mdat Media Data Box}, or
 * {@link Zend_Media_Iso14496_Box_Free Free Space}). It identifies which
 * specification is the <i>best use</i> of the file, and a minor version of
 * that specification; and also a set of others specifications to which the
 * file complies.
 *
 * The minor version is informative only. It does not appear for
 * compatible-brands, and must not be used to determine the conformance of a
 * file to a standard. It may allow more precise identification of the major
 * specification, for inspection, debugging, or improved decoding.
 *
 * The type <i>isom</i> (ISO Base Media file) is defined as identifying files
 * that conform to the first version of the ISO Base Media File Format. More
 * specific identifiers can be used to identify precise versions of
 * specifications providing more detail. This brand is not be used as the major
 * brand; this base file format should be derived into another specification to
 * be used. There is therefore no defined normal file extension, or mime type
 * assigned to this brand, nor definition of the minor version when <i>isom</i>
 * is the major brand.
 *
 * Files would normally be externally identified (e.g. with a file extension or
 * mime type) that identifies the <i>best use</i> (major brand), or the brand
 * that the author believes will provide the greatest compatibility.
 *
 * The brand <i>iso2</i> shall be used to indicate compatibility with the
 * amended version of the ISO Base Media File Format; it may be used in addition
 * to or instead of the <i>isom</i> brand and the same usage rules apply. If
 * used without the brand <i>isom</i> identifying the first version of the
 * specification, it indicates that support for some or all of the technology
 * introduced by the amended version of the ISO Base Media File Format is
 * required.
 *
 * The brand <i>avc1</i> shall be used to indicate that the file is conformant
 * with the <i>AVC Extensions</i>. If used without other brands, this implies
 * that support for those extensions is required. The use of <i>avc1</i> as a
 * major-brand may be permitted by specifications; in that case, that
 * specification defines the file extension and required behavior.
 *
 * If a Meta-box with an MPEG-7 handler type is used at the file level, then the
 * brand <i>mp71</i> is a member of the compatible-brands list in the file-type
 * box.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ftyp.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Ftyp extends Zend_Media_Iso14496_Box
{
    /** @var integer */
    private $_majorBrand;

    /** @var integer */
    private $_minorVersion;

    /** @var integer */
    private $_compatibleBrands = array();

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

        $this->_majorBrand   = $this->_reader->readString8(4);
        $this->_minorVersion = $this->_reader->readUInt32BE();
        while ($this->_reader->getOffset() < $this->getSize()) {
            if (($brand = $this->_reader->readString8(4)) != '') {
                $this->_compatibleBrands[] = $brand;
            }
        }
    }

    /**
     * Returns the major version brand.
     *
     * @return string
     */
    public function getMajorBrand()
    {
        return $this->_majorBrand;
    }

    /**
     * Sets the major version brand.
     *
     * @param string $majorBrand The major version brand.
     */
    public function setMajorBrand($majorBrand)
    {
        $this->_majorBrand = $majorBrand;
    }

    /**
     * Returns the minor version number.
     *
     * @return integer
     */
    public function getMinorVersion()
    {
        return $this->_minorVersion;
    }

    /**
     * Sets the minor version number.
     *
     * @param integer $minorVersion The minor version number.
     */
    public function setMinorVersion($minorVersion)
    {
        $this->_minorVersion = $minorVersion;
    }

    /**
     * Returns the array of compatible version brands.
     *
     * @return Array
     */
    public function getCompatibleBrands()
    {
        return $this->_compatibleBrands;
    }

    /**
     * Sets the array of compatible version brands.
     *
     * @param Array $compatibleBrands The array of compatible version brands.
     */
    public function setCompatibleBrands($compatibleBrands)
    {
        $this->_compatibleBrands = $compatibleBrands;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 8 + 4 * count($this->_compatibleBrands);
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

        $writer->writeString8(substr($this->_majorBrand, 0, 4))
               ->writeUInt32BE($this->_minorVersion);
        for ($i = 0; $i < count($this->_compatibleBrands); $i++) {
            $writer->writeString8(substr($this->_compatibleBrands[$i], 0, 4));
        }
    }
}
