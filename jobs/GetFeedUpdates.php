<?php

namespace sij\humhub\modules\rss\jobs;

use Yii;
use yii\helpers\Console;

use humhub\modules\queue\ActiveJob;
use humhub\modules\post\models\Post;
use humhub\modules\content\models\Content;

use sij\humhub\modules\rss\components\MarkdownHelper;
use sij\humhub\modules\rss\components\RssElement;
use sij\humhub\modules\rss\controllers\RssController;
use sij\humhub\modules\rss\models\RssPosts;

/**
 * Reads the RSS Feed URL for this space
 * and adds any new posts into the stream.
 */
class GetFeedUpdates extends ActiveJob
{

    public $space; # humhub\modules\space\models\Space
    public $force; # when true, ignore any cached values (used when config changed)

    private $logFileHandle;

    # configuration settings
    private $cfg_url; # the URL of the RSS feed
    private $cfg_article; # 'summary' or 'full'
    private $cfg_pictures; # 'yes' or 'no'
    private $cfg_maxwidth; # maximum width of pictures
    private $cfg_maxheight; # maximum height of pictures
    private $cfg_owner; # user id of the owner of the posts
    private $cfg_dayshistory; # maximum number of days history
    private $cfg_daysfuture; # maximum number of days into the future

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
    private $oldest; # oldest date we are accepting
    private $newest; # newest date we are accepting
    private $items; # array of sij\humhub\modules\rss\components\RssElement keyed by pubDate

    /**
     * @var string mutex to acquire
     */
    const MUTEX_ID = 'rss-queue';

