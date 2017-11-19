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
 * @version    $Id: Stts.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Decoding Time to Sample Box</i> contains a compact version of a table
 * that allows indexing from decoding time to sample number. Other tables give
 * sample sizes and pointers, from the sample number. Each entry in the table
 * gives the number of consecutive samples with the same time delta, and the
 * delta of those samples. By adding the deltas a complete time-to-sample map
 * may be built.
 *
 * The Decoding Time to Sample Box contains decode time delta's: DT(n+1) = DT(n)
 * + STTS(n) where STTS(n) is the (uncompressed) table entry for sample n.
 *
 * The sample entries are ordered by decoding time stamps; therefore the deltas
 * are all non-negative.
 *
 * The DT axis has a zero origin; DT(i) = SUM(for j=0 to i-1 of delta(j)), and
 * the sum of all deltas gives the length of the media in the track (not mapped
 * to the overall timescale, and not considering any edit list).
 *
 * The {@link Zend_Media_Iso14496_Box_Elst Edit List Box} provides the initial
 * CT value if it is non-empty (non-zero).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stts.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Stts extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_timeToSampleTable = array();

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
            $this->_timeToSampleTable[$i] = array
                ('sampleCount' => $this->_reader->readUInt32BE(),
                 'sampleDelta' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o sampleCount -- an integer that counts the number of consecutive
     *     samples that have the given duration.
     *   o sampleDelta -- an integer that gives the delta of these samples in
     *     the time-scale of the media.
     *
     * @return Array
     */
    public function getTimeToSampleTable()
    {
        return $this->_timeToSampleTable;
    }

    /**
     * Sets an array of values. Each entry must be an array containing the
     * following keys.
     *   o sampleCount -- an integer that counts the number of consecutive
     *     samples that have the given duration.
     *   o sampleDelta -- an integer that gives the delta of these samples in
     *     the time-scale of the media.
     *
     * @param Array $timeToSampleTable The array of values.
     */
    public function setTimeToSampleTable($timeToSampleTable)
    {
        $this->_timeToSampleTable = $timeToSampleTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 + count($this->_timeToSampleTable) * 8;
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
        $writer->writeUInt32BE($entryCount = count($this->_timeToSampleTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeUInt32BE($this->_timeToSampleTable[$i]['sampleCount'])
                   ->writeUInt32BE
                        ($this->_timeToSampleTable[$i]['sampleDelta']);
        }
    }
}
