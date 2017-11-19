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
 * @version    $Id: Schm.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Scheme Type Box</i> identifies the protection scheme.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Schm.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Schm extends Zend_Media_Iso14496_FullBox
{
    /** @var string */
    private $_schemeType;

    /** @var integer */
    private $_schemeVersion;

    /** @var string */
    private $_schemeUri;

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

        $this->_schemeType = $this->_reader->read(4);
        $this->_schemeVersion = $this->_reader->readUInt32BE();
        if ($this->hasFlag(1)) {
            $this->_schemeUri = preg_split
                ("/\\x00/", $this->_reader->read
                 ($this->getOffset() + $this->getSize() -
                  $this->_reader->getOffset()));
        }
    }

    /**
     * Returns the code defining the protection scheme.
     *
     * @return string
     */
    public function getSchemeType()
    {
        return $this->_schemeType;
    }

    /**
     * Sets the code defining the protection scheme.
     *
     * @param string $schemeType The scheme type.
     */
    public function setSchemeType($schemeType)
    {
        $this->_schemeType = $schemeType;
    }

    /**
     * Returns the version of the scheme used to create the content.
     *
     * @return integer
     */
    public function getSchemeVersion()
    {
        return $this->_schemeVersion;
    }

    /**
     * Sets the version of the scheme used to create the content.
     *
     * @param integer $schemeVersion The scheme version.
     */
    public function setSchemeVersion($schemeVersion)
    {
        $this->_schemeVersion = $schemeVersion;
    }

    /**
     * Returns the optional scheme address to allow for the option of directing
     * the user to a web-page if they do not have the scheme installed on their
     * system. It is an absolute URI.
     *
     * @return string
     */
    public function getSchemeUri()
    {
        return $this->_schemeUri;
    }

    /**
     * Sets the optional scheme address to allow for the option of directing
     * the user to a web-page if they do not have the scheme installed on their
     * system. It is an absolute URI.
     *
     * @param string $schemeUri The scheme URI.
     */
    public function setSchemeUri($schemeUri)
    {
        $this->_schemeUri = $schemeUri;
        if ($schemeUri === null) {
            $this->setFlags(0);
        } else {
            $this->setFlags(1);
        }
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 8 +
            ($this->hasFlag(1) ? strlen($this->_schemeUri) + 1 : 0);
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
        $writer->write($this->_schemeType);
        $writer->writeUInt32BE($this->_schemeVersion);
        if ($this->hasFlag(1)) {
            $writer->writeString8($this->_schemeUri, 1);
        }
    }
}
