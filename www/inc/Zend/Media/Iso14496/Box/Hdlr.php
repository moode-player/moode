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
 * @version    $Id: Hdlr.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Handler Reference Box</i> is within a
 * {@link Zend_Media_Iso14496_Box_Mdia Media Box} declares the process by which
 * the media-data in the track is presented, and thus, the nature of the media
 * in a track. For example, a video track would be handled by a video handler.
 *
 * This box when present within a {@link Zend_Media_Iso14496_Box_Meta Meta Box},
 * declares the structure or format of the <i>meta</i> box contents.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Hdlr.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Hdlr extends Zend_Media_Iso14496_FullBox
{
    /** @var string */
    private $_handlerType;

    /** @var string */
    private $_name;

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

        $this->_reader->skip(4);
        $this->_handlerType = $this->_reader->read(4);
        $this->_reader->skip(12);
        $this->_name = $this->_reader->readString8
            ($this->getOffset() + $this->getSize() -
             $this->_reader->getOffset());
    }

    /**
     * Returns the handler type.
     *
     * When present in a media box, the returned value contains one of the
     * following values, or a value from a derived specification:
     *   o <i>vide</i> Video track
     *   o <i>soun</i> Audio track
     *   o <i>hint</i> Hint track
     *
     * When present in a meta box, the returned value contains an appropriate
     * value to indicate the format of the meta box contents.
     *
     * @return integer
     */
    public function getHandlerType() 
    {
        return $this->_handlerType; 
    }

    /**
     * Sets the handler type.
     *
     * When present in a media box, the value must be set to one of the
     * following values, or a value from a derived specification:
     *   o <i>vide</i> Video track
     *   o <i>soun</i> Audio track
     *   o <i>hint</i> Hint track
     *
     * When present in a meta box, the value must be set to an appropriate value
     * to indicate the format of the meta box contents.
     *
     * @param string $handlerType The handler type.
     */
    public function setHandlerType($handlerType)
    {
        $this->_handlerType = $handlerType;
    }

    /**
     * Returns the name string. The name is in UTF-8 characters and gives a
     * human-readable name for the track type (for debugging and inspection
     * purposes).
     *
     * @return integer
     */
    public function getName() 
    {
        return $this->_name; 
    }

    /**
     * Sets the name string. The name must be in UTF-8 and give a human-readable
     * name for the track type (for debugging and inspection purposes).
     *
     * @param string $name The human-readable description.
     */
    public function setName($name) 
    {
        $this->_name = $name; 
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 21 + strlen($this->_name);
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
        $writer->write(str_pad('', 4, "\0"))
               ->write($this->_handlerType)
               ->writeUInt32BE(0)
               ->writeUInt32BE(0)
               ->writeUInt32BE(0)
               ->writeString8($this->_name, 1);
    }
}
