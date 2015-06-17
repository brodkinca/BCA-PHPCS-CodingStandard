<?php
/**
 * Prepares suite for testing.
 *
 * @package    squizlabs/php_codesniffer
 * @subpackage bca/coding-standard
 * @author     Brodkin CyberArts <info@brodkinca.com>
 * @copyright  2015 Brodkin CyberArts
 */

namespace BCA\Sniffs\Strings;

use \PHP_CodeSniffer_File;

/**
 * Check formatting of concatenated strings.
 */
class ConcatenationSpacingSniff extends \Squiz_Sniffs_Strings_ConcatenationSpacingSniff
{

    /**
     * Allow new lines within concatenations.
     * @var boolean
     */
    public $ignoreNewlines = true;
}
