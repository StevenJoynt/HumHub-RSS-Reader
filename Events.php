<?php

namespace sij\humhub\modules\rss;

use Yii;
use yii\helpers\Url;
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
    public static function onHourlyCron($event)
    {
        $ccmsEnabled = ContentContainerModuleState::find()->
            where(['module_id' => 'rss'])->
            andWhere(['module_state' => 1])->
            each();
        foreach ( $ccmsEnabled as $ccms ) {
            $cc = ContentContainer::findOne($ccms->contentcontainer_id);
            $space = Space::findOne($cc->pk);
            Yii::$app->queue->push(new jobs\GetFeedUpdates(['space' => $space]));
        }
    }

}
