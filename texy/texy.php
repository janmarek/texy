<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    2.0beta for PHP5 $Revision$ $Date$
 */


if (version_compare(PHP_VERSION , '5.0.0', '<'))
    die('Texy! needs PHP version 5');

define('TEXY', 'Version 2.0beta $Revision$');


/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/RegExp.Patterns.php';
require_once TEXY_DIR.'libs/TexyHtml.php';
require_once TEXY_DIR.'libs/TexyHtmlFormatter.php';
require_once TEXY_DIR.'libs/TexyHtmlWellForm.php';
require_once TEXY_DIR.'libs/TexyModifier.php';
require_once TEXY_DIR.'libs/TexyModule.php';
require_once TEXY_DIR.'libs/TexyParser.php';
require_once TEXY_DIR.'libs/TexyUtf.php';
require_once TEXY_DIR.'modules/TexyBlockModule.php';
require_once TEXY_DIR.'modules/TexyHeadingModule.php';
require_once TEXY_DIR.'modules/TexyHorizLineModule.php';
require_once TEXY_DIR.'modules/TexyHtmlModule.php';
require_once TEXY_DIR.'modules/TexyFigureModule.php';
require_once TEXY_DIR.'modules/TexyImageModule.php';
require_once TEXY_DIR.'modules/TexyLinkModule.php';
require_once TEXY_DIR.'modules/TexyListModule.php';
require_once TEXY_DIR.'modules/TexyDefinitionListModule.php';
require_once TEXY_DIR.'modules/TexyLongWordsModule.php';
require_once TEXY_DIR.'modules/TexyPhraseModule.php';
require_once TEXY_DIR.'modules/TexyQuoteModule.php';
require_once TEXY_DIR.'modules/TexyScriptModule.php';
require_once TEXY_DIR.'modules/TexyEmoticonModule.php';
require_once TEXY_DIR.'modules/TexyTableModule.php';
require_once TEXY_DIR.'modules/TexyTypographyModule.php';


/**
 * Texy! - Convert plain text to XHTML format using {@link process()}
 *
 * <code>
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 * </code>
 */
class Texy
{
    // configuration directives
    const ALL = TRUE;
    const NONE = FALSE;

    // types of protection marks
    const CONTENT_MARKUP = "\x17";
    const CONTENT_REPLACED = "\x16";
    const CONTENT_TEXTUAL = "\x15";
    const CONTENT_BLOCK = "\x14";

    /** @var string  input & output text encoding */
    public $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    public $allowed = array();

    /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses;

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles;

    /** @var boolean  Do obfuscate e-mail addresses? */
    public $obfuscateEmail = TRUE;

    /** @var string  regexp to check URL schemes */
    public $urlSchemes = NULL;

    /** @var array  Parsing summary */
    public $summary = array(
        'images' => array(),
        'links' => array(),
        'preload' => array(),
    );

    /** @var string  Generated stylesheet */
    public $styleSheet = '';

    /** @var bool  Paragraph merging mode */
    public $mergeLines = TRUE;

    /** @var object  User handler object */
    public $handler;

    public
        /** @var TexyScriptModule */
        $scriptModule,
        /** @var TexyHtmlModule */
        $htmlModule,
        /** @var TexyImageModule */
        $imageModule,
        /** @var TexyLinkModule */
        $linkModule,
        /** @var TexyPhraseModule */
        $phraseModule,
        /** @var TexyEmoticonModule */
        $emoticonModule,
        /** @var TexyBlockModule */
        $blockModule,
        /** @var TexyHeadingModule */
        $headingModule,
        /** @var TexyHorizLineModule */
        $horizLineModule,
        /** @var TexyQuoteModule */
        $quoteModule,
        /** @var TexyListModule */
        $listModule,
        /** @var TexyDefinitionListModule */
        $definitionListModule,
        /** @var TexyTableModule */
        $tableModule,
        /** @var TexyFigureModule */
        $figureModule,
        /** @var TexyTypographyModule */
        $typographyModule,
        /** @var TexyLongWordsModule */
        $longWordsModule;

