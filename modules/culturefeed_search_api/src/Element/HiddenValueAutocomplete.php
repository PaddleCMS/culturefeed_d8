<?php

namespace Drupal\culturefeed_search_api\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Element\Textfield;

/**
 * Creates a hidden value autocomplete element.
 *
 * The value of the selected item is stored in the value element,
 * and the label of the selected item is shown in the
 * autocomplete element instead.
 *
 * @FormElement("culturefeed_hidden_value_autocomplete")
 */
class HiddenValueAutocomplete extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#input' => TRUE,
      '#no_validate' => FALSE,
      '#size' => 60,
      '#maxlength' => 128,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [$class, 'processElement'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#element_validate' => [
        [$class, 'validateHiddenAutocomplete'],
      ],
    ];
  }

  /**
   * Process the "hidden value autocomplete" element.
   *
   * @param array $element
   *   Element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#tree'] = TRUE;

    $element['#wrapper_attributes']['class'] = [
      'hidden-value-autocomplete--group',
    ];

    // Create the autocomplete element with the properties of the parent.
    $element['label'] = [
      '#input' => $element['#input'],
      '#type' => 'textfield',
      '#autocomplete_route_name' => $element['#autocomplete_route_name'],
      '#autocomplete_route_parameters' => $element['#autocomplete_route_parameters'] ?? [],
      '#size' => $element['#size'],
      '#maxlength' => $element['#maxlength'],
      '#process' => [
        [Textfield::class, 'processAutocomplete'],
        [Textfield::class, 'processAjaxForm'],
        [Textfield::class, 'processPattern'],
        [Textfield::class, 'processGroup'],
        [self::class, 'processAutocompleteLibrary'],
      ],
      // Set required property on autocomplete element.
      '#required' => $element['#required'],
      '#placeholder' => $element['#placeholder'] ?? NULL,
      '#ajax' => $element['#ajax'] ?? [],
      '#default_value' => $element['#default_value']['label'] ?? NULL,
    ];

    // Add a hidden field for storing the autocomplete value.
    $element['value'] = [
      '#type' => 'hidden',
      '#default_value' => $element['#default_value']['value'] ?? NULL,
    ];

    // Remove validation stuff of main element.
    unset($element['#maxlength']);

    return $element;
  }

  /**
   * Swap out the core autocomplete library for our custom library.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processAutocompleteLibrary(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (!empty($element['#attributes']['data-autocomplete-path'])) {
      $element['#attributes']['class'][] = 'hidden-value-autocomplete';
      $element['#attached']['library'] = ['culturefeed_search_api/hidden-value-autocomplete'];
    }

    return $element;
  }

  /**
   * Validate the hidden autocomplete.
   */
  public static function validateHiddenAutocomplete(&$element, FormStateInterface $form_state, &$complete_form) {
    if (!$element['#no_validate'] && $element['#required'] && empty($element['value']['#value'])) {
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }
  }

}
