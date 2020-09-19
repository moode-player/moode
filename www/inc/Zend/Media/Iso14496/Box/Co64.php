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
 * @version    $Id: Co64.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Chunk Offset Box</i> table gives the index of each chunk into the
 * containing file. There are two variants, permitting the use of 32-bit or
 * 64-bit offsets. The latter is useful when managing very large presentations.
 * At most one of these variants will occur in any single instance of a sample
 * table.
 *
 * Offsets are file offsets, not the offset into any box within the file (e.g.
 * {@link Zend_Media_Iso14496_Box_Mdat Media Data Box}). This permits referring
 * to media data in files without any box structure. It does also mean that care
 * must be taken when constructing a self-contained ISO file with its metadata
 * ({@link Zend_Media_Iso14496_Box_Moov Movie Box}) at the front, as the size of
 * the {@link Zend_Media_Iso14496_Box_Moov Movie Box} will affect the chunk
 * offsets to the media data.
 *
 * This box variant contains 64-bit offsets.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Co64.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Co64 extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_chunkOffsetTable = array();

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

        $entryCount = $this->_reader->readUInt32BE();
        for ($i = 1; $i <= $entryCount; $i++) {
            $this->_chunkOffsetTable[$i] = $this->_reader->readInt64BE();
        }
    }

    /**
     * Returns an array of values. Each entry has the entry number as its index
     * and a 64 bit integer that gives the offset of the start of a chunk into
     * its containing media file as its value.
     *
     * @return Array
     */
    public function getChunkOffsetTable() 
    {
        return $this->_chunkOffsetTable; 
    }

    /**
     * Sets an array of chunk offsets. Each entry must have the entry number as
     * its index and a 64 bit integer that gives the offset of the start of a
     * chunk into its containing media file as its value.
     *
     * @param Array $chunkOffsetTable The chunk offset array.
     */
    public function setChunkOffsetTable($chunkOffsetTable)
    {
        $this->_chunkOffsetTable = $chunkOffsetTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 + count($this->_chunkOffsetTable) * 8;
    }

    /**
     * Writes the box data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt32BE($entryCount = count($this->_chunkOffsetTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeInt64BE($this->_chunkOffsetTable[$i]);
        }
    }
}