    private function log($message) {
        if ( $this->logFileHandle ) {
            fwrite($this->logFileHandle, $message);
            fflush($this->logFileHandle);
        }
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
    private function postMessage($message, $link = false, $datePublished = false)
    {
        $post = null;

        // find previous version of the post via db
        if ( $link ) {
            $url2id = RssPosts::findOne(['rss_link' => $link]);
            if ( $url2id !== null )
                $post = Post::findOne($url2id->post_id);
        }

        // attempt to locate a previous version of the post
        // guess this should go some day - themroc
        if ( $post === null && $datePublished ) {
            $stamp = $datePublished->format("Y-m-d H:i:s");
            $oldContent = Content::findAll([
                'contentcontainer_id' => $this->space->contentcontainer_id,
//              'created_by' => $this->created_by, // cant rely on this field if config changed
                'created_at' => $stamp,
            ]);
            if ( count($oldContent) == 1 ) {
                $post = Post::findOne($oldContent[0]->object_id);
            }
        }

        // if no previous version of the post, create a new one
        if ( $post === null ) {
            $post = new Post($this->space);
            $this->log("\n\n### new Post\n");
            Console::stdout("RSS queue: creating new post... ");
        } else {
            if ( $datePublished ) {
                if ($stamp > $post->created_at) {
                    $this->log("\n\n### update Post\n");
                    Console::stdout("RSS queue: updating post... ");
                } else {
                    return; // not changed
                }
            } else {
                if ( $post !== null ) {
                    return; // we assume it hasn't changed - better miss an update than rewrite the post every time.
                }
            }
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
        $this->log(print_r($post, true));

        if (! $url2id ) {
            $url2id = new RssPosts();
            $url2id->rss_link = $link;
            $url2id->post_id = $post->id;
            $url2id->save();
        }

        // make it look like the space post was created at the same time as the RSS article
        // note $post->save() always sets the time stamps to "now"
        if ( $datePublished ) {
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

        Console::stdout(Console::renderColoredString("%gdone.%n\n", 1));
    }

    /**
     * Process the 'enclosure' element of a podcast item within an RSS feed
     */
    public function parseEnclosure($enclosure) {
        $this->log("\n\n### parseEnclosure\n" . print_r($enclosure, true));
        $url = $enclosure->attr('url');
        if ( $url ) {
            $this->article .= "\n[:arrow_forward:](" . $url . ")\n";
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

        // if the published date is known, skip the item if it is out of range
        if ( $datePublished ) {
            if ( $datePublished < $this->oldest ) return;
            if ( $datePublished > $this->newest ) return;
        }

        // choose which version of the article to use
        // use $description if we want to show a quick summary
        // use $content if we are showing the full article
        // if only one of them is supplied, use that
        if ( $content == '' || $this->cfg_article == 'summary' ) {
            $content = $description;
        }

        // parse the body of the item as a HTML document
        $this->article = MarkdownHelper::translateHTML($content);

        // append the podcast audio file to the article if present
        $item->each('enclosure', [$this, 'parseEnclosure']);

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
            $message .= '![](' . $image . ')' . "\n";
        }

        // add the main body of the rss news item to this stream post
        $message .= $this->article;

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
        $this->postMessage($message, $link, $datePublished);

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
        $cacheFileHandle = fopen($this->new_file, 'w');
        if ( ! $cacheFileHandle ) {
            $this->postError(
                "Failed to create cache file",
                MarkdownHelper::escape($this->new_file)
            );
            return;
        }

        // use cURL to download the latest RSS data
        $ch = curl_init($this->cfg_url);
        curl_setopt($ch, CURLOPT_FILE, $cacheFileHandle);
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

        fclose($cacheFileHandle);

        if ( $error ) {
            // curl failed for some reason
            $this->postError(
                "Failed to read feed from URL",
                MarkdownHelper::escape($this->cfg_url) .
                "\n" .
                MarkdownHelper::escape($error) .
                "\n"
            );
            return;
        }

        if ( $code == 304 ) {
            // feed is unchanged
            @unlink($this->new_file);
            if ( $this->force ) {
                $this->log("\nRe-examine the old RSS feed data\n");
                $this->parseNewsFeed();
            } else {
                $this->log("\nFeed is unchanged\n");
            }
            return;
        }

        if ( $code == 200 ) {
            // feed has been updated
            if ( @is_file($this->rss_file) ) {
                @unlink($this->rss_file);
            }
            @rename($this->new_file, $this->rss_file);
            $this->log("\nExamine the updated RSS feed data\n");
            $this->parseNewsFeed();
            return;
        }

        // report error
        $this->postError(
            "Failed to read URL",
            MarkdownHelper::escape($this->cfg_url) . "\n" .
            "HTTP response code = ${code}"
        );

    }

/**
 * Main entry point for this cron job.
 */
    public function run()
    {
        if (! Yii::$app->mutex->acquire(static::MUTEX_ID)) {
            Console::stdout("RSS queue execution skipped - already running!\n");
            return;
        }

####### $this->logFileHandle = fopen(dirname(__FILE__) . '/log.txt', 'w');

        $this->cfg_url = $this->space->getSetting('url', 'rss');
        $this->cfg_article = $this->space->getSetting('article', 'rss', 'summary');
        $this->cfg_pictures = $this->space->getSetting('pictures', 'rss', 'yes');
        $this->cfg_maxwidth = (int)$this->space->getSetting('maxwidth', 'rss', '500');
        $this->cfg_maxheight = (int)$this->space->getSetting('maxheight', 'rss', '500');
        $this->cfg_owner = $this->space->getSetting('owner', 'rss', '');
        $this->cfg_dayshistory = (int)$this->space->getSetting('dayshistory', 'rss', '31');
        $this->cfg_daysfuture = (int)$this->space->getSetting('daysfuture', 'rss', '1');

        MarkdownHelper::$cfg_pictures = $this->cfg_pictures;
        MarkdownHelper::$cfg_maxwidth = $this->cfg_maxwidth;
        MarkdownHelper::$cfg_maxheight = $this->cfg_maxheight;

        $this->created_by = RssController::vetOwner($this->cfg_owner, $this->space)->id;

        $this->rss_folder = Yii::getAlias('@runtime/rss');
        $this->rss_file = $this->rss_folder . '/' . $this->space->guid . '.xml';
        $this->new_file = $this->rss_folder . '/' . $this->space->guid . '.new';

        $this->now = new \DateTime('now');
        $this->oldest = (new \DateTime('now'))->sub(new \DateInterval('P' . $this->cfg_dayshistory . 'D'));
        $this->newest = (new \DateTime('now'))->add(new \DateInterval('P' . $this->cfg_daysfuture . 'D'));

        $this->log("\n\n### run at " . print_r($this->now, true));

        $this->downloadNewsFeed();

        if ( $this->logFileHandle ) {
            fclose($this->logFileHandle);
        }

        Yii::$app->mutex->release(static::MUTEX_ID);
    }
}
