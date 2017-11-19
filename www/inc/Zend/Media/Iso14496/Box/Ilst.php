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
 * @version    $Id: Ilst.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * A container box for all the iTunes/iPod specific boxes. A list of well known
 * boxes is provided in the following table. The value for each box is contained
 * in a nested {@link Zend_Media_Iso14496_Box_Data Data Box}.
 *
 * <ul>
 * <li><b>_nam</b> -- <i>Name of the track</i></li>
 * <li><b>_ART</b> -- <i>Name of the artist</i></li>
 * <li><b>aART</b> -- <i>Name of the album artist</i></li>
 * <li><b>_alb</b> -- <i>Name of the album</i></li>
 * <li><b>_grp</b> -- <i>Grouping</i></li>
 * <li><b>_day</b> -- <i>Year of publication</i></li>
 * <li><b>trkn</b> -- <i>Track number (number/total)</i></li>
 * <li><b>disk</b> -- <i>Disk number (number/total)</i></li>
 * <li><b>tmpo</b> -- <i>BPM tempo</i></li>
 * <li><b>_wrt</b> -- <i>Name of the composer</i></li>
 * <li><b>_cmt</b> -- <i>Comments</i></li>
 * <li><b>_gen</b> -- <i>Genre as string</i></li>
 * <li><b>gnre</b> -- <i>Genre as an ID3v1 code, added by one</i></li>
 * <li><b>cpil</b> -- <i>Part of a compilation (0/1)</i></li>
 * <li><b>tvsh</b> -- <i>Name of the (television) show</i></li>
 * <li><b>sonm</b> -- <i>Sort name of the track</i></li>
 * <li><b>soar</b> -- <i>Sort name of the artist</i></li>
 * <li><b>soaa</b> -- <i>Sort name of the album artist</i></li>
 * <li><b>soal</b> -- <i>Sort name of the album</i></li>
 * <li><b>soco</b> -- <i>Sort name of the composer</i></li>
 * <li><b>sosn</b> -- <i>Sort name of the show</i></li>
 * <li><b>_lyr</b> -- <i>Lyrics</i></li>
 * <li><b>covr</b> -- <i>Cover (or other) artwork binary data</i></li>
 * <li><b>_too</b> -- <i>Information about the software</i></li>
 * </ul>
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ilst.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      iTunes/iPod specific
 */
final class Zend_Media_Iso14496_Box_Ilst extends Zend_Media_Iso14496_Box
{
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
        $this->setContainer(true);

        if ($reader === null) {
            return;
        }

        $this->constructBoxes('Zend_Media_Iso14496_Box_Ilst_Container');
    }

    /**
     * Override magic function so that $obj->value on a box will return the data
     * box instead of the data container box.
     *
     * @param string $name The box or field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (strlen($name) == 3) {
            $name = "\xa9" . $name;
        }
        if ($name[0] == '_') {
            $name = "\xa9" . substr($name, 1, 3);
        }
        if ($this->hasBox($name)) {
            $boxes = $this->getBoxesByIdentifier($name);
            return $boxes[0]->data;
        }
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        return $this->addBox
            (new Zend_Media_Iso14496_Box_Ilst_Container($name))->data;
    }
}

/**
 * Generic iTunes/iPod DATA Box container.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ilst.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      iTunes/iPod specific
 * @ignore
 */
final class Zend_Media_Iso14496_Box_Ilst_Container
    extends Zend_Media_Iso14496_Box
{
    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct(is_string($reader) ? null : $reader, $options);
        $this->setContainer(true);

        if (is_string($reader)) {
            $this->setType($reader);
            $this->addBox(new Zend_Media_Iso14496_Box_Data());
        } else {
            $this->constructBoxes();
        }
    }
}

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * A box that contains data for iTunes/iPod specific boxes.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ilst.php 177 2010-03-09 13:13:34Z svollbehr $
 * @since      iTunes/iPod specific
 */
final class Zend_Media_Iso14496_Box_Data extends Zend_Media_Iso14496_FullBox
{
    /** @var mixed */
    private $_value;

    /** A flag to indicate that the data is an unsigned 8-bit integer. */
    const INTEGER = 0x0;

    /**
     * A flag to indicate that the data is an unsigned 8-bit integer. Different
     * value used in old versions of iTunes.
     */
    const INTEGER_OLD_STYLE = 0x15;

    /** A flag to indicate that the data is a string. */
    const STRING = 0x1;

    /** A flag to indicate that the data is the contents of an JPEG image. */
    const JPEG = 0xd;

    /** A flag to indicate that the data is the contents of a PNG image. */
    const PNG = 0xe;

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
        $data = $this->_reader->read
            ($this->getOffset() + $this->getSize() -
             $this->_reader->getOffset());
        switch ($this->getFlags()) {
            case self::INTEGER:
                // break intentionally omitted
            case self::INTEGER_OLD_STYLE:
                for ($i = 0;  $i < strlen($data); $i++) {
                    $this->_value .= ord($data[$i]);
                }
                break;
            case self::STRING:
                // break intentionally omitted
            default:
                $this->_value = $data;
                break;
        }
    }

    /**
     * Returns the value this box contains.
     *
     * @return mixed
     */
    public function getValue() 
    {
        return $this->_value; 
    }

    /**
     * Sets the value this box contains.
     *
     * @return mixed
     */
    public function setValue($value, $type = null)
    {
        $this->_value = (string)$value;
        if ($type === null && is_string($value)) {
            $this->_flags = self::STRING;
        }
        if ($type === null && is_int($value)) {
            $this->_flags = self::INTEGER;
        }
        if ($type !== null) {
            $this->_flags = $type;
        }
    }

    /**
     * Override magic function so that $obj->data will return the current box
     * instead of an error. For other values the method will attempt to call a
     * getter method.
     *
     * If there are no getter methods with given name, the method will yield an
     * exception.
     *
     * @param string $name The box or field name.
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'data') {
            return $this;
        }
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        }
        require_once 'Zend/Media/Iso14496/Exception.php';
        throw new Zend_Media_Iso14496_Exception('Unknown box/field: ' . $name);
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 + strlen($this->_value);
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
        $writer->write("\0\0\0\0");
        switch ($this->getFlags()) {
            case self::INTEGER:
                // break intentionally omitted
            case self::INTEGER_OLD_STYLE:
                for ($i = 0;  $i < strlen($this->_value); $i++) {
                    $writer->writeInt8($this->_value[$i]);
                }
                break;
            case self::STRING:
                // break intentionally omitted
            default:
                $writer->write($this->_value);
                break;
        }
    }
}
