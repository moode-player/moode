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
 * @version    $Id: Stsc.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * Samples within the media data are grouped into chunks. Chunks can be of
 * different sizes, and the samples within a chunk can have different sizes.
 * The <i>Sample To Chunk Box</i> table can be used to find the chunk that
 * contains a sample, its position, and the associated sample description.
 *
 * The table is compactly coded. Each entry gives the index of the first chunk
 * of a run of chunks with the same characteristics. By subtracting one entry
 * here from the previous one, you can compute how many chunks are in this run.
 * You can convert this to a sample count by multiplying by the appropriate
 * samplesPerChunk.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stsc.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Stsc extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_sampleToChunkTable = array();

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
            $this->_sampleToChunkTable[$i] = array
                ('firstChunk' => $this->_reader->readUInt32BE(),
                 'samplesPerChunk' => $this->_reader->readUInt32BE(),
                 'sampleDescriptionIndex' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o firstChunk -- an integer that gives the index of the first chunk in
     *     this run of chunks that share the same samplesPerChunk and
     *     sampleDescriptionIndex; the index of the first chunk in a track has
     *     the value 1 (the firstChunk field in the first record of this box
     *     has the value 1, identifying that the first sample maps to the first
     *     chunk).
     *   o samplesPerChunk is an integer that gives the number of samples in
     *     each of these chunks.
     *   o sampleDescriptionIndex is an integer that gives the index of the
     *     sample entry that describes the samples in this chunk. The index
     *     ranges from 1 to the number of sample entries in the
     *     {@link Zend_Media_Iso14496_Box_Stsd Sample Description Box}.
     *
     * @return Array
     */
    public function getSampleToChunkTable()
    {
        return $this->_sampleToChunkTable;
    }

    /**
     * Sets an array of values. Each entry is an array containing the
     * following keys.
     *   o firstChunk -- an integer that gives the index of the first chunk in
     *     this run of chunks that share the same samplesPerChunk and
     *     sampleDescriptionIndex; the index of the first chunk in a track has
     *     the value 1 (the firstChunk field in the first record of this box
     *     has the value 1, identifying that the first sample maps to the first
     *     chunk).
     *   o samplesPerChunk is an integer that gives the number of samples in
     *     each of these chunks.
     *   o sampleDescriptionIndex is an integer that gives the index of the
     *     sample entry that describes the samples in this chunk. The index
     *     ranges from 1 to the number of sample entries in the
     *     {@link Zend_Media_Iso14496_Box_Stsd Sample Description Box}.
     *
     * @param Array $sampleToChunkTable The array of values.
     */
    public function setSampleToChunkTable($sampleToChunkTable)
    {
        $this->_sampleToChunkTable = $sampleToChunkTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 +
            count($this->_sampleToChunkTable) * 12;
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
        $writer->writeUInt32BE($entryCount = count($this->_sampleToChunkTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeUInt32BE
                        ($this->_sampleToChunkTable[$i]['firstChunk'])
                   ->writeUInt32BE
                        ($this->_sampleToChunkTable[$i]['samplesPerChunk'])
                   ->writeUInt32BE
                        ($this->_sampleToChunkTable[$i]
                            ['sampleDescriptionIndex']);
        }
    }
}
