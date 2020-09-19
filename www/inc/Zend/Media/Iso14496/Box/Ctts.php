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
 * @version    $Id: Ctts.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Composition Time to Sample Box</i> provides the offset between
 * decoding time and composition time. Since decoding time must be less than the
 * composition time, the offsets are expressed as unsigned numbers such that
 * CT(n) = DT(n) + CTTS(n) where CTTS(n) is the (uncompressed) table entry for
 * sample n.
 *
 * The composition time to sample table is optional and must only be present if
 * DT and CT differ for any samples. Hint tracks do not use this box.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ctts.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Ctts extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_compositionOffsetTable = array();

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
            $this->_compositionOffsetTable[$i] = array
                ('sampleCount'  => $this->_reader->readUInt32BE(),
                 'sampleOffset' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o sampleCount -- an integer that counts the number of consecutive
     *     samples that have the given offset.
     *   o sampleOffset -- a non-negative integer that gives the offset between
     *     CT and DT, such that CT(n) = DT(n) + CTTS(n).
     *
     * @return Array
     */
    public function getCompositionOffsetTable()
    {
        return $this->_compositionOffsetTable;
    }

    /**
     * Sets an array of values. Each entry must have an array containing the
     * following keys.
     *   o sampleCount -- an integer that counts the number of consecutive
     *     samples that have the given offset.
     *   o sampleOffset -- a non-negative integer that gives the offset between
     *     CT and DT, such that CT(n) = DT(n) + CTTS(n).
     *
     * @param Array $compositionOffsetTable The array of values.
     */
    public function setCompositionOffsetTable($compositionOffsetTable)
    {
        $this->_compositionOffsetTable = $compositionOffsetTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 +
            count($this->_compositionOffsetTable) * 8;
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
        $writer->writeUInt32BE($entryCount = count($this->_compositionOffsetTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeUInt32BE
                        ($this->_compositionOffsetTable[$i]['sampleCount'])
                   ->writeUInt32BE
                        ($this->_compositionOffsetTable[$i]['sampleOffset']);
        }
    }
}
