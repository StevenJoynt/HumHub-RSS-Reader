<?php

namespace sij\humhub\modules\rss\jobs;

use Yii;

use humhub\modules\queue\ActiveJob;
use humhub\modules\post\models\Post;
use humhub\modules\content\models\Content;

use sij\humhub\modules\rss\components\MarkdownHelper;
use sij\humhub\modules\rss\components\RssElement;

/**
 * Reads the RSS Feed URL for this space
 * and adds any new posts into the stream.
 */
class GetFeedUpdates extends ActiveJob
{

    public $space; # humhub\modules\space\models\Space

    private $log; # filehandle for log file

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
    private $now; # DateTime: the current time
    private $items; # array of sij\humhub\modules\rss\components\RssElement keyed by pubDate

    private function log($message) {
        fwrite($this->log, $message);
        fflush($this->log);
    }

/**
 * Creates a new Post in the Space
 */
    private function postError($title, $info)
    {

        $post = new Post($this->space);

        $post->created_by =
        $post->updated_by =
        $post->content->created_by =
        $post->content->updated_by =
            $this->created_by;

        $post->autoFollow = false;

        $post->silentContentCreation = false;

        $post->message =
            "# RSS Error\n" .
            "## " . MarkdownHelper::escape($title) . "\n" .
            "${info}\n" ;

        $post->save();

    }

/**
 * Creates a new Post in the Space
 */
    private function postMessage($message, $datePublished = false)
    {

        $post = null;

        // attempt to locate a previous version of the post
        if ( $datePublished ) {
            $oldContent = Content::findAll([
                'contentcontainer_id' => $this->space->contentcontainer_id,
                'created_by' => $this->created_by,
                'created_at' => $datePublished->format("Y-m-d H:i:s"),
            ]);
            if ( count($oldContent) == 1 ) {
                $post = new Post($oldContent[0]->object_id);
                $this->log("\n\n### update Post");
            }
        }

        // if no previous version of the post, create a new one
        if ( $post === null ) {
            $post = new Post($this->space); 
            $this->log("\n\n### new Post");
        }

        $post->created_by =
        $post->updated_by =
        $post->content->created_by =
        $post->content->updated_by =
            $this->created_by;

        $post->autoFollow = false;

        $post->silentContentCreation = true;

        $post->message = $message;

        $post->save();

        if ( $datePublished ) {
            $stamp = $datePublished->format("Y-m-d H:i:s");
            Yii::$app->db
                ->createCommand('update post set created_at=:created_at, updated_at=:updated_at where id=:id')
                ->bindValue('created_at', $stamp)
                ->bindValue('updated_at', $stamp)
                ->bindValue('id', $post->id)
                ->query();
            Yii::$app->db
                ->createCommand('update content set created_at=:created_at, updated_at=:updated_at, stream_sort_date=:stream_sort_date where id=:id')
                ->bindValue('created_at', $stamp)
                ->bindValue('updated_at', $stamp)
                ->bindValue('stream_sort_date', $stamp)
                ->bindValue('id', $post->content->id)
                ->query();
        }

    }

