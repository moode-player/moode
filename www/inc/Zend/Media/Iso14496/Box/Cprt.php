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
 * @version    $Id: Cprt.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Copyright Box</i> contains a copyright declaration which applies to
 * the entire presentation, when contained within the
 * {@link Zend_Media_Iso14496_Box_Moov Movie Box}, or, when contained in a
 * track, to that entire track. There may be multiple copyright boxes using
 * different language codes.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Cprt.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Cprt extends Zend_Media_Iso14496_FullBox
{
    /** @var string */
    private $_language;

    /** @var string */
    private $_notice;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     * @todo Distinguish UTF-16?
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_language = chr
            (((($tmp = $this->_reader->readUInt16BE()) >> 10) & 0x1f) + 0x60) .
            chr((($tmp >> 5) & 0x1f) + 0x60) . chr(($tmp & 0x1f) + 0x60);
        $this->_notice = $this->_reader->readString8
            ($this->getOffset() + $this->getSize() -
             $this->_reader->getOffset());
    }

    /**
     * Returns the three byte language code to describe the language of the
     * notice, according to {@link http://www.loc.gov/standards/iso639-2/
     * ISO 639-2/T}.
     *
     * @return string
     */
    public function getLanguage() 
    {
        return $this->_language; 
    }

    /**
     * Sets the three byte language code to describe the language of this
     * media, according to {@link http://www.loc.gov/standards/iso639-2/
     * ISO 639-2/T}.
     *
     * @param string $language The language code.
     */
    public function setLanguage($language)
    {
        $this->_language = $language;
    }

    /**
     * Returns the copyright notice.
     *
     * @return string
     */
    public function getNotice()
    {
        return $this->_notice;
    }

    /**
     * Returns the copyright notice.
     *
     * @param string $notice The copyright notice.
     */
    public function setNotice($notice)
    {
        $this->_notice = $notice;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 3 + strlen($this->_notice);
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
        $writer->writeUInt16BE((ord($this->_language[0]) - 0x60) << 10 |
                (ord($this->_language[1])- 0x60) << 5 |
                 (ord($this->_language[2])- 0x60))
               ->writeString8($this->_notice, 1);
    }
}
