<?php

namespace sij\humhub\modules\rss\controllers;

use Yii;
use yii\web\HttpException;
use humhub\modules\user\models\User;
use humhub\modules\space\models\Space;
use humhub\modules\content\components\ContentContainerController;
use sij\humhub\modules\rss\models\ConfigureForm;
use sij\humhub\modules\rss\jobs\GetFeedUpdates;

class RssController extends ContentContainerController
{

    /**
     * Configuration Action for Space Admins
     */
    public function actionConfig()
    {
        $container = $this->contentContainer;
        $form = new ConfigureForm();
        $form->url = $container->getSetting('url', 'rss');
        $form->article = $container->getSetting('article', 'rss', 'summary');
        $form->pictures = $container->getSetting('pictures', 'rss', 'yes');
        $form->maxwidth = $container->getSetting('maxwidth', 'rss', '500');
        $form->maxheight = $container->getSetting('maxheight', 'rss', '500');
        $form->interval = $container->getSetting('interval', 'rss', '60');
        $form->owner = RssController::vetOwner($container->getSetting('owner', 'rss', ''), $container)->guid;
        $form->dayshistory = $container->getSetting('dayshistory', 'rss', '31');
        $form->daysfuture = $container->getSetting('daysfuture', 'rss', '1');
        if ( $form->load(Yii::$app->request->post()) && $form->validate() ) {
            $container->setSetting('url', $form->url, 'rss');
            $container->setSetting('article', $form->article, 'rss');
            $container->setSetting('pictures', $form->pictures, 'rss');
            $container->setSetting('maxwidth', $form->maxwidth, 'rss');
            $container->setSetting('maxheight', $form->maxheight, 'rss');
            $container->setSetting('interval', $form->interval, 'rss');
            $container->setSetting('owner', RssController::vetOwner($form->owner, $container)->id, 'rss');
            $container->setSetting('dayshistory', $form->dayshistory, 'rss');
            $container->setSetting('daysfuture', $form->daysfuture, 'rss');
            Yii::$app->queue->push(new GetFeedUpdates(['space' => $container, 'force' => true]));
            return $this->redirect($container->createUrl('/rss/rss/config'));
        }
        return $this->render('config', array('model' => $form));
    }

    public static function vetOwner($owner, $space) {
        $owner = explode(',', $owner)[0]; // only interested in the first owner
        $user = null;
        if ( $owner ) {
            if ( ctype_digit($owner) ) {
                // user id number supplied - from our settings
                $user = User::findOne(['id'=>$owner]);
            } else {
                // user guid string supplied - from UserPicker::widget
                $user = User::findOne(['guid'=>$owner]);
            }
        }
        if ( ! $user ) {
            // no valid user selected
            // default to the owner of this space
            $user = $space->ownerUser;
        }
        return $user;
    }

}