    /**
     * Process a single item from the RSS news feed
     */
    private function parseNewsItem($item)
    {

        $this->log("\n\n### parseNewsItem\n" . print_r($item, true));

        // extract the information about this item
        $title = $item->text('title');
        $link = $item->text('link');
        $description = $item->text('description');
        $content = $item->nstext('content', 'encoded');
        $pubDate = $item->text('pubDate'); # eg Wed, 14 Apr 2021 00:00:00 GMT
        $image = $item->text('image');

        // decode the date of publication, if known
        if ( $pubDate ) {
            $datePublished = \DateTime::createFromFormat("D, j M Y H:i:s T", $pubDate);
        } else {
            $datePublished = false;
        }

        // check if published date is known and in the future
        if ( $datePublished ) {
            if ( $datePublished > $this->now ) {
                return; // don't publish it here if it's not time yet
            }
        }

        // choose which version of the article to use
        // use $description if we want to show a quick summary
        // use $content if we are showing the full article
        // if only one of them is supplied, use that
        // TODO for now we will favour the full article - we need to make this configurable
        if ( $content == '' ) {
            $content = $description;
        }

        // parse the body of the item as a HTML document
        $article = MarkdownHelper::translateHTML($content);

        // start building the message to post in the stream
        $message = '';

        // create a post heading using the rss title and maybe the link
        if ( $title ) {
            if ( $link ) {
                $message .= '# [' . MarkdownHelper::escape($title) . ']';
                $message .= '(' . MarkdownHelper::escape($link) . ')' . "\n";
            } else {
                $message .= '# ' . MarkdownHelper::escape($title) . "\n";
            }
        }

        // add the publication date to the post message, if supplied
        if ( $pubDate ) {
            $message .= '## ' . MarkdownHelper::escape($pubDate) . "\n";
        }

        // add the picture to the post message, if supplied
        if ( $image ) {
            $message .= '![<](' . $image . ' =200x)';
        }

        // add the main body of the rss news item to this stream post
        $message .= $article;

        // add a horizontal line to the post to separate it from the footer
        $message .= "\n\n---\n\n";

        // add the title of the rss channel to the footer of the stream post
        if ( $this->feed_title ) {
            if ( $this->feed_link ) {
                $message .= '### [' . MarkdownHelper::escape($this->feed_title) . ']';
                $message .= '(' . MarkdownHelper::escape($this->feed_link) . ')' . "\n";
            } else {
                $message .= '### ' . MarkdownHelper::escape($this->feed_title) . "\n";
            }
        }

        // add the description of the rss channel to the footer of the stream post
        if ( $this->feed_description ) {
            $message .= MarkdownHelper::escape($this->feed_description) . "\n";
        }

        // add the copyright info of the rss channel to the footer of the stream post
        if ( $this->feed_copyright ) {
            $message .= MarkdownHelper::escape($this->feed_copyright);
        }

        // post the message in the stream
        $this->postMessage($message, $datePublished);

    }

/**
 * Examines each RSS news item.
 * Extracts the pubDate so they can be sorted into the correct order.
 */
    public function examineNewsItem($item) {

        $pubDate = $item->text('pubDate'); # eg Wed, 14 Apr 2021 00:00:00 GMT

        if ( $pubDate ) {
            $datePublished = \DateTime::createFromFormat("D, j M Y H:i:s T", $pubDate);
        } else {
            $datePublished = false;
        }

        if ( $datePublished ) {
            $stamp = $datePublished->format("Y-m-d H:i:s");
            $this->items[$stamp] = $item;
        } else {
            $this->items[] = $item;
        }

    }

/**
 * Process a single channel from the RSS news feed
 */
    public function parseNewsChannel($channel)
    {

        // extract the info about this channel
        $this->feed_title = $channel->text('title');
        $this->feed_link = $channel->text('link');
        $this->feed_description = $channel->text('description');
        $this->feed_copyright = $channel->text('copyright');

        // examine each item in the current channel of the RSS feed
        $this->items = [];
        $channel->each('item', [$this, 'examineNewsItem']);

        // sort news items into date order
        ksort($this->items);

        // parse each item in the current channel of the RSS feed
        foreach ( $this->items as $item ) {
            $this->parseNewsItem($item);
        }

    }

/**
 * Process the downloaded RSS new feed
 */
    private function parseNewsFeed()
    {

        // read the RSS file into memory
        libxml_use_internal_errors(true);
        $this->feed = @simplexml_load_file(
            $this->rss_file,
            RssElement::class,
            LIBXML_NOCDATA
        );

        // report error if we can't parse the xml rss feed
        if ( $this->feed === false ) {
            $message =
                MarkdownHelper::escape($this->rss_file) .
                "\n\n" .
                "| Line | Col | Message |\n" .
                "| ---: | ---: | :--- |\n";
            foreach ( libxml_get_errors() as $error ) {
                $message .=
                    "| " .
                    $error->line .
                    " | " .
                    $error->column .
                    " | " .
                    MarkdownHelper::escape($error->message) .
                    " |\n";
            }
            $this->postError(
                "Failed to parse RSS data",
                $message
            );
            return;
        }

        $this->log("\n\n### parseNewsFeed\n" . print_r($this->feed, true));

        // parse each channel in the RSS feed
        $this->feed->each('channel', [$this, 'parseNewsChannel']);

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
                $this->postError(
                    "Failed to create cache folder",
                    MarkdownHelper::escape($this->rss_folder)
                );
                return;
            }
        }
        @chmod($this->rss_folder, 0755);

        // create a new file to hold the updated RSS data
        $fh = fopen($this->new_file, 'w');
        if ( ! $fh ) {
            $this->postError(
                "Failed to create cache file",
                MarkdownHelper::escape($this->new_file)
            );
            return;
        }

        // use cURL to download the latest RSS data
        $ch = curl_init($this->rss_url);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        /* if ( @is_file($this->rss_file) ) {
            // only download if newer than what we already have in the cache
            curl_setopt($ch, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
            curl_setopt($ch, CURLOPT_TIMEVALUE, filemtime($this->rss_file));
        } */
        curl_exec($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        fclose($fh);

        if ( $error ) {
            // curl failed for some reason
            $this->postError(
                "Failed to read feed from URL",
                MarkdownHelper::escape($this->rss_url) .
                "\n" .
                MarkdownHelper::escape($error) .
                "\n"
            );
            return;
        }

        if ( $code == 200 ) {
            // feed has been updated
            if ( @is_file($this->rss_file) ) {
                @unlink($this->rss_file);
            }
            @rename($this->new_file, $this->rss_file);
            $this->parseNewsFeed(); // examine the updated RSS feed data
            return; // nothing more to do
        }

        // delete temporary file
        @unlink($this->new_file);

        if ( $code == 304 ) {
            // feed is unchanged - don't report this as an error
            return;
        }

        // report error
        $this->postError(
            "Failed to read URL",
            MarkdownHelper::escape($this->rss_url) . "\n" .
            "HTTP response code = ${code}"
        );

    }

/**
 * Main entry point for this cron job.
 */
    public function run()
    {

        $this->log = fopen(dirname(__FILE__) . '/log.txt', 'w');

        $this->rss_url = $this->space->getSetting('url', 'rss');
        $this->created_by = $this->space->created_by;

        $this->rss_folder = Yii::getAlias('@runtime/rss');
        $this->rss_file = $this->rss_folder . '/' . $this->space->guid . '.xml';
        $this->new_file = $this->rss_folder . '/' . $this->space->guid . '.new';

        $this->now = new \DateTime("now");

        $this->log("\n\n### run at " . print_r($this->now, true));

        $this->downloadNewsFeed();

        fclose($this->log);

    }
}
