<?php
/**
 * Prepares suite for testing.
 *
 * @package   bca/coding-standard
 * @author    Brodkin CyberArts <info@brodkinca.com>
 * @copyright 2014 Brodkin CyberArts
 */

namespace BCA\Sniffs\Commenting;

use \PHP_CodeSniffer_CommentParser_ClassCommentParser;
use \PHP_CodeSniffer_File;

/**
 * Parses and verifies the class doc comment.
 */
class FileCommentSniff implements \PHP_CodeSniffer_Sniff
{

    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = array(
                                   'PHP',
                                   'JS',
                                  );

    /**
     * The header comment parser for the current file.
     *
     * @var PHP_CodeSniffer_Comment_Parser_ClassCommentParser
     */
    protected $commentParser = null;

    /**
     * The current PHP_CodeSniffer_File object we are processing.
     *
     * @var PHP_CodeSniffer_File
     */
    protected $currentFile = null;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_OPEN_TAG);

    }//end register()

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param integer              $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;

        // We are only interested if this is the first open tag.
        if ($stackPtr !== 0) {
            if ($phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) !== false) {
                return;
            }
        }

        $tokens = $phpcsFile->getTokens();

        $errorToken = ($stackPtr + 1);
        if (isset($tokens[$errorToken]) === false) {
            $errorToken--;
        }

        // Find the next non whitespace token.
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        if ($tokens[$commentStart]['code'] === T_CLOSE_TAG) {
            // We are only interested if this is the first open tag.
            return;
        } elseif ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a file comment', $errorToken, 'WrongStyle');

            return;
        } elseif ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT) {
            $phpcsFile->addError('Missing file doc comment', $errorToken, 'Missing');

            return;
        }

        // Extract the header comment docblock.
        $commentEnd = ($phpcsFile->findNext(T_DOC_COMMENT, ($commentStart + 1), null, true) - 1);

        // Check if there is only 1 doc comment between the open tag and class token.
        $nextToken = array(
            T_ABSTRACT,
            T_CLASS,
            T_DOC_COMMENT,
        );

        $commentNext = $phpcsFile->findNext($nextToken, ($commentEnd + 1));
        if ($commentNext !== false && $tokens[$commentNext]['code'] !== T_DOC_COMMENT) {
            // Found a class token right after comment doc block.
            $newlineToken = $phpcsFile->findNext(
                T_WHITESPACE,
                ($commentEnd + 1),
                $commentNext,
                false,
                $phpcsFile->eolChar
            );
            if ($newlineToken !== false) {
                $newlineToken = $phpcsFile->findNext(
                    T_WHITESPACE,
                    ($newlineToken + 1),
                    $commentNext,
                    false,
                    $phpcsFile->eolChar
                );
                if ($newlineToken === false) {
                    // No blank line between the class token and the doc block.
                    // The doc block is most likely a class comment.
                    $phpcsFile->addError('Missing file doc comment', $errorToken, 'Missing');

                    return;
                }
            }
        }

        // No blank line between the open tag and the file comment.
        $blankLineBefore = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, false, $phpcsFile->eolChar);
        if ($blankLineBefore !== false && $blankLineBefore < $commentStart) {
            $error = 'Extra newline found after the open tag';
            $phpcsFile->addError($error, $stackPtr, 'SpacingAfterOpen');
        }

        // Exactly one blank line after the file comment.
        $nextTokenStart = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), null, true);
        if ($nextTokenStart !== false) {
            $blankLineAfter = 0;
            for ($i = ($commentEnd + 1); $i < $nextTokenStart; $i++) {
                if ($tokens[$i]['code'] === T_WHITESPACE && $tokens[$i]['content'] === $phpcsFile->eolChar) {
                    $blankLineAfter++;
                }
            }

            if ($blankLineAfter !== 2) {
                $error = 'There must be exactly one blank line after the file comment';
                $phpcsFile->addError($error, ($commentEnd + 1), 'SpacingAfterComment');
            }
        }

        $commentString = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

        // Parse the header comment docblock.
        try {
            $this->commentParser = new PHP_CodeSniffer_CommentParser_ClassCommentParser($commentString, $phpcsFile);
            $this->commentParser->parse();
        } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $commentStart);
            $phpcsFile->addError($e->getMessage(), $line, 'Exception');

            return;
        }

        $comment = $this->commentParser->getComment();
        if (is_null($comment) === true) {
            $error = 'File doc comment is empty';
            $phpcsFile->addError($error, $commentStart, 'Empty');

            return;
        }

        // The first line of the comment should just be the /** code.
        $eolPos    = strpos($commentString, $phpcsFile->eolChar);
        $firstLine = substr($commentString, 0, $eolPos);
        if ($firstLine !== '/**') {
            $error = 'The open comment tag must be the only content on the line';
            $phpcsFile->addError($error, $commentStart, 'ContentAfterOpen');
        }

        // No extra newline before short description.
        $short        = $comment->getShortComment();
        $newlineCount = 0;
        $newlineSpan  = strspn($short, $phpcsFile->eolChar);
        if ($short !== '' && $newlineSpan > 0) {
            $error = 'Extra newline(s) found before file comment short description';
            $phpcsFile->addError($error, ($commentStart + 1), 'SpacingBeforeShort');
        }

        $newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

        // Exactly one blank line between short and long description.
        $long = $comment->getLongComment();
        if (empty($long) === false) {
            $between        = $comment->getWhiteSpaceBetween();
            $newlineBetween = substr_count($between, $phpcsFile->eolChar);
            if ($newlineBetween !== 2) {
                $error = 'There must be exactly one blank line between descriptions in file comment';
                $phpcsFile->addError($error, ($commentStart + $newlineCount + 1), 'SpacingBetween');
            }

            $newlineCount += $newlineBetween;

            $testLong = trim($long);
            if (preg_match('|\p{Lu}|u', $testLong[0]) === 0) {
                $error = 'File comment long description must start with a capital letter';
                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'LongNotCapital');
            }
        }//end if

        // Exactly one blank line before tags.
        $tags = $this->commentParser->getTagOrders();
        if (count($tags) > 1) {
            $newlineSpan = $comment->getNewlineAfter();
            if ($newlineSpan !== 2) {
                $error = 'There must be exactly one blank line before the tags in file comment';
                if ($long !== '') {
                    $newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
                }

                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'SpacingBeforeTags');
                $short = rtrim($short, $phpcsFile->eolChar.' ');
            }
        }

        // Short description must be single line and begin with a capital letter.
        $testShort = trim($short);
        if ($testShort === '') {
            $error = 'Missing short description in file comment';
            $phpcsFile->addError($error, ($commentStart + 1), 'MissingShort');
        } else {
            if (substr_count($testShort, $phpcsFile->eolChar) !== 0) {
                $error = 'File comment short description must be on a single line';
                $phpcsFile->addError($error, ($commentStart + 1), 'ShortSingleLine');
            }

            if (preg_match('|\p{Lu}|u', $testShort[0]) === 0) {
                $error = 'File comment short description must start with a capital letter';
                $phpcsFile->addError($error, ($commentStart + 1), 'ShortNotCapital');
            }
        }//end if

        // Check each tag.
        $this->processTags($commentStart, $commentEnd);

        // The last content should be a newline and the content before
        // that should not be blank. If there is more blank space
        // then they have additional blank lines at the end of the comment.
        $words = $this->commentParser->getWords();
        $lastPos = (count($words) - 1);
        if (trim($words[($lastPos - 1)]) !== ''
            || strpos($words[($lastPos - 1)], $this->currentFile->eolChar) === false
            || trim($words[($lastPos - 2)]) === ''
        ) {
            $error = 'Additional blank lines found at end of file comment';
            $this->currentFile->addError($error, $commentEnd, 'SpacingAfter');
        }

    }//end process()

    /**
     * Processes each required or optional tag.
     *
     * @param integer $commentStart The position in the stack where the comment started.
     * @param integer $commentEnd   The position in the stack where the comment ended.
     *
     * @return void
     */
    protected function processTags($commentStart, $commentEnd)
    {
        // Required tags in correct order.
        $tags = array(
            'package'    => 'precedes @author',
            'author'     => 'follows @package',
            'copyright'  => 'follows @author',
        );

        $foundTags = $this->commentParser->getTagOrders();
        $errorPos = 0;
        $orderIndex = 0;
        foreach ($tags as $tag => $orderText) {

            // Required tag missing.
            if (in_array($tag, $foundTags) === false) {
                $error = 'Missing @%s tag in file comment';
                $data  = array($tag);
                $this->currentFile->addError($error, $commentEnd, 'Missing'.ucfirst($tag).'Tag', $data);
                continue;
            }

            // Get the line number for current tag.
            $tagName = ucfirst($tag);
            if ($tagName === 'Author' || $tagName === 'Copyright') {
                // These tags are different because they return an array.
                $tagName .= 's';
            }

            // Work out the line number for this tag.
            $getMethod = 'get'.$tagName;
            $tagElement = $this->commentParser->$getMethod();
            if (is_null($tagElement) === true || empty($tagElement) === true) {
                continue;
            } elseif (is_array($tagElement) === true && empty($tagElement) === false) {
                $tagElement = $tagElement[0];
            }

            $errorPos = ($commentStart + $tagElement->getLine());

            // Make sure there is no duplicate tag.
            $foundIndexes = array_keys($foundTags, $tag);
            if (count($foundIndexes) > 1) {
                $error = 'Only 1 @%s tag is allowed in file comment';
                $data  = array($tag);
                $this->currentFile->addError($error, $errorPos, 'Duplicate'.ucfirst($tag).'Tag', $data);
            }

            // Check tag order.
            if ($foundIndexes[0] > $orderIndex) {
                $orderIndex = $foundIndexes[0];
            } else {
                $error = 'The @%s tag is in the wrong order; the tag %s';
                $data = array(
                    $tag,
                    $orderText,
                );
                $this->currentFile->addError($error, $errorPos, ucfirst($tag).'TagOrder', $data);
            }

            $method = 'process'.$tagName;
            if (method_exists($this, $method) === true) {
                // Process each tag if a method is defined.
                call_user_func(array($this, $method), $errorPos);
            } else {
                $tagElement->process($this->currentFile, $commentStart, 'file');
            }
        }//end foreach

    }//end processTags()

    /**
     * The package name must be Packagist-compatible.
     *
     * @param integer $errorPos The line number where the error occurs.
     *
     * @return void
     */
    protected function processPackage($errorPos)
    {
        $package = $this->commentParser->getPackage();
        if ($package !== null) {
            $content = $package->getContent();
            if (empty($content)) {
                $error = 'Content missing for @package tag in file comment';
                $this->currentFile->addError($error, $errorPos, 'MissingPackage');
            } elseif (!preg_match('/([a-z\-]+)\/([a-z\-]+)/', $content, $match)) {
                $newName = 'bca/'.strtolower(substr($content, strpos($content, '/') + 1));
                $data = array(
                    $content,
                    $newName,
                );

                $error = 'Package name "%s" is not valid; must be a valid Packagist package name.';
                $this->currentFile->addError($error, $errorPos, 'IncorrectPackage', $data);
            } elseif ($match[1] !== 'bca') {
                $newName = 'bca/'.strtolower(substr($content, strpos($content, '/') + 1));
                $data = array(
                    $content,
                    $newName,
                );

                $error = 'Package name "%s" does not begin with "bca/"; consider "%s" instead';
                $this->currentFile->addError($error, $errorPos, 'BCAPackage', $data);
            }
        }
    }

    /**
     * Author tag must be 'Brodkin CyberArts <info@brodkinca.com>'.
     *
     * @param integer $errorPos The line number where the error occurs.
     *
     * @return void
     */
    protected function processAuthors($errorPos)
    {
        $authors = $this->commentParser->getAuthors();
        if (empty($authors) === false) {
            $author  = $authors[0];
            $content = $author->getContent();
            if (empty($content) === true) {
                $error = 'Content missing for @author tag in file comment';
                $this->currentFile->addError($error, $errorPos, 'MissingAuthor');
            } elseif ($content !== 'Brodkin CyberArts <info@brodkinca.com>') {
                $error = 'Expected "Brodkin CyberArts <info@brodkinca.com>" for author tag';
                $this->currentFile->addError($error, $errorPos, 'IncorrectAuthor');
            }
        }
    }

    /**
     * Copyright tag must be in the form '2006-YYYY Squiz Pty Ltd (ABN 77 084 670 600)'.
     *
     * @param integer $errorPos The line number where the error occurs.
     *
     * @return void
     */
    protected function processCopyrights($errorPos)
    {
        $copyrights = $this->commentParser->getCopyrights();
        $copyright  = $copyrights[0];

        if ($copyright !== null) {
            $content = $copyright->getContent();
            if (empty($content) === true) {
                $error = 'Content missing for @copyright tag in file comment';
                $this->currentFile->addError($error, $errorPos, 'MissingCopyright');

            } elseif (preg_match('/^([0-9]{4})(-[0-9]{4})? (Brodkin CyberArts)$/', $content) === 0) {
                $error = 'Expected "xxxx[-xxxx] Brodkin CyberArts" for copyright declaration';
                $this->currentFile->addError($error, $errorPos, 'IncorrectCopyright');
            }
        }
    }
}
