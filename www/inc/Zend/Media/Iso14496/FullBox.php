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
 * @version    $Id: FullBox.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * A base class for objects that also contain a version number and flags field.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: FullBox.php 177 2010-03-09 13:13:34Z svollbehr $
 */
abstract class Zend_Media_Iso14496_FullBox extends Zend_Media_Iso14496_Box
{
    /** @var integer */
    protected $_version = 0;

    /** @var integer */
    protected $_flags = 0;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $this->_version =
            (($field = $this->_reader->readUInt32BE()) >> 24) & 0xff;
        $this->_flags = $field & 0xffffff;
    }

    /**
     * Returns the version of this format of the box.
     *
     * @return integer
     */
    public function getVersion() 
    {
        return $this->_version; 
    }

    /**
     * Sets the version of this format of the box.
     *
     * @param integer $version The version.
     */
    public function setVersion($version) 
    {
        $this->_version = $version; 
    }

    /**
     * Checks whether or not the flag is set. Returns <var>true</var> if the
     * flag is set, <var>false</var> otherwise.
     *
     * @param integer $flag The flag to query.
     * @return boolean
     */
    public function hasFlag($flag) 
    {
        return ($this->_flags & $flag) == $flag; 
    }

    /**
     * Returns the map of flags.
     *
     * @return integer
     */
    public function getFlags() 
    {
        return $this->_flags; 
    }

    /**
     * Sets the map of flags.
     *
     * @param string $flags The map of flags.
     */
    public function setFlags($flags) 
    {
        $this->_flags = $flags; 
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4;
    }

    /**
     * Writes the box data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        parent::_writeData($writer);
        $writer->writeUInt32BE($this->_version << 24 | $this->_flags);
    }
}
