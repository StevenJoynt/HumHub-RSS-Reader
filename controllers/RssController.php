<?php

namespace sij\humhub\modules\rss\controllers;

use Yii;
use yii\web\HttpException;
use humhub\modules\user\models\User;
use humhub\modules\space\models\Space;
use humhub\modules\content\components\ContentContainerController;
use sij\humhub\modules\rss\models\ConfigureForm;

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
        if ( $form->load(Yii::$app->request->post()) && $form->validate() ) {
            $container->setSetting('url', $form->url, 'rss');
            return $this->redirect($container->createUrl('/rss/rss/config'));
        }
        return $this->render('config', array('model' => $form));
    }

}
