<?php

namespace sij\humhub\modules\rss\components;

/**
 * A collection of useful methods to assist working with Markdown text.
 */
class MarkdownHelper {

    private static $article;
    public static $fh = false;

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
        if ( MarkdownHelper::$fh ) {
            fwrite(MarkdownHelper::$fh, $text);
        }
    }

/**
 * Translates a HTML document into Markdown syntax
 */
    public static function translateHTML($html) {
        $doc = new \DomDocument();
        $doc->loadHTML($html,
            LIBXML_HTML_NOIMPLIED | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOEMPTYTAG
        );
        MarkdownHelper::$article = "";
        MarkdownHelper::translateNode($doc->documentElement);
        MarkdownHelper::$article =  preg_replace(
            ['/  +/', '/ +\n/'], 
            [' ',     "\n"    ], 
            MarkdownHelper::$article
        );
        return trim(MarkdownHelper::$article);
    }

/**
 * Translate the HTML body of the RSS new item into Markdown syntax
 */
    private static function translateNode($node)
    {
        switch ( $node->nodeType ) {
            case XML_TEXT_NODE:
                $text = preg_replace('/\s+/', ' ', $node->textContent);
                MarkdownHelper::log("\n\n### Text: |${text}|");
                MarkdownHelper::$article .= MarkdownHelper::escape($text);
                break;
            case XML_ELEMENT_NODE:
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
                        $before = " **";
                        $after = "** ";
                        $enter = true;
                        break;
                    case 'dfn' :
                    case 'em':
                    case 'i':
                    case 'var' :
                        $before = " *";
                        $after = "* ";
                        $enter = true;
                        break;
                    case 's':
                    case 'strike':
                    case 'del':
                        $before = " ~~";
                        $after = "~~ ";
                        $enter = true;
                        break;
                    case 'blockquote':
                    case 'q' :
                        $before = "\n> ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'a':
                        $before = "[";
                        $after = "](" . $node->getAttribute('href') . ")";
                        $enter = true;
                        break;
                    case 'img':
                        $size =
                            $node->getAttribute('width') .
                            "x" .
                            $node->getAttribute('height');
                        $before = "![" . $node->getAttribute('alt') . "]";
                        $after = "(" . $node->getAttribute('src');
                        if ($size != 'x') {
                            $after .= " =${size}";
                        }
                        $after .= ")";
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
                    default:
                        $before = "";
                        $after = "";
                        $enter = false;
                }

                MarkdownHelper::log("\n\n### " . strtolower($node->nodeName));
                MarkdownHelper::log("\nBefore: |${before}|");
                MarkdownHelper::log("\nAfter: |${after}|");
                if ( $enter ) {
                    MarkdownHelper::log("\nEntering...");
                } else {
                    MarkdownHelper::log("\nSkipping...");
                }

                MarkdownHelper::$article .= $before;
                if ( $enter ) {
                    foreach ( $node->childNodes as $child ) {
                        MarkdownHelper::translateNode($child);
                    }
                }
                MarkdownHelper::$article .= $after;
        }
    }

}
