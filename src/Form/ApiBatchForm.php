<?php

namespace Drupal\sync_external_posts\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sync_external_posts\Batch\BatchProcessor;

/**
 * Class ApiBatchForm.
 */
class ApiBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Let's put a submission button here.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync posts'),
    ];
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Syncing Paint Cans'))
      ->setFinishCallback([BatchProcessor::class, 'finishedCallback'])
      ->setInitMessage(t('Batch is starting'))
      ->setProgressMessage(t('Currently syncing paint cans.'))
      ->setErrorMessage(t('Batch has encountered an error'));

    // We can pass additional arguments if we want, such as settings from the
    // form. These would get passed as additional variables to the operation
    // callback method.
    $args = [];
    $batch_builder->addOperation([BatchProcessor::class, 'operationCallback'], $args);
    batch_set($batch_builder->toArray());
  }

}
