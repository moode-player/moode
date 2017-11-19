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
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tfra.php 221 2011-05-04 05:31:08Z svollbehr $
 */

/**#@ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Track Fragment Random Access Box</i> provides entries that contains the location and the presentation time of
 * the random accessible sample. It indicates that the sample in the entry can be random accessed. Note that not every
 * random accessible sample in the track needs to be listed in the table.
 *
 * The absence of this box does not mean that all the samples are sync samples. Random access information in the
 * {@link Zend_Media_Iso14496_Box_Trun}, {@link Zend_Media_Iso14496_Box_Traf} and {@link Zend_Media_Iso14496_Box_Trex}
 * shall be set appropriately regardless of the presence of this box.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Georges-Etienne Legendre <legege@legege.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tfra.php 221 2011-05-04 05:31:08Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Tfra extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_trackId;

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

        $this->_trackId = $this->_reader->readUInt32BE();

        $reserved = (($tmp = $this->_reader->readUInt32BE()) >> 6) & 0x3ffffff;
        $lengthSizeOfTrafNum = ($tmp >> 4) & 0x3;
        $lengthSizeOfTrunNum = ($tmp >> 2) & 0x3;
        $lengthSizeOfSampleNum = $tmp & 0x3;

        $entryCount = $this->_reader->readUInt32BE();
        for ($i = 0; $i < $entryCount; $i++) {
            $entry = array();
            if ($this->getVersion() == 1) {
              $entry['time'] = $this->_reader->readInt64BE();
              $entry['moofOffset'] = $this->_reader->readInt64BE();
            } else {
              $entry['time'] = $this->_reader->readUInt32BE();
              $entry['moofOffset'] = $this->_reader->readUInt32BE();
            }

            switch ($lengthSizeOfTrafNum) {
            case 0:
                $entry['trafNumber'] = $this->_reader->readUInt8();
                break;
            case 1:
                $entry['trafNumber'] = $this->_reader->readUInt16BE();
                break;
            case 2:
                $entry['trafNumber'] = $this->_reader->readUInt32BE();
                break;
            case 3:
                $entry['trafNumber'] = $this->_reader->readInt64BE();
                break;
            }

            switch ($lengthSizeOfTrunNum) {
            case 0:
                $entry['trunNumber'] = $this->_reader->readUInt8();
                break;
            case 1:
                $entry['trunNumber'] = $this->_reader->readUInt16BE();
                break;
            case 2:
                $entry['trunNumber'] = $this->_reader->readUInt32BE();
                break;
            case 3:
                $entry['trunNumber'] = $this->_reader->readInt64BE();
                break;
            }

            switch ($lengthSizeOfSampleNum) {
            case 0:
                $entry['sampleNumber'] = $this->_reader->readUInt8();
                break;
            case 1:
                $entry['sampleNumber'] = $this->_reader->readUInt16BE();
                break;
            case 2:
                $entry['sampleNumber'] = $this->_reader->readUInt32BE();
                break;
            case 3:
                $entry['sampleNumber'] = $this->_reader->readInt64BE();
                break;
            }
            $this->_entries[] = $entry;
        }
    }

    /**
     * Returns the integer identifying the track ID.
     *
     * @return integer
     */
    public function getTrackId()
    {
        return $this->_trackId;
    }

    /**
     * Sets the integer identifying the track ID.
     *
     * @param integer $trackId The track ID.
     */
    public function setTrackId($trackId)
    {
        $this->_trackId = $trackId;
    }

    /**
     * Returns an array of entries. Each entry is an array containing the
     * following keys.
     *   o time: is 32 or 64 bits integer that indicates the presentation time of the random access sample in units
     *     defined in the {@link Zend_Media_Iso14496_Box_Mdhd} of the associated track.
     *   o moofOffset: is 32 or 64 bits integer that gives the offset of the {@link Zend_Media_Iso14496_Box_Moof} used
     *     in this entry. Offset is the byte-offset between the beginning of the file and the beginning of the
     *     {@link Zend_Media_Iso14496_Box_Moof}.
     *   o trafNumber: indicates the {@link Zend_Media_Iso14496_Box_Traf} number that contains the random accessible
     *     sample. The number ranges from 1 (the first traf is numbered 1) in each {@link Zend_Media_Iso14496_Box_Moof},
     *   o trunNumber: indicates the {@link Zend_Media_Iso14496_Box_Trun} number that contains the random accessible
     *     sample. The number ranges from 1 in each {@link Zend_Media_Iso14496_Box_Traf}.
     *   o sampleNumber: indicates the sample number that contains the random accessible sample. The number ranges from
     *     1 in each {@link Zend_Media_Iso14496_Box_Trun}.
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
     *   o time: is 32 or 64 bits integer that indicates the presentation time of the random access sample in units
     *     defined in the {@link Zend_Media_Iso14496_Box_Mdhd} of the associated track.
     *   o moofOffset: is 32 or 64 bits integer that gives the offset of the {@link Zend_Media_Iso14496_Box_Moof} used
     *     in this entry. Offset is the byte-offset between the beginning of the file and the beginning of the
     *     {@link Zend_Media_Iso14496_Box_Moof}.
     *   o trafNumber: indicates the {@link Zend_Media_Iso14496_Box_Traf} number that contains the random accessible
     *     sample. The number ranges from 1 (the first traf is numbered 1) in each {@link Zend_Media_Iso14496_Box_Moof},
     *   o trunNumber: indicates the {@link Zend_Media_Iso14496_Box_Trun} number that contains the random accessible
     *     sample. The number ranges from 1 in each {@link Zend_Media_Iso14496_Box_Traf}.
     *   o sampleNumber: indicates the sample number that contains the random accessible sample. The number ranges from
     *     1 in each {@link Zend_Media_Iso14496_Box_Trun}.
     *
     * @return Array
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
        $lengthSizes = $this->_getLengthSizes();
        return parent::getHeapSize() + 12 +
            count($this->_entries) * ($this->getVersion() == 1 ? 16 : 8) +
            count($this->_entries) * ($lengthSizes['trafNum'] + 1) +
            count($this->_entries) * ($lengthSizes['trunNum'] + 1) +
            count($this->_entries) * ($lengthSizes['sampleNum'] + 1);
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
        $lengthSizes = $this->_getLengthSizes();
        $writer->writeUInt32BE($this->_trackId)
               ->writeUInt32BE
                    (($lengthSizes['trafNum'] << 4) | ($lengthSizes['trunNum'] << 2) | ($lengthSizes['sampleNum']))
               ->writeUInt32BE($entryCount = count($this->_entries));
        for ($i = 0; $i < $entryCount; $i++) {
            if ($this->getVersion() == 1) {
                $writer->writeInt64BE($this->_entries[$i]['time'])
                       ->writeInt64BE($this->_entries[$i]['mediaTime']);
            } else {
                $writer->writeUInt32BE($this->_entries[$i]['time'])
                       ->writeInt32BE($this->_entries[$i]['moofOffset']);
            }

            switch ($lengthSizes['trafNum']) {
            case 0:
                $writer->writeUInt8($this->_entries[$i]['trafNumber']);
                break;
            case 1:
                $writer->writeUInt16BE($this->_entries[$i]['trafNumber']);
                break;
            case 2:
                $writer->writeUInt32BE($this->_entries[$i]['trafNumber']);
                break;
            case 3:
                $writer->writeInt64BE($this->_entries[$i]['trafNumber']);
                break;
            }

            switch ($lengthSizes['trunNum']) {
            case 0:
                $writer->writeUInt8($this->_entries[$i]['trunNumber']);
                break;
            case 1:
                $writer->writeUInt16BE($this->_entries[$i]['trunNumber']);
                break;
            case 2:
                $writer->writeUInt32BE($this->_entries[$i]['trunNumber']);
                break;
            case 3:
                $writer->writeInt64BE($this->_entries[$i]['trunNumber']);
                break;
            }

            switch ($lengthSizes['sampleNum']) {
            case 0:
                $writer->writeUInt8($this->_entries[$i]['sampleNumber']);
                break;
            case 1:
                $writer->writeUInt16BE($this->_entries[$i]['sampleNumber']);
                break;
            case 2:
                $writer->writeUInt32BE($this->_entries[$i]['sampleNumber']);
                break;
            case 3:
                $writer->writeInt64BE($this->_entries[$i]['sampleNumber']);
                break;
            }
        }
    }

    /**
     * Returns the length sizes based on maximum numbers for each type of integer.
     * @return integer Returns the length sizes based on maximum numbers for each type of integer.
     */
    private function _getLengthSizes()
    {
        $maxTrafNum = $maxTrunNum = $maxSampleNum = 0;
        foreach ($this->entries as $entry) {
            if ($maxTrafNum < $entry['trafNumber']) {
                $maxTrafNum = $entry['trafNumber'];
            }
            if ($maxTrunNum < $entry['trunNumber']) {
                $maxTrunNum = $entry['trunNumber'];
            }
            if ($maxSampleNum < $entry['sampleNumber']) {
                $maxSampleNum = $entry['sampleNumber'];
            }
        }
        return array(
             'trafNum' => $this->_getLengthSizeFromInteger($maxTrafNum),
             'trunNum' => $this->_getLengthSizeFromInteger($maxTrunNum),
             'sampleNum' => $this->_getLengthSizeFromInteger($maxSampleNum)
        );
    }

    /**
     * Returns the length size based on the given integer.
     *
     * @param integer $integer The integer to determine the length size from.
     * @return Returns the length size of the given integer.
     */
    private function _getLengthSizeFromInteger($integer)
    {
        if ($integer <= 0xff) {
            return 0;
        } else if ($integer <= 0xffff) {
            return 1;
        } else if ($integer <= 0xffffffff) {
            return 2;
        } else {
            return 3;
        }
    }
}
