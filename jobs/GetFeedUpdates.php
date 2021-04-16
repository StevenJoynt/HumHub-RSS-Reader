<?php

namespace sij\humhub\modules\rss\jobs;

use Yii;
use humhub\modules\queue\ActiveJob;
use humhub\modules\post\models\Post;
use sij\humhub\modules\rss\components\RssElement;

/**
 * Reads the RSS Feed URL for this space
 * and add any new posts into the stream.
 */
class GetFeedUpdates extends ActiveJob
{

    public $space; # humhub\modules\space\models\Space

    private $rss_url; # string: the URL of the RSS feed
    private $created_by; # int: user id number
    private $rss_folder; # string: full name of the folder to store the RSS feed
    private $rss_file; # string: full name of the current RSS feed file
    private $new_file; # string: full name of temporary file to receive the latest RSS feed data

    private $feed; # sij\humhub\modules\rss\components\RssElement
    private $feed_title; # string: the title of this RSS feed
    private $feed_link; # string: the URL to link to the web site where the RSS feed is from
    private $feed_description; # string: description of this RSS feed
    private $feed_copyright; # string: copyright information for this RSS feed
    private $article; # string: markdown document representing HTML feed item body

/**
 * Creates a new Post in the Space
 */
    private function postMessage($message)
    {
        $post = new Post($this->space);
        $post->created_by = $this->created_by;
        $post->updated_by = $this->created_by;
        $post->content->created_by = $this->created_by;
        $post->content->updated_by = $this->created_by;
        $post->autoFollow = false;
        $post->silentContentCreation = true;
        $post->message = $message;
        $post->save();
    }

/**
 * Makes the supplied text safe to use as Markdown.
 */
    private function safeMarkdown($text)
    {
        return str_replace(
            [   '\\',  '-',  '#',  '*',  '+',  '`',  '.',  '[',  ']',  '(',  ')',  '!',  '<',  '>',  '_',  '{',  '}', ],
            [ '\\\\', '\-', '\#', '\*', '\+', '\`', '\.', '\[', '\]', '\(', '\)', '\!', '\<', '\>', '\_', '\{', '\}', ], 
            $text
        );
    }

/**
 * Translate the HTML body of the RSS new item into Markdown syntax
 */
    private function translateNode($node) {
        switch ( $node->nodeType ) {
            case XML_TEXT_NODE :
                $this->article .= $this->safeMarkdown($node->textContent);
                break;
            case XML_ELEMENT_NODE :
                switch ( $node->nodeName ) {
                    case 'h1' :
                        $before = "\n#";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h2' :
                        $before = "\n##";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h3' :
                        $before = "\n###";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h4' :
                        $before = "\n####";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h5' :
                        $before = "\n#####";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'h6' :
                        $before = "\n######";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'p' :
                        $before = "\n\n";
                        $after = "\n\n";
                        $enter = true;
                        break;
                    case 'br' :
                        $before = "\n";
                        $after = "";
                        $enter = false;
                        break;
                    case 'b' :
                        $before = " **";
                        $after = "** ";
                        $enter = true;
                        break;
                    case 'em' :
                    case 'i' :
                        $before = " *";
                        $after = "* ";
                        $enter = true;
                        break;
                    case 's' :
                    case 'strike' :
                    case 'del' :
                        $before = " ~~";
                        $after = "~~ ";
                        $enter = true;
                        break;
                    case 'blockquote' :
                        $before = "\n> ";
                        $after = "\n";
                        $enter = true;
                        break;
                    case 'a' :
                        # [title](https://www.example.com)
                        $before = "[";
                        $after = "](" . $node->getAttribute('href') . ")";
                        $enter = true;
                        break;
                    case 'img' :
                        $before = "![" . $node->getAttribute('alt') . "]";
                        $after = "(" . $node->getAttribute('src');
                        $size = $node->getAttribute('width') . "x" . $node->getAttribute('height');
                        if ( $size != 'x' ) $after .= " =${size}";
                        $after .= ")";
                        $enter = false;
                        break;
                    case 'hr' :
                        $before = "\n\n---\n\n";
                        $after = "";
                        $enter = false;
                        break;
                    default :
                        $before = "";
                        $after = "";
                        $enter = false;
                }
                $this->article .= $before;
                if ( $enter ) {
                    foreach ( $node->childNodes as $child ) {
                        $this->translateNode($child);
                    }
                }
                $this->article .= $after;
                break;
        }
    }

/**
 * Process a single item from the RSS news feed
 */
    private function parseNewsItem($item) {

        // extract the information about this item
        $title = $item->text('title');
        $link = $item->text('link');
        $description = $item->text('description');
        $pubDate = $item->text('pubDate'); # eg Wed, 14 Apr 2021 00:00:00 GMT
        $image = $item->text('image');

        // parse the body of the item as a HTML document
        $doc = new \DomDocument();
        $doc->loadHTML($description,
            LIBXML_HTML_NOIMPLIED | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOEMPTYTAG
        );
        $this->article = '';
        $this->translateNode($doc->documentElement);

        // start building the message to post in the stream
        $message = '';

        // create a post heading using the rss title and maybe the link
        if ( $title ) {
            if ( $link ) {
                $message .= '# [' . $this->safeMarkdown($title) . ']';
                $message .= '(' . $this->safeMarkdown($link) . ')' . "\n";
            } else {
                $message .= '# ' . $this->safeMarkdown($title) . "\n";
            }
        }

        // add the publication date to the post message, if supplied
        if ( $pubDate ) {
            $message .= '## ' . $this->safeMarkdown($pubDate) . "\n";
        }

        // add the picture to the post message, if supplied
        if ( $image ) {
            $message.= '![<](' . $image . ' =200x)';
        }

        // add the main body of the rss news item to this stream post
        $message .= trim($this->article);

        // add a horizontal line to the post to separate it from the footer
        $message .= "\n\n---\n\n";

        // add the title of the rss channel to the footer of the stream post
        if ( $this->feed_title ) {
            if ( $this->feed_link ) {
                $message .= '### [' . $this->safeMarkdown($this->feed_title) . ']';
                $message .= '(' . $this->safeMarkdown($this->feed_link) . ')' . "\n";
            } else {
                $message .= '### ' . $this->safeMarkdown($this->feed_title) . "\n";
            }
        }

        // add the description of the rss channel to the footer of the stream post
        if ( $this->feed_description ) {
            $message .= $this->safeMarkdown($this->feed_description) . "\n";
        }

        // add the copyright info of the rss channel to the footer of the stream post
        if ( $this->feed_copyright ) {
            $message .= $this->safeMarkdown($this->feed_copyright);
        }

        // post the message in the stream
        $this->postMessage($message);

    }

/**
 * Process a single channel from the RSS news feed
 */
    private function parseNewsChannel($channel) {

        // extract the info about this channel
        $this->feed_title = $channel->text('title');
        $this->feed_link = $channel->text('link');
        $this->feed_description = $channel->text('description');
        $this->feed_copyright = $channel->text('copyright');

        // parse each item in the current channel of the RSS feed
        if ( is_array($channel->item) ) {
            foreach ( $channel->item as $item ) {
                $this->parseNewsItem(item);
                return; ###################################################### while debugging
            }
        } else {
            $this->parseNewsItem($channel->item);
        }

    }

/**
 * Process the downloaded RSS new feed
 */
    private function parseNewsFeed()
    {

        // read the RSS file into memory
        libxml_use_internal_errors(true);
        $this->feed = @simplexml_load_file($this->rss_file, RssElement::class, LIBXML_NOCDATA);
        if ( $this->feed === false ) {
            $message =
                "# RSS Error\n" .
                "Failed to parse RSS data\n" .
                $this->safeMarkdown($this->rss_file) . "\n\n" .
                "| Line | Col | Message |\n" .
                "| ---: | ---: | :--- |\n";
            foreach ( libxml_get_errors() as $error ) {
                $message .=
                    "| " . $error->line . " | " . $error->column . " | " .
                    $this->safeMarkdown($error->message) . " |\n" ;
            }
            $this->postMessage($message);
            return;
        }

        // parse each channel in the RSS feed
        if ( is_array($this->feed->channel) ) {
            foreach ( $this->feed->channel as $channel ) {
                $this->parseNewsChannel($channel);
            }
        } else {
            $this->parseNewsChannel($this->feed->channel);
        }

    }

/**
 * Attempt to download the RSS feed from the specified URL
 * into a local cache file.
 * Only download a new file if the old copy is out of date.
 */
    private function downloadNewsFeed()
    {

        // ensure the cache folder exists and has the correct permissions
        if ( ! @is_dir($this->rss_folder) ) {
            if ( ! @mkdir($this->rss_folder, 0755, true) ) {
                $this->postMessage(
                    "# RSS Error\n" .
                    "Failed to create cache folder\n" .
                    $this->safeMarkdown($this->rss_folder) . "\n"
                );
                return;
            }
        }
        @chmod($this->rss_folder, 0755);

        // create a new file to hold the updated RSS data
        $fh = fopen($this->new_file, 'w');
        if ( ! $fh ) {
            $this->postMessage(
                "# RSS Error\n" .
                "Failed to create cache file\n" .
                $this->safeMarkdown($this->new_file) . "\n"
            );
            return;
        }

        // use cURL to download the latest RSS data
        $ch = curl_init($this->rss_url);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        if ( @is_file($this->rss_file) ) {
            // only download if newer than what we already have in the cache
            curl_setopt($ch, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
            curl_setopt($ch, CURLOPT_TIMEVALUE, filemtime($this->rss_file));
        }
        curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        fclose($fh);

        if ( $error ) { // curl failed for some reason
            $this->postMessage(
                    "# RSS Error\n" .
                    "Failed to read " . $this->safeMarkdown($this->rss_url) . "\n" .
                    $this->safeMarkdown($error) . "\n"
            );
            return;
        }

        if ( $code == 200 ) { // feed has been updated
            if ( @is_file($this->rss_file) ) {
                @unlink($this->rss_file);
            }
            @rename($this->new_file, $this->rss_file);
            $this->parseNewsFeed(); // examine the updated RSS feed data
            return; // nothing more to do
        }

        // delete temporary file
        @unlink($this->new_file);

        if ( $code == 304 ) { // feed is unchanged - don't report this as an error
            return;
        }

        // report error
        $this->postMessage(
            "# RSS Error\n" .
            "Failed to read " . $this->safeMarkdown($this->rss_url) . "\n" .
            "HTTP response code = ${code}\n"
        );

    }

/**
 * Main entry point for this cron job.
 */
    public function run()
    {

        $this->rss_url = $this->space->getSetting('url', 'rss');
        $this->created_by = $this->space->created_by;

        $this->rss_folder = Yii::getAlias('@runtime/rss');
        $this->rss_file = $this->rss_folder . '/' . $this->space->guid . '.xml';
        $this->new_file = $this->rss_folder . '/' . $this->space->guid . '.new';

        $this->downloadNewsFeed();

    }

}
## todo
# just use one xml library
# move html to markdown code to a separate class so it can be reused

# catch and report errors in xml parsing
# ensure all html is translated to markdown - all tags and attrs
# ensure all markdown parameters are escaped properly
