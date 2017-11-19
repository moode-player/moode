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
 * @version    $Id: Meta.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Meta Box</i> contains descriptive or annotative metadata. The
 * <i>meta</i> box is required to contain a
 * {@link Zend_Media_Iso14496_Box_Hdlr hdlr} box indicating the structure or
 * format of the <i>meta</i> box contents. That metadata is located either
 * within a box within this box (e.g. an XML box), or is located by the item
 * identified by a primary item box.
 *
 * All other contained boxes are specific to the format specified by the handler
 * box.
 *
 * The other boxes defined here may be defined as optional or mandatory for a
 * given format. If they are used, then they must take the form specified here.
 * These optional boxes include a data-information box, which documents other
 * files in which metadata values (e.g. pictures) are placed, and a item
 * location box, which documents where in those files each item is located (e.g.
 * in the common case of multiple pictures stored in the same file). At most one
 * meta box may occur at each of the file level, movie level, or track level.
 *
 * If an {@link Zend_Media_Iso14496_Box_Ipro Item Protection Box} occurs, then
 * some or all of the meta-data, including possibly the primary resource, may
 * have been protected and be un-readable unless the protection system is taken
 * into account.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Meta.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Meta extends Zend_Media_Iso14496_FullBox
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

        $this->constructBoxes();
    }
}
