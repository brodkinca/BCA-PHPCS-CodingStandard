<?php
/**
 * Prepares suite for testing.
 *
 * @package    squizlabs/php_codesniffer
 * @subpackage bca/coding-standard
 * @author     Brodkin CyberArts <info@brodkinca.com>
 * @copyright  2015 Brodkin CyberArts
 */

namespace BCA\Sniffs\Commenting;

use \PHP_CodeSniffer_File;

/**
 * Check file and class comments.
 */
class FileCommentSniff implements \PHP_CodeSniffer_Sniff
{

    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = [
     'PHP',
     'JS',
    ];


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_OPEN_TAG];

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param integer              $stackPtr  The position of the current token
     *                                    in the stack passed in $tokens.
     *
     * @return integer
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;

        $tokens       = $phpcsFile->getTokens();
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        if ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a file comment', $commentStart, 'WrongStyle');
            return ($phpcsFile->numTokens + 1);
        } else if ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            return ($phpcsFile->numTokens + 1);
        }

        $commentEnd = $tokens[$commentStart]['comment_closer'];

        // No blank line between the open tag and the file comment.
        if ($tokens[$commentStart]['line'] > ($tokens[$stackPtr]['line'] + 1)) {
            $error = 'There must be no blank lines before the file comment';
            $phpcsFile->addError($error, $stackPtr, 'SpacingAfterOpen');
        }

        // Exactly one blank line after the file comment.
        $next = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), null, true);
        if ($tokens[$next]['line'] !== ($tokens[$commentEnd]['line'] + 2)) {
            $error = 'There must be exactly one blank line after the file comment';
            $phpcsFile->addError($error, $commentEnd, 'SpacingAfterComment');
        }

        // Required tags in correct order.
        $required = [
         '@package'    => true,
         '@subpackage' => false,
         '@author'     => true,
         '@copyright'  => true,
        ];

        $foundTags = [];
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            $name       = $tokens[$tag]['content'];
            $isRequired = isset($required[$name]);

            if ($isRequired === true && in_array($name, $foundTags) === true) {
                $error = 'Only one %s tag is allowed in a file comment';
                $data  = [$name];
                $phpcsFile->addError($error, $tag, 'Duplicate'.ucfirst(substr($name, 1)).'Tag', $data);
            }

            $foundTags[] = $name;

            if ($isRequired === false) {
                continue;
            }

            $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
            if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                $error = 'Content missing for %s tag in file comment';
                $data  = [$name];
                $phpcsFile->addError($error, $tag, 'Empty'.ucfirst(substr($name, 1)).'Tag', $data);
                continue;
            }

            if ($name === '@author') {
                if ($tokens[$string]['content'] !== 'Brodkin CyberArts <info@brodkinca.com>') {
                    $error = 'Expected "Brodkin CyberArts <info@brodkinca.com>" for author tag';
                    $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectAuthor');
                    if ($fix === true) {
                        $expected = 'Brodkin CyberArts <info@brodkinca.com>';
                        $phpcsFile->fixer->replaceToken($string, $expected);
                    }
                }
            } else if ($name === '@copyright') {
                if (preg_match('/^([0-9]{4})(-[0-9]{4})? (Brodkin CyberArts)$/', $tokens[$string]['content']) === 0) {
                    $error = 'Expected "xxxx[-xxxx] Brodkin CyberArts" for copyright declaration';
                    $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectCopyright');
                    if ($fix === true) {
                        $matches = [];
                        preg_match('/^(([0-9]{4})(-[0-9]{4})?)?.*$/', $tokens[$string]['content'], $matches);
                        if (isset($matches[1]) === false) {
                            $matches[1] = date('Y');
                        }

                        $expected = $matches[1].' Brodkin CyberArts';
                        $phpcsFile->fixer->replaceToken($string, $expected);
                    }
                }
            }//end if
        }//end foreach

        // Check if the tags are in the correct position.
        $pos = 0;
        foreach ($required as $tag => $true) {
            if (in_array($tag, $foundTags) === false) {
                $error = 'Missing %s tag in file comment';
                $data  = [$tag];
                $phpcsFile->addError($error, $commentEnd, 'Missing'.ucfirst(substr($tag, 1)).'Tag', $data);
            }

            if (isset($foundTags[$pos]) === false) {
                break;
            }

            if ($foundTags[$pos] !== $tag) {
                $error = 'The tag in position %s should be the %s tag';
                $data  = [
                  ($pos + 1),
                  $tag,
                 ];
                $phpcsFile->addError($error, $tokens[$commentStart]['comment_tags'][$pos], ucfirst(substr($tag, 1)).'TagOrder', $data);
            }

            $pos++;
        }//end foreach

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);

    }//end process()
}//end class
