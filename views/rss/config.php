<?php
use humhub\compat\CActiveForm;
use humhub\modules\user\widgets\UserPicker;
use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading">
        RSS Module Configuration
    </div>
    <div class="panel-body">

        <?php $form = CActiveForm::begin(); ?>
        <?php echo $form->errorSummary($model); ?>

        <div class="form-group">
            <?php echo $form->field($model, 'url'); ?>
            <?php echo $form->error($model, 'url'); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'owner')->textInput(['id' => 'owner']); ?>
            <?php echo $form->error($model, 'owner'); ?>
            <?php echo UserPicker::widget(array(
                'placeholderText' => 'Select a user',
                'maxUsers' => 1,
                'attribute' => 'owner',
                'model' => $model,
                'inputId' => 'owner'
            )); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'article')->radioList(['full' => 'Full Article', 'summary' => 'Summary Only']); ?>
            <?php echo $form->error($model, 'article'); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'pictures')->radioList(['yes' => 'Yes', 'no' => 'No']); ?>
            <?php echo $form->error($model, 'pictures'); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'maxwidth'); ?>
            <?php echo $form->error($model, 'maxwidth'); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'maxheight'); ?>
            <?php echo $form->error($model, 'maxheight'); ?>
        </div>

        <div class="form-group">
            <?php echo $form->field($model, 'interval'); ?>
            <?php echo $form->error($model, 'interval'); ?>
        </div>

        <?php echo Html::submitButton('Save', array('class' => 'btn btn-primary')); ?>
        <?php CActiveForm::end(); ?>

    </div>
</div>
