<?php

namespace sij\humhub\modules\rss;

use Yii;
use yii\helpers\Url;
use yii\helpers\Console;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\content\models\ContentContainerModuleState;
use humhub\modules\space\models\Space;

class Events
{

    /**
     * Defines what to do if admin menu is initialized.
     *
     * @param $event
     */
    public static function onAdminMenuInit($event)
    {
        $event->sender->addItem([
            'label' => 'RSS',
            'url' => Url::to(['/rss/admin']),
            'group' => 'manage',
            'icon' => '<i class="fa fa-rss"></i>',
            'isActive' => (
                Yii::$app->controller->module &&
                Yii::$app->controller->module->id == 'rss' &&
                Yii::$app->controller->id == 'admin'
            ),
            'sortOrder' => 99999,
        ]);
    }

    /**
     * Tasks on hourly cron job
     *
     * @param \yii\base\Event $event
     */
    public static function onCron($event)
    {
        try {
            Console::stdout("Updating RSS news feeds...\n");
            $ccmsEnabled = ContentContainerModuleState::find()->
                where(['module_id' => 'rss'])->
                andWhere(['module_state' => 1])->
                each();
            foreach ( $ccmsEnabled as $ccms ) {
                $cc = ContentContainer::findOne($ccms->contentcontainer_id);
                $space = Space::findOne($cc->pk);
                $interval = $this->settings->space()->get('interval', 'rss', 60);
                $lastrun = $this->settings->space()->get('lastrun', 'rss', '');
                if (! empty($lastrun) && time() < ($interval * 60 + $lastrun))
                    continue;
                $space->$this->settings->space()->set('lastrun', time(), 'rss');
                Console::stdout("  Queueing update for space \"" . $space->name . "\"\n");
                Yii::$app->queue->push(new jobs\GetFeedUpdates(['space' => $space, 'force' => false]));
            }
            Console::stdout(Console::renderColoredString("%gdone.%n\n", 1));
        } catch (\Throwable $e) {
            $e->getMessage()."\n";
            Yii::error($e);
        }
    }

}
