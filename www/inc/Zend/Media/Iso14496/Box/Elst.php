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
 * @version    $Id: Elst.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Edit List Box</i> contains an explicit timeline map. Each entry
 * defines part of the track time-line: by mapping part of the media time-line,
 * or by indicating empty time, or by defining a dwell, where a single
 * time-point in the media is held for a period.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Elst.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Elst extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_entries = array();

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
        for ($i = 0; $i < $entryCount; $i++) {
            $entry = array();
            if ($this->getVersion() == 1) {
                $entry['segmentDuration'] = $this->_reader->readInt64BE();
                $entry['mediaTime'] = $this->_reader->readInt64BE();
            } else {
                $entry['segmentDuration'] = $this->_reader->readUInt32BE();
                $entry['mediaTime'] = $this->_reader->readInt32BE();
            }
            $entry['mediaRate'] =
                (float)($this->_reader->readInt16BE() . "." .
                    $this->_reader->readInt16BE());
            $this->_entries[] = $entry;
        }
    }

    /**
     * Returns an array of entries. Each entry is an array containing the
     * following keys.
     *   o segmentDuration: specifies the duration of this edit segment in units
     *     of the timescale in the
     *     {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}.
     *   o mediaTime: the starting time within the media of this edit segment
     *     (in media time scale units, in composition time). If this field is
     *     set to –1, it is an empty edit. The last edit in a track shall never
     *     be an empty edit. Any difference between the duration in the
     *     {@link Zend_Media_Iso14496_Box_MVHD Movie Header Box}, and the
     *     track's duration is expressed as an implicit empty edit at the end.
     *   o mediaRate: the relative rate at which to play the media corresponding
     *     to this edit segment. If this value is 0, then the edit is specifying
     *     a dwell: the media at media-time is presented for the
     *     segment-duration. Otherwise this field shall contain the value 1.
     *
     * @return Array
     */
    public function getEntries()
    {
        return $this->_entries;
    }

    /**
     * Sets the array of entries. Each entry must be an array containing the
     * following keys.
     *   o segmentDuration: specifies the duration of this edit segment in units
     *     of the timescale in the
     *     {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}.
     *   o mediaTime: the starting time within the media of this edit segment
     *     (in media time scale units, in composition time). If this field is
     *     set to –1, it is an empty edit. The last edit in a track shall never
     *     be an empty edit. Any difference between the duration in the
     *     {@link Zend_Media_Iso14496_Box_MVHD Movie Header Box}, and the
     *     track's duration is expressed as an implicit empty edit at the end.
     *   o mediaRate: the relative rate at which to play the media corresponding
     *     to this edit segment. If this value is 0, then the edit is specifying
     *     a dwell: the media at media-time is presented for the
     *     segment-duration. Otherwise this field shall contain the value 1.
     *
     * @param Array $entries The array of entries;
     */
    public function setEntries($entries)
    {
        $this->_entries = $entries;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 + count($this->_entries) *
            ($this->getVersion() == 1 ? 20 : 12);
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
        $writer->writeUInt32BE($entryCount = count($this->_entries));
        for ($i = 0; $i < $entryCount; $i++) {
            if ($this->getVersion() == 1) {
                $writer->writeInt64BE($this->_entries[$i]['segmentDuration'])
                       ->writeInt64BE($this->_entries[$i]['mediaTime']);
            } else {
                $writer->writeUInt32BE($this->_entries[$i]['segmentDuration'])
                       ->writeInt32BE($this->_entries[$i]['mediaTime']);
            }
            @list($mediaRateInteger, $mediaRateFraction) = explode
                ('.', (float)$this->_entries[$i]['mediaRate']);
            $writer->writeInt16BE($mediaRateInteger)
                   ->writeInt16BE($mediaRateFraction);
        }
    }
}
