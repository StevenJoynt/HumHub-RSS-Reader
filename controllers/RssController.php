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

}
