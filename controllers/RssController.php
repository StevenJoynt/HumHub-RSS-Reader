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
        $model = new ConfigureForm();
        $model->loadSettings();

        if ($model->load(Yii::$app->request->post()) && $model->saveSettings()) {
            $this->view->saved();
        }

        return $this->render('config', ['model' => $model]);
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
