<?php

namespace Drupal\newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulario público de suscripción al Newsletter.
 */
class NewsletterSubscribeForm extends FormBase {

  /**
   * La conexión a la base de datos.
   *
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
    return 'newsletter_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'newsletter-subscribe-form';

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Suscríbete a nuestro Newsletter y recibe las últimas novedades en tu correo.') . '</p>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre'),
      '#placeholder' => $this->t('Tu nombre'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Correo electrónico'),
      '#placeholder' => $this->t('tu@email.com'),
      '#required' => TRUE,
    ];

    $form['privacy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('He leído y acepto la <a href="/politica-privacidad" target="_blank">Política de Privacidad</a>.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('¡Suscribirme!'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    if (!\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('El correo electrónico no es válido.'));
      return;
    }

    // Comprobar si el email ya está registrado.
    $exists = $this->database->select('newsletter_subscribers', 'n')
      ->fields('n', ['id'])
      ->condition('email', $email)
      ->execute()
      ->fetchField();

    if ($exists) {
      $form_state->setErrorByName('email', $this->t('Este correo electrónico ya está suscrito al Newsletter.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->database->insert('newsletter_subscribers')
        ->fields([
          'email'   => $form_state->getValue('email'),
          'name'    => $form_state->getValue('name') ?? '',
          'created' => \Drupal::time()->getRequestTime(),
          'status'  => 1,
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('¡Gracias! Te has suscrito correctamente al Newsletter.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Ha ocurrido un error al procesar tu suscripción. Por favor, inténtalo de nuevo.'));
      \Drupal::logger('newsletter')->error('Error al insertar suscriptor: @error', ['@error' => $e->getMessage()]);
    }
  }

}
