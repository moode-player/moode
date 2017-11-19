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
 * @version    $Id: Sinf.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * The <i>Protection Scheme Information Box</i> contains all the information
 * required both to understand the encryption transform applied and its
 * parameters, and also to find other information such as the kind and location
 * of the key management system. It also documents the original (unencrypted)
 * format of the media. The Protection Scheme Info Box is a container Box. It is
 * mandatory in a sample entry that uses a code indicating a protected stream.
 *
 * When used in a protected sample entry, this box must contain the original
 * format box to document the original format. At least one of the following
 * signaling methods must be used to identify the protection applied:
 *
 *  a) MPEG-4 systems with IPMP: no other boxes, when IPMP descriptors in MPEG-4
 *     systems streams are used;
 *  b) Standalone IPMP: an {@link Zend_Media_Iso14496_Box_Imif IPMP Info Box},
 *     when IPMP descriptors outside MPEG-4 systems are used;
 *  c) Scheme signaling: a {@link Zend_Media_Iso14496_Box_Schm Scheme Type Box}
 *     and {@link Zend_Media_Iso14496_Box_Schi Scheme Information Box}, when
 *     these are used (either both must occur, or neither).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Sinf.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Sinf extends Zend_Media_Iso14496_Box
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
