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
 * @version    $Id: Sbgp.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Sample To Group Box</i> table can be used to find the group that a
 * sample belongs to and the associated description of that sample group. The
 * table is compactly coded with each entry giving the index of the first sample
 * of a run of samples with the same sample group descriptor. The sample group
 * description ID is an index that refers to a
 * {@link Zend_Media_Iso14496_Box_Sgpd Sample Group Description Box}, which
 * contains entries describing the characteristics of each sample group.
 *
 * There may be multiple instances of this box if there is more than one sample
 * grouping for the samples in a track. Each instance of the Sample To Group Box
 * has a type code that distinguishes different sample groupings. Within a
 * track, there shall be at most one instance of this box with a particular
 * grouping type. The associated Sample Group Description shall indicate the
 * same value for the grouping type.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sbgp.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Sbgp extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_groupingType;

    /** @var Array */
    private $_sampleToGroupTable = array();

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

        $groupingType = $this->_reader->readUInt32BE();
        $entryCount = $this->_reader->readUInt32BE();
        for ($i = 1; $i <= $entryCount; $i++) {
            $this->_sampleToGroupTable[$i] = array
                ('sampleCount' => $this->_reader->readUInt32BE(),
                 'groupDescriptionIndex' => $this->_reader->readUInt32BE());
        }
    }

    /**
     * Returns the grouping type that identifies the type (i.e. criterion used
     * to form the sample groups) of the sample grouping and links it to its
     * sample group description table with the same value for grouping type. At
     * most one occurrence of this box with the same value for groupingType
     * shall exist for a track.
     *
     * @return integer
     */
    public function getGroupingType()
    {
        return $this->_groupingType;
    }

    /**
     * Sets the grouping type that identifies the type (i.e. criterion used
     * to form the sample groups) of the sample grouping and links it to its
     * sample group description table with the same value for grouping type. At
     * most one occurrence of this box with the same value for groupingType
     * shall exist for a track.
     *
     * @param integer $groupingType The grouping type.
     */
    public function setGroupingType($groupingType)
    {
        $this->_groupingType = $groupingType;
    }

    /**
     * Returns an array of values. Each entry is an array containing the
     * following keys.
     *   o sampleCount -- an integer that gives the number of consecutive
     *     samples with the same sample group descriptor. If the sum of the
     *     sample count in this box is less than the total sample count, then
     *     the reader should effectively extend it with an entry that associates
     *     the remaining samples with no group. It is an error for the total in
     *     this box to be greater than the sample_count documented elsewhere,
     *     and the reader behavior would then be undefined.
     *   o groupDescriptionIndex -- an integer that gives the index of the
     *     sample group entry which describes the samples in this group. The
     *     index ranges from 1 to the number of sample group entries in the
     *     {@link Zend_Media_Iso14496_Box_Sgpd Sample Group Description Box},
     *     or takes the value 0 to indicate that this sample is a member of no
     *     group of this type.
     *
     * @return Array
     */
    public function getSampleToGroupTable()
    {
        return $this->_sampleToGroupTable;
    }

    /**
     * Sets the array of values. Each entry must be an array containing the
     * following keys.
     *   o sampleCount -- an integer that gives the number of consecutive
     *     samples with the same sample group descriptor. If the sum of the
     *     sample count in this box is less than the total sample count, then
     *     the reader should effectively extend it with an entry that associates
     *     the remaining samples with no group. It is an error for the total in
     *     this box to be greater than the sample_count documented elsewhere,
     *     and the reader behavior would then be undefined.
     *   o groupDescriptionIndex -- an integer that gives the index of the
     *     sample group entry which describes the samples in this group. The
     *     index ranges from 1 to the number of sample group entries in the
     *     {@link Zend_Media_Iso14496_Box_Sgpd Sample Group Description Box},
     *     or takes the value 0 to indicate that this sample is a member of no
     *     group of this type.
     *
     * @param Array $sampleToGroupTable The array of entries
     */
    public function setSampleToGroupTable($sampleToGroupTable)
    {
        $this->_sampleToGroupTable = $sampleToGroupTable;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 8 +
            count($this->_sampleToGroupTable) * 8;
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
        $writer->writeUInt32BE($this->_groupingType);
        $writer->writeUInt32BE($entryCount = count($this->_sampleToGroupTable));
        for ($i = 1; $i <= $entryCount; $i++) {
            $writer->writeUInt32BE
                        ($this->_sampleToGroupTable[$i]['sampleCount'])
                   ->writeUInt32BE
                        ($this->_sampleToGroupTable[$i]
                           ['groupDescriptionIndex']);
        }
    }
}