    public
        $formatter,
        $formatterModule, // back compatibility
        $wellForm;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression
     *                'name'    => pattern's name)
     */
    private $linePatterns = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression
     *                'name'    => pattern's name)
     */
    private $blockPatterns = array();


    /** @var TexyDomElement  DOM structure for parsed text */
    private $DOM;

    /** @var TexyModule[]  List of all modules */
    private $modules;

    /** @var array  Texy protect markup table */
    private $marks = array();

    /** @var bool  how split paragraphs (internal usage) */
    public $_paragraphMode;

    /** @var array  for internal usage */
    public $_classes, $_styles;





    public function __construct()
    {
        // load all modules
        $this->loadModules();

        // load routines
        $this->formatter = new TexyHtmlFormatter();
        $this->wellForm = new TexyHtmlWellForm();

        $this->formatterModule = $this->formatter; // back compatibility

        // default configuration
        $this->trustMode();

        // examples of link reference ;-)
        $mod = new TexyModifier;
        $mod->title = 'The best text -> HTML converter and formatter';
        $this->linkModule->addReference('texy', 'http://texy.info/', 'Texy!', $mod);
        $this->linkModule->addReference('google', 'http://www.google.com/search?q=%s');
        $this->linkModule->addReference('wikipedia', 'http://en.wikipedia.org/wiki/Special:Search?search=%s');
    }



    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     */
    protected function loadModules()
    {
        // Line parsing - order is not important
        $this->scriptModule = new TexyScriptModule($this);
        $this->htmlModule = new TexyHtmlModule($this);
        $this->imageModule = new TexyImageModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->linkModule = new TexyLinkModule($this);
        $this->emoticonModule = new TexyEmoticonModule($this);

        // block parsing - order is not important
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->definitionListModule = new TexyDefinitionListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->figureModule = new TexyFigureModule($this);

        // post process - order is not important
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
    }



    public function registerModule($module)
    {
        $this->modules[] = $module;
    }



