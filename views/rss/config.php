<?php

use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\modules\user\widgets\UserPickerField;
use humhub\libs\Html;

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('RssModule.base', 'RSS Module Configuration') ?>
    </div>
    <div class="panel-body">

        <?php $form = ActiveForm::begin(); ?>

        <div class="form-group">
            <?= $form->field($model, 'url'); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'interval'); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'daysfuture'); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'dayshistory'); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'owner')->widget(UserPickerField::class, ['id' => 'user_id', 'maxSelection' => '1']); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'article')->inline()->radioList(['full' => 'Full Article', 'summary' => 'Summary Only']); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'pictures')->inline()->radioList(['yes' => 'Yes', 'no' => 'No']); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'maxwidth'); ?>
        </div>

        <div class="form-group">
            <?= $form->field($model, 'maxheight'); ?>
        </div>

        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']); ?>
        <?php ActiveForm::end(); ?>

    </div>
</div>
