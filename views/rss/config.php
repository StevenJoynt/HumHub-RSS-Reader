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

        <?php echo Html::submitButton('Save', array('class' => 'btn btn-primary')); ?>
        <?php CActiveForm::end(); ?>

    </div>
</div>
