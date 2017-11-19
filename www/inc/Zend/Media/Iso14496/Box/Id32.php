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
 * @version    $Id: Id32.php 259 2012-03-05 18:58:07Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
require_once 'Zend/Media/Id3v2.php';
/**#@-*/

/**
 * The <i>ID3v2 Box</i> resides under the
 * {@link Zend_Media_Iso14496_Box_Meta Meta Box} and stores ID3 version 2
 * meta-data. There may be more than one Id3v2 Box present each with a different
 * language code.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Id32.php 259 2012-03-05 18:58:07Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Id32 extends Zend_Media_Iso14496_FullBox
{
    /** @var string */
    private $_language = 'und';

    /** @var Zend_Media_Id3v2 */
    private $_tag;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
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

        $this->_language =
            chr(((($tmp = $this->_reader->readUInt16BE()) >> 10) & 0x1f) +
                0x60) .
            chr((($tmp >> 5) & 0x1f) + 0x60) . chr(($tmp & 0x1f) + 0x60);
        $this->_tag = new Zend_Media_Id3v2
            ($this->_reader, array('readonly' => true));
    }

    /**
     * Returns the three byte language code to describe the language of this
     * media, according to {@link http://www.loc.gov/standards/iso639-2/
     * ISO 639-2/T}.
     *
     * @return string
     */
    public function getLanguage() 
    {
        return $this->_language; 
    }

    /**
     * Sets the three byte language code as specified in the
     * {@link http://www.loc.gov/standards/iso639-2/ ISO 639-2} standard.
     *
     * @param string $language The language code.
     */
    public function setLanguage($language) 
    {
        $this->_language = $language; 
    }

    /**
     * Returns the {@link Zend_Media_Id3v2 Id3v2} tag class instance.
     *
     * @return string
     */
    public function getTag() 
    {
        return $this->_tag; 
    }

    /**
     * Sets the {@link Zend_Media_Id3v2 Id3v2} tag class instance using given
     * language.
     *
     * @param Zend_Media_Id3v2 $tag The tag instance.
     * @param string $language The language code.
     */
    public function setTag($tag, $language = null)
    {
        $this->_tag = $tag;
        if ($language !== null) {
            $this->_language = $language;
        }
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     * @todo There has got to be a better way to do this
     */
    public function getHeapSize()
    {
        $writer = new Zend_Io_StringWriter();
        $this->_tag->write($writer);
        return parent::getHeapSize() + 2 + $writer->getSize();
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
        $writer->writeUInt16BE
             (((ord($this->_language[0]) - 0x60) << 10) |
              ((ord($this->_language[1]) - 0x60) << 5) |
              ord($this->_language[2]) - 0x60);
        $this->_tag->write($writer);
    }
}
