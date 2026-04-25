<?php

namespace Drupal\newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario de edición de un suscriptor desde el panel de administración.
 */
class NewsletterEditForm extends FormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
    return 'newsletter_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $subscriber = $this->database->select('newsletter_subscribers', 'n')
      ->fields('n')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$subscriber) {
      $this->messenger()->addError($this->t('El suscriptor no existe.'));
      return $form;
    }

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre'),
      '#default_value' => $subscriber->name,
      '#maxlength' => 255,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Correo electrónico'),
      '#default_value' => $subscriber->email,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => [
        1 => $this->t('Activo'),
        0 => $this->t('Inactivo'),
      ],
      '#default_value' => $subscriber->status,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar cambios'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('newsletter.admin_list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $id    = $form_state->getValue('id');

    // Comprobar que no existe otro suscriptor con el mismo email.
    $exists = $this->database->select('newsletter_subscribers', 'n')
      ->fields('n', ['id'])
      ->condition('email', $email)
      ->condition('id', $id, '<>')
      ->execute()
      ->fetchField();

    if ($exists) {
      $form_state->setErrorByName('email', $this->t('Ya existe otro suscriptor con este correo electrónico.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->update('newsletter_subscribers')
      ->fields([
        'name'   => $form_state->getValue('name'),
        'email'  => $form_state->getValue('email'),
        'status' => $form_state->getValue('status'),
      ])
      ->condition('id', $form_state->getValue('id'))
      ->execute();

    $this->messenger()->addStatus($this->t('El suscriptor ha sido actualizado correctamente.'));
    $form_state->setRedirect('newsletter.admin_list');
  }

}
