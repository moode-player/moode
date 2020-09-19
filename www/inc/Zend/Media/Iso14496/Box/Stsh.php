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
 * @version    $Id: Stsh.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Shadow Sync Sample Box</i> table provides an optional set of sync
 * samples that can be used when seeking or for similar purposes. In normal
 * forward play they are ignored.
 *
 * Each entry in the Shadow Sync Table consists of a pair of sample numbers. The
 * first entry (shadowedSampleNumber) indicates the number of the sample that a
 * shadow sync will be defined for. This should always be a non-sync sample
 * (e.g. a frame difference). The second sample number (syncSampleNumber)
 * indicates the sample number of the sync sample (i.e. key frame) that can be
 * used when there is a random access at, or before, the shadowedSampleNumber.
 *
 * The shadow sync samples are normally placed in an area of the track that is
 * not presented during normal play (edited out by means of an edit list),
 * though this is not a requirement. The shadow sync table can be ignored and
 * the track will play (and seek) correctly if it is ignored (though perhaps not
 * optimally).
 *
 * The Shadow Sync Sample replaces, not augments, the sample that it shadows
 * (i.e. the next sample sent is shadowedSampleNumber+1). The shadow sync sample
 * is treated as if it occurred at the time of the sample it shadows, having the
 * duration of the sample it shadows.
 *
 * Hinting and transmission might become more complex if a shadow sample is used
 * also as part of normal playback, or is used more than once as a shadow. In
 * this case the hint track might need separate shadow syncs, all of which can
 * get their media data from the one shadow sync in the media track, to allow
 * for the different time-stamps etc. needed in their headers.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Stsh.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Stsh extends Zend_Media_Iso14496_FullBox
{
    /** @var Array */
    private $_shadowSyncSampleTable = array();

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
            $this->_shadowSyncSampleTable[$i] = array
                ('shadowedSampleNumber' => $this->_reader->readUInt32BE(),
                 'syncSampleNumber' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o shadowedSampleNumber - gives the number of a sample for which there
     *     is an alternative sync sample.
     *   o syncSampleNumber - gives the number of the alternative sync sample.
     *
     * @return Array
     */
    public function getShadowSyncSampleTable()
    {
        return $this->_shadowSyncSampleTable;
    }

    /**
     * Sets an array of values. Each entry must be an array containing the
     * following keys.
     *   o shadowedSampleNumber - gives the number of a sample for which there
     *     is an alternative sync sample.
     *   o syncSampleNumber - gives the number of the alternative sync sample.
     *
     * @param Array $shadowSyncSampleTable The array of values.
     */
    public function setShadowSyncSampleTable($shadowSyncSampleTable)
    {
        $this->_shadowSyncSampleTable = $shadowSyncSampleTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 4 +
            count($this->_shadowSyncSampleTable) * 8;
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
        $writer->writeUInt32BE
            ($entryCount = count($this->_shadowSyncSampleTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeUInt32BE
                        ($this->_shadowSyncSampleTable[$i]
                            ['shadowedSampleNumber'])
                   ->writeUInt32BE
                        ($this->_shadowSyncSampleTable[$i]['syncSampleNumber']);
        }
    }
}