    public function registerLinePattern($handler, $pattern, $name)
    {
        if (empty($this->allowed[$name])) return;
        $this->linePatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern,
        );
    }



    public function registerBlockPattern($handler, $pattern, $name)
    {
        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));
        if (empty($this->allowed[$name])) return;
        $this->blockPatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern  . 'm',  // force multiline
        );
    }




    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & toHtml()
     *
     * @param string   input text
     * @param bool     is block or single line?
     * @return string  output html code
     */
    public function process($text, $singleLine = FALSE)
    {
        $this->parse($text, $singleLine);
        return $this->toHtml();
    }



    /**
     * Converts Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     *
     * @param string
     * @param bool     is block or single line?
     * @return void
     */
    public function parse($text, $singleLine = FALSE)
    {
         // initialization
        if ($this->handler && !is_object($this->handler))
            throw new Exception('$texy->handler must be object. See documentation.');

        $this->_paragraphMode = TRUE;
        $this->marks = array();

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        $tmp = array($this->linePatterns, $this->blockPatterns);

        // convert to UTF-8 (and check source encoding)
        $text = iconv($this->encoding, 'utf-8', $text);

        // standardize line endings and spaces
        $text = self::normalize($text);

        // init modules
        foreach ($this->modules as $module) $module->init($text);

        // parse!
        $this->DOM = TexyHtml::el();
        if ($singleLine)
            $this->DOM->parseLine($this, $text);
        else
            $this->DOM->parseBlock($this, $text);

        // user handler
        if (is_callable(array($this->handler, 'afterParse')))
            $this->handler->afterParse($this, $this->DOM, $singleLine);

        // clean-up
        list($this->linePatterns, $this->blockPatterns) = $tmp;
    }





    /**
     * Converts internal DOM structure to final HTML code
     * @return string
     */
    public function toHtml()
    {
        // Convert DOM structure to (X)HTML code
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        $html = $this->_toHtml( $this->DOM->export($this) );

        // this notice should remain!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- by Texy2! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

        $html = TexyUtf::utf2html($html, $this->encoding);

        return $html;
    }



    /**
     * Converts internal DOM structure to pure Text
     * @return string
     */
    public function toText()
    {
        // Convert DOM structure to (X)HTML code
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        $text = $this->_toText( $this->DOM->export($this) );

        $text = iconv('utf-8', $this->encoding.'//TRANSLIT', $text);

        return $text;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    public function _toHtml($s)
    {
        // decode HTML entities to UTF-8
        $s = self::decode($s);

        // line-postprocessing
        $blocks = explode(self::CONTENT_BLOCK, $s);
        foreach ($this->modules as $module) {
            if ($module instanceof ITexyLineModule) {
                foreach ($blocks as $n => $s) {
                    if ($n % 2 === 0 && $s !== '')
                        $blocks[$n] = $module->postLine($s);
                }
            }
        }
        $s = implode(self::CONTENT_BLOCK, $blocks);

        // encode < > &
        $s = self::encode($s);

        // replace protected marks
        $s = $this->unProtect($s);

        // wellform and reformat HTML
        $s = $this->wellForm->process($s);
        $s = $this->formatter->process($s);

        // remove HTML 4.01 optional tags
        if (!TexyHtml::$XHTML)
            $s = preg_replace('#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#', '', $s);

        // unfreeze spaces
        $s = self::unfreezeSpaces($s);

        return $s;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    public function _toText($s)
    {
        $save = $this->formatter->lineWrap;
        $this->formatter->lineWrap = FALSE;
        $s = $this->_toHtml( $s );
        $this->formatter->lineWrap = $save;

        // remove tags
        $s = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $s);
        $s = strip_tags($s);
        $s = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $s);

        // entities -> chars
        $s = Texy::decode($s);

        // convert nbsp to normal space and remove shy
        $s = strtr($s, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        return $s;
    }




    /**
     * Switch Texy! configuration to the safe mode
     * Suitable for web comments and other usages, where input text may insert attacker
     */
    public function safeMode()
    {
        $this->allowedClasses = self::NONE;                 // no class or ID are allowed
        $this->allowedStyles  = self::NONE;                 // style modifiers are disabled
        $this->allowedTags = array(                         // only some "safe" HTML tags and attributes are allowed
            'a'         => array('href'),
            'acronym'   => array('title'),
            'b'         => array(),
            'br'        => array(),
            'cite'      => array(),
            'code'      => array(),
            'em'        => array(),
            'i'         => array(),
            'strong'    => array(),
            'sub'       => array(),
            'sup'       => array(),
            'q'         => array(),
            'small'     => array(),
        );
        $this->urlSchemes = '#https?:|ftp:|mailto:#Ai';
        $this->allowed['image'] = FALSE;                    // disable images
        $this->allowed['link/definition'] = FALSE;          // disable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
        //$this->mergeLines = FALSE;                          // enter means <BR>
    }



    /**
     * Switch Texy! configuration to the (default) trust mode
     */
    public function trustMode()
    {
        $this->allowedClasses = self::ALL;                  // classes and id are allowed
        $this->allowedStyles  = self::ALL;                  // inline styles are allowed
        $this->allowedTags = array_merge(TexyHtml::$blockTags,
            TexyHtml::$inlineTags, TexyHtml::$replacedTags); // full support for "valid" HTML tags
        $this->urlSchemes = NULL;
        $this->allowed['image'] = TRUE;                     // enable images
        $this->allowed['link/definition'] = TRUE;           // enable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
        //$this->mergeLines = TRUE;                           // enter doesn't means <BR>
    }



    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by TexyHtmlFormatter routine
     * @param string
     * @return string
     */
    static public function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }



    /**
     * Reverts meta-spaces back to normal spaces
     * @param string
     * @return string
     */
    static public function unfreezeSpaces($s)
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * Removes special controls characters and normalizes line endings and spaces
     * @param string
     * @return string
     */
    static public function normalize($s)
    {
        // remove special chars
        $s = preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $s);

        // standardize line endings to unix-like
        $s = str_replace("\r\n", "\n", $s); // DOS
        $s = strtr($s, "\r", "\n"); // Mac

        // right trim
        $s = preg_replace("#[\t ]+$#m", '', $s); // right trim

        // replace tabs with spaces
        while (strpos($s, "\t") !== FALSE)
            $s = preg_replace_callback('#^(.*)\t#mU', array('Texy', 'tabCb'), $s);

        // trailing spaces
        $s = trim($s, "\n");

        return $s;
    }



    /**
     * Converts to web safe characters [a-z0-9.-] text
     * @param string
     * @return string
     */
    static public function webalize($s)
    {
        $s = TexyUtf::utf2ascii($s);
        $s = preg_replace('#[\s-]+#', '-', $s);
        $s = strtolower($s);
        $s = preg_replace('#[^a-z0-9.-]+#', '', $s);
        return $s;
    }



    /**
     * Texy! version of htmlSpecialChars (much faster than htmlSpecialChars!)
     * @param string
     * @return string
     */
    static public function encode($s)
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $s);
    }


    /**
     * Texy! version of html_entity_decode (always UTF-8, much faster than original!)
     * @param string
     * @return string
     */
    static public function decode($s)
    {
        if (strpos($s, '&') === FALSE) return $s;
        return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    }


    /**
     * Generate unique mark - useful for freezing (folding) some substrings
     * @param string   any string to froze
     * @param int      Texy::CONTENT_* constant
     * @return string  internal mark
     */
    public function protect($child, $contentType=self::CONTENT_BLOCK)
    {
        if ($child==='') return '';

        $key = $contentType
            . strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $contentType;

        $this->marks[$key] = $child;

        return $key;
    }



    public function unProtect($html)
    {
        return strtr($html, $this->marks);
    }



    /**
     * Generates "real" URL
     * @param string   user URL
     * @param string   root for relative URL's
     * @return string|FALSE
     */
    public function completeURL($URL, $root)
    {
        // invalid URL
        if ($URL == NULL) return FALSE;

        // special supported case
        if (strncasecmp($URL, 'www.', 4) === 0) return 'http://' . $URL;

        // special supported case
        if (preg_match('#'.TEXY_EMAIL.'$#iA', $URL)) return 'mailto:' . $URL;

        // absolute URL with scheme
        if (preg_match('#[a-z]+:#iA', $URL)) {
            if ($this->urlSchemes && !preg_match($this->urlSchemes, $URL)) return FALSE;
            // replace unwanted &amp;
            return str_replace('&amp;', '&', $URL);
        }

        // absolute URL without scheme
        if ($URL[0] === '/') return $URL;

        // relative URL
        if ($root == NULL) return $URL;
        return rtrim($root, '/\\') . '/' . $URL;
    }



    /**
     * Generates "real" file path
     * @param string   user URL/path
     * @param string   root for relative paths
     * @return string|FALSE
     */
    public function completePath($path, $root)
    {
        // absolute URL
        if (preg_match('#[a-z]+:|www\.|/|'.TEXY_EMAIL.'#iA', $path))
            return FALSE;

        // security
        if (strpos($path, '..')) return FALSE;

        // relative path
        if ($root == NULL) return $path;
        return rtrim($root, '/\\') . '/' . $path;
    }



    public function getLinePatterns()
    {
        return $this->linePatterns;
    }



    public function getBlockPatterns()
    {
        return $this->blockPatterns;
    }



    public function getDOM()
    {
        return $this->DOM;
    }



    public function getModules()
    {
        return $this->modules;
    }



    static private function tabCb($m)
    {
        // tab width = 8
        return $m[1] . str_repeat(' ', 8 - strlen($m[1]) % 8);
    }



    /**
     * experimental
     */
    public function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

} // Texy