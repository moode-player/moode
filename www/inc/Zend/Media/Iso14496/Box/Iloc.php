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
 * @version    $Id: Iloc.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * The <i>The Item Location Box</i> provides a directory of resources in this or
 * other files, by locating their containing file, their offset within that
 * file, and their length. Placing this in binary format enables common handling
 * of this data, even by systems which do not understand the particular metadata
 * system (handler) used. For example, a system might integrate all the
 * externally referenced metadata resources into one file, re-adjusting file
 * offsets and file references accordingly.
 *
 * Items may be stored fragmented into extents, e.g. to enable interleaving. An
 * extent is a contiguous subset of the bytes of the resource; the resource is
 * formed by concatenating the extents. If only one extent is used then either
 * or both of the offset and length may be implied:
 *
 *   o If the offset is not identified (the field has a length of zero), then
 *     the beginning of the file (offset 0) is implied.
 *   o If the length is not specified, or specified as zero, then the entire
 *     file length is implied. References into the same file as this metadata,
 *     or items divided into more than one extent, should have an explicit
 *     offset and length, or use a MIME type requiring a different
 *     interpretation of the file, to avoid infinite recursion.
 *
 * The size of the item is the sum of the extentLengths. Note: extents may be
 * interleaved with the chunks defined by the sample tables of tracks.
 *
 * The dataReferenceIndex may take the value 0, indicating a reference into the
 * same file as this metadata, or an index into the dataReference table.
 *
 * Some referenced data may itself use offset/length techniques to address
 * resources within it (e.g. an MP4 file might be included in this way).
 * Normally such offsets are relative to the beginning of the containing file.
 * The field base offset provides an additional offset for offset calculations
 * within that contained data. For example, if an MP4 file is included within a
 * file formatted to this specification, then normally data-offsets within that
 * MP4 section are relative to the beginning of file; baseOffset adds to those
 * offsets.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Iloc.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Iloc extends Zend_Media_Iso14496_Box
{
    /** @var Array */
    private $_items = array();

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

        $offsetSize = (($tmp = $this->_reader->readUInt16BE()) >> 12) & 0xf;
        $lengthSize = ($tmp >> 8) & 0xf;
        $baseOffsetSize = ($tmp >> 4) & 0xf;
        $itemCount = $this->_reader->readUInt16BE();
        for ($i = 0; $i < $itemCount; $i++) {
            $item = array();
            $item['itemId'] = $this->_reader->readUInt16BE();
            $item['dataReferenceIndex'] = $this->_reader->readUInt16BE();
            $item['baseOffset'] =
                ($baseOffsetSize == 4 ? $this->_reader->readUInt32BE() :
                 ($baseOffsetSize == 8 ? $this->_reader->readInt64BE() : 0));
            $extentCount = $this->_reader->readUInt16BE();
            $item['extents'] = array();
            for ($j = 0; $j < $extentCount; $j++) {
                $extent = array();
                $extent['offset'] =
                    ($offsetSize == 4 ? $this->_reader->readUInt32BE() :
                     ($offsetSize == 8 ? $this->_reader->readInt64BE() : 0));
                $extent['length'] =
                    ($lengthSize == 4 ? $this->_reader->readUInt32BE() :
                     ($lengthSize == 8 ? $this->_reader->readInt64BE() : 0));
                $item['extents'][] = $extent;
            }
            $this->_items[] = $item;
        }
    }

    /**
     * Returns the array of items. Each entry has the following keys set:
     * itemId, dataReferenceIndex, baseOffset, and extents.
     *
     * @return Array
     */
    public function getItems()
    {
        return $this->_items;
    }

    /**
     * Sets the array of items. Each entry must have the following keys set:
     * itemId, dataReferenceIndex, baseOffset, and extents.
     *
     * @return Array
     */
    public function setItems($items)
    {
        $this->_items = $items;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        $totalSize = 4;
        for ($i = 0; $i < count($this->_itemId); $i++) {
            $totalSize += 6;
            if ($this->_itemId[$i]['baseOffset'] > 0xffffffff) {
                    $totalSize += 8;
            } else {
                    $totalSize += 4;
            }
            $extentCount = count($this->_itemId[$i]['extents']);
            for ($j = 0; $j < $extentCount; $j++) {
                if ($this->_itemId[$i]['extents'][$j]['offset'] > 0xffffffff) {
                    $totalSize += 8 * $extentCount;
                } else {
                    $totalSize += 4 * $extentCount;
                }
                if ($this->_itemId[$i]['extents'][$j]['length'] > 0xffffffff) {
                    $totalSize += 8 * $extentCount;
                } else {
                    $totalSize += 4 * $extentCount;
                }
            }
        }
        return parent::getHeapSize() + $totalSize;
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

        $offsetSize = 4;
        $lengthSize = 4;
        $baseOffsetSize = 4;

        $itemCount = count($this->_itemId);
        for ($i = 0; $i < $itemCount; $i++) {
            if ($this->_itemId[$i]['baseOffset'] > 0xffffffff) {
                    $baseOffsetSize = 8;
            }
            for ($j = 0; $j < count($this->_itemId[$i]['extents']); $j++) {
                if ($this->_itemId[$i]['extents'][$j]['offset'] > 0xffffffff) {
                    $offsetSize = 8;
                }
                if ($this->_itemId[$i]['extents'][$j]['length'] > 0xffffffff) {
                    $lengthSize = 8;
                }
            }
        }

        $writer->writeUInt16BE
            ((($offsetSize & 0xf) << 12) | (($lengthSize & 0xf) << 8) |
             (($baseOffsetSize & 0xf) << 4))
               ->writeUInt16BE($itemCount);
        for ($i = 0; $i < $itemCount; $i++) {
            $writer->writeUInt16BE($this->_itemId[$i]['itemId'])
                   ->writeUInt16BE($this->_itemId[$i]['dataReferenceIndex']);
            if ($baseOffsetSize == 4) {
                $writer->writeUInt32BE($this->_itemId[$i]['baseOffset']);
            }
            if ($baseOffsetSize == 8) {
                $writer->writeInt64BE($this->_itemId[$i]['baseOffset']);
            }
            $writer->writeUInt16BE
                    ($extentCount = count($this->_itemId[$i]['extents']));
            for ($j = 0; $j < $extentCount; $j++) {
                if ($offsetSize == 4) {
                    $writer->writeUInt32BE
                            ($this->_itemId[$i]['extents'][$j]['offset']);
                }
                if ($offsetSize == 8) {
                    $writer->writeInt64BE
                            ($this->_itemId[$i]['extents'][$j]['offset']);
                }
                if ($offsetSize == 4) {
                    $writer->writeUInt32BE
                            ($this->_itemId[$i]['extents'][$j]['length']);
                }
                if ($offsetSize == 8) {
                    $writer->writeInt64BE
                            ($this->_itemId[$i]['extents'][$j]['length']);
                }
            }
        }
    }
}
