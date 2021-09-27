<?php

namespace sij\humhub\modules\rss\components;

/**
 * A collection of useful methods to assist working with Markdown text.
 */
class MarkdownHelper {

    private static $article;
    private static $level;

    public static $logFileHandle = false;
    public static $cfg_pictures = 'yes';
    public static $cfg_maxwidth = 500;
    public static $cfg_maxheight = 500;

/**
 * Makes the supplied text safe to use as Markdown.
 */
    public static function escape($text)
    {
        return str_replace(
            [   '\\',  '-',  '#',  '*',  '+',  '`',  '.',  '[',  ']',  '(',  ')',  '!',  '<',  '>',  '_',  '{',  '}', ],
            [ '\\\\', '\-', '\#', '\*', '\+', '\`', '\.', '\[', '\]', '\(', '\)', '\!', '\<', '\>', '\_', '\{', '\}', ],
            $text
        );
    }

    private static function log($text) {
        if ( self::$logFileHandle ) {
            fwrite(self::$logFileHandle, $text);
        }
    }

/**
 * Translates a HTML document into Markdown syntax
 */
    public static function translateHTML($html) {
        $doc = new \DomDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), 
            LIBXML_NOCDATA );
        self::$article = "";
        self::$level = 0;
        self::translateNode($doc->documentElement);
        self::$article =  preg_replace(
            ['/  +/', '/ +\n/'], 
            [' ',     "\n"    ], 
            self::$article
        );
        return trim(self::$article);
    }

/**
 * Translate the HTML body of the RSS new item into Markdown syntax
 */
    private static function translateNode($node)
    {
        switch ( $node->nodeType ) {
            case XML_TEXT_NODE:
                $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
                self::log("\n### Text: |${text}|");
                self::$article .= self::escape($text);
                break;
            case XML_ELEMENT_NODE:
                self::log("\n### node " . self::$level . " " . strtolower($node->nodeName));
                switch ( strtolower($node->nodeName) ) {
                    case 'h1':
                        $before = "\n# ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h2':
                        $before = "\n## ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h3':
                        $before = "\n### ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h4':
                        $before = "\n#### ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h5':
                        $before = "\n##### ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h6':
                        $before = "\n###### ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'center' :
                    case 'article' :
                    case 'aside' :
                    case 'div' :
                    case 'footer' :
                    case 'header' :
                    case 'main' :
                    case 'p':
                    case 'section' :
                        $before = "\n\n";
                        $after = "\n\n";
                        $enter = true;
                        break;
                    case 'br':
                        $before = "\n";
                        $after = "";
                        $enter = false;
                        break;
                    case 'b':
                    case 'kbd' :
                    case 'mark' :
                    case 'strong' :
                        $before = "**";
                        $after = "**";
                        $enter = true;
                        break;
                    case 'dfn' :
                    case 'em':
                    case 'i':
                    case 'var' :
                        $before = "_";
                        $after = "_";
                        $enter = true;
                        break;
                    case 's':
                    case 'strike':
                    case 'del':
                        $before = "~~";
                        $after = "~~";
                        $enter = true;
                        break;
                    case 'blockquote':
                    case 'q' :
                        $before = "\n> ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'a':
                        $before = " [";
                        $after = "](" . $node->getAttribute('href') . ") ";
                        $enter = true;
                        break;
                    case 'img':
                        if ( self::$cfg_pictures == 'yes' ) {
                            $width = $node->getAttribute('width');
                            $height = $node->getAttribute('height');
                            if ( $width ) {
                                if ( $width > self::$cfg_maxwidth ) {
                                    if ( $height ) {
                                        $height = floor($height * self::$cfg_maxwidth / $width);
                                    }
                                    $width = self::$cfg_maxwidth;
                                }
                            }
                            if ( $height ) {
                                if ( $height > self::$cfg_maxheight ) {
                                    if ( $width ) {
                                        $width = floor($width * self::$cfg_maxheight / $height);
                                    }
                                    $height = self::$cfg_maxheight;
                                }
                            }
                            $size = ' =' . $width . 'x' . $height;
                            if ( $size = ' =x' ) {
                                $size = '';
                            }
                            $before = " ![" . $node->getAttribute('alt') . "]";
                            $after = "(" . $node->getAttribute('src') . $size . ") ";
                        } else {
                            $before = '';
                            $after = '';
                        }
                        $enter = false;
                        break;
                    case 'hr':
                        $before = "\n\n---\n\n";
                        $after = "";
                        $enter = false;
                        break;
                    case 'abbr' :
                    case 'acronym' :
                    case 'address' :
                    case 'big' :
                    case 'body' :
                    case 'cite' :
                    case 'figcaption' :
                    case 'figure' :
                    case 'html' :
                    case 'ins' :
                    case 'small' :
                    case 'span' :
                    case 'sub' :
                    case 'sup' :
                    case 'time' :
                    case 'u' :
                    case 'wbr' :
                        $before = "";
                        $after = "";
                        $enter = true;
                        break;
                    case 'table' :
                        $before = "\n";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'tr' :
                    case 'th' :
                    case 'td' :
                    case 'thead' :
                    case 'tbody' :
                    case 'tfoot' :
                    case 'caption' :
                        $before = " ";
                        $after = " ";
                        $enter = true;
                        break;
                    default :
                        $before = "";
                        $after = "";
                        $enter = false;
                }

                self::log("\nBefore: |${before}|");
                self::log("\nAfter: |${after}|");

                self::$article .= $before;

                if ( $enter ) {
                    self::$level++;
                    self::log("\nEntering... " . self::$level);
                    foreach ( $node->childNodes as $child ) {
                        self::translateNode($child);
                    }
                    self::log("\nExiting... " . self::$level);
                    self::$level--;
                } else {
                    self::log("\nSkipping...");
                }

                self::$article .= $after;
        }
    }

}
