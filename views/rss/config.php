<?php
use humhub\compat\CActiveForm;
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

        <?php echo Html::submitButton('Save', array('class' => 'btn btn-primary')); ?>
        <?php CActiveForm::end(); ?>

    </div>
</div>
