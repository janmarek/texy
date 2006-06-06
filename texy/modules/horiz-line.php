<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.5 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizLineModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    /**
     * Module initialization.
     */
    function init()
    {
        $this->texy->registerBlockPattern($this, 'processBlock', '#^(\- |\-|\* |\*){3,}\ *<MODIFIER_H>?()$#mU');
    }



    /**
     * Callback function (for blocks)
     *
     *            ---------------------------
     *
     *            - - - - - - - - - - - - - -
     *
     *            ***************************
     *
     *            * * * * * * * * * * * * * *
     *
     */
    function processBlock(&$parser, $matches)
    {
        list(, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ---
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

        $el = &new TexyBlockElement($this->texy);
        $el->tag = 'hr';
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);
    }




} // TexyHorizlineModule



?>