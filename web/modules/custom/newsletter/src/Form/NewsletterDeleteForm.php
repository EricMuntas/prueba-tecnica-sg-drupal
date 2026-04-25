<?php

namespace Drupal\newsletter\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de confirmación de eliminación de un suscriptor.
 */
class NewsletterDeleteForm extends ConfirmFormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * ID del suscriptor a eliminar.
   *
   * @var int
   */
  protected $subscriberId;

  /**
   * Constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newsletter_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('¿Estás seguro de que deseas eliminar este suscriptor?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('newsletter.admin_list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Eliminar');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $subscriber = $this->database->select('newsletter_subscribers', 'n')
      ->fields('n', ['email', 'name'])
      ->condition('id', $this->subscriberId)
      ->execute()
      ->fetchObject();

    if ($subscriber) {
      return $this->t('Se eliminará el suscriptor <strong>@email</strong> (@name). Esta acción no se puede deshacer.', [
        '@email' => $subscriber->email,
        '@name'  => $subscriber->name ?: $this->t('sin nombre'),
      ]);
    }

    return $this->t('Esta acción no se puede deshacer.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->subscriberId = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->delete('newsletter_subscribers')
      ->condition('id', $this->subscriberId)
      ->execute();

    $this->messenger()->addStatus($this->t('El suscriptor ha sido eliminado correctamente.'));
    $form_state->setRedirect('newsletter.admin_list');
  }

}
