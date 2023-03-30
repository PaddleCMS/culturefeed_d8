<?php

/**
 * @file
 * Template preprocessors for agenda templates.
 */

use Drupal\culturefeed_agenda\Utility\SearchPreprocessor;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url as CoreUrl;
use Drupal\culturefeed_agenda\Url;

/**
 * Preprocess an event item.
 *
 * @param array $variables
 *   Array of currently known variables.
 */
function template_preprocess_culturefeed_event(array &$variables) {
  $variables['view_mode'] = $variables['elements']['#view_mode'] ?? 'full';

  // Provide a distinct $teaser boolean.
  $variables['teaser'] = $variables['view_mode'] == 'teaser';

  /** @var \CultuurNet\SearchV3\ValueObjects\Event $event */
  $event = $variables['elements']['#item'];
  $variables['item'] = $event;

  $langcode = $variables['elements']['#langcode'] ?? \Drupal::languageManager()->getCurrentLanguage()->getId();

  $searchPreprocessor = new SearchPreprocessor();

  // Preprocessor settings.
  $settings = [
    'image' => [
      'width' => 360,
      'height' => 360,
      'default_image' => \Drupal::service('file_url_generator')->generateAbsoluteString(Drupal::service('module_handler')->getModule('culturefeed_agenda')->getPath() . '/img/no-thumbnail.gif'),
      'fit' => 'fill',
    ],
    'description' => [
      'characters' => 255,
    ],
    'type' => [
      'enabled' => TRUE,
    ],
  ];

  // Merge provided settings on the default settings.
  if (!empty($variables['elements']['#settings'])) {
    $settings = NestedArray::mergeDeep($settings, $variables['elements']['#settings']);
  }

  $variables += $variables['view_mode'] === 'full' ? $searchPreprocessor->preprocessEventDetail($event, $langcode, $settings) : $searchPreprocessor->preprocessEvent($event, $langcode, $settings);

  if (!empty($variables['description'])) {
    $variables['description'] = [
      '#type' => 'processed_text',
      '#text' => $variables['description'],
      '#format' => 'filtered_html',
    ];
  }

  if (!empty($variables['summary'])) {
    $variables['summary'] = [
      '#type' => 'processed_text',
      '#text' => $variables['summary'],
      '#format' => 'filtered_html',
    ];
  }

  $variables['when_summary'] = Markup::create($variables['when_summary']);

  if (isset($variables['when_details'])) {
    $variables['when_details'] = Markup::create($variables['when_details']);
  }

  if ($endDate = $event->getEndDate()) {
    $variables['event_passed'] = $endDate->getTimestamp() < Drupal::time()->getCurrentTime();
  }

  if (isset($variables['prices'])) {
    foreach ($variables['prices'] as $key => $price) {
      $variables['prices'][$key]['price'] = Markup::create($variables['prices'][$key]['price']);
      $variables['prices'][$key]['info'] = Markup::create($variables['prices'][$key]['info']);
    }
  }

  // Url to detail page.
  $variables['url'] = Url::toEventDetail($event);

  // Add mailto on mail.
  $mailUrl = clone $variables['url'];
  $mailUrl->setAbsolute(TRUE);
  $mailtoQuery = [
    'subject' => $variables['name'] . ' (via ' . \Drupal::config('system.site')->get('name') . ')',
    'body' => $mailUrl->toString(),
  ];

  if (!empty($variables['contact_info']) && !empty($variables['contact_info']['emails'])) {
    foreach ($variables['contact_info']['emails'] as $key => $email) {
      $variables['contact_info']['emails'][$key] = Link::fromTextAndUrl($email, CoreUrl::fromUri('mailto:' . $email, ['query' => $mailtoQuery]));
    }
  }

  if (!empty($variables['booking_info']) && !empty($variables['booking_info']['email'])) {
    $email = $variables['booking_info']['email'];
    $variables['booking_info']['email'] = Link::fromTextAndUrl($email, CoreUrl::fromUri('mailto:' . $email, ['query' => $mailtoQuery]));
  }

  $variables['#cache'] = [
    'tags' => [
      'culturefeed_event',
      'culturefeed_event:' . $event->getId(),
    ],
  ];

  // Attempt to preprocess additional variables for the current view mode.
  $function = __FUNCTION__ . '__' . $variables['view_mode'];
  if (function_exists($function)) {
    $function($variables);
  }
}

/**
 * Preprocess additional variables for an event in the teaser view mode.
 *
 * @param array $variables
 *   Array of currently known variables.
 */
function template_preprocess_culturefeed_event__teaser(array &$variables) {
  $variables['location'] = t('Location to be discussed');

  if (!empty($variables['where'])) {
    if (!empty($variables['where']['address']['city'])) {
      $variables['location'] = $variables['where']['address']['city'];
    }
    elseif ($variables['where']['name']) {
      $variables['location'] = $variables['where']['name'];
    }
  }
}

/**
 * Preprocess additional variables for an event in the full view mode.
 *
 * @param array $variables
 *   Array of currently known variables.
 */
function template_preprocess_culturefeed_event__full(array &$variables) {
  if (isset($variables['types'])) {
    $variables['types_urls'] = [];
    foreach ($variables['types'] as $key => $type) {
      $query = [
        'types' => [
          $type->getId() => $type->getLabel(),
        ],
      ];
      $variables['types_urls'][$type->getLabel()] = Url::fromRoute('culturefeed_agenda.agenda', [], ['query' => $query]);
    }
  }

  if (isset($variables['themes'])) {
    $variables['themes_urls'] = [];
    foreach ($variables['themes'] as $key => $theme) {
      $query = [
        'themes' => [
          $theme->getId() => $theme->getLabel(),
        ],
      ];
      $variables['themes_urls'][$theme->getLabel()] = Url::fromRoute('culturefeed_agenda.agenda', [], ['query' => $query]);
    }
  }

  // Create a summary from the long description
  // and strip the summary from the description.
  $longDescription = \Drupal::service('renderer')->render($variables['description']);
  $introParagraph = substr($longDescription, strpos($longDescription, '<p'), strpos($longDescription, '</p>') + 4);

  if ($introParagraph) {
    // Remove the extracted text from the long description.
    $variables['description'] = [
      '#markup' => str_replace($introParagraph, '', $longDescription),
    ];

    // Overwrite the default summary text.
    $variables['summary'] = [
      '#markup' => $introParagraph,
    ];
  }

  // If the intro contains an equal amount of paragraphs as
  // the long description, remove the long description.
  // Use DOMDocument to count the number of top-level paragraphs.
  $longDescriptionDOM = new DOMDocument();
  if (!empty($longDescription)) {
    $longDescriptionDOM->loadHTML($longDescription);
  }

  $introParagraphDOM = new DOMDocument();
  if (!empty($introParagraph)) {
    $introParagraphDOM->loadHTML($introParagraph);
  }

  if ($longDescriptionDOM->getElementsByTagName('p')->length === $introParagraphDOM->getElementsByTagName('p')->length) {
    // If the summary and description are the same, remove the description.
    unset($variables['description']);
  }
}

/**
 * Preprocess Culturefeed agenda search results.
 *
 * @param array $variables
 *   List of current known variables.
 */
function template_preprocess_culturefeed_agenda_search_results(array &$variables) {
  // Prepare the search result rows.
  $variables['rows'] = [];

  /** @var \CultuurNet\SearchV3\ValueObjects\Event $item */
  foreach ($variables['results'] as $item) {
    $variables['rows'][] = [
      '#theme' => 'culturefeed_event',
      '#view_mode' => 'teaser',
      '#item' => $item,
      '#settings' => [
        'image' => [
          'width' => 150,
          'height' => 150,
          'fit' => 'auto',
        ],
      ],
    ];
  }

  // Reset url.
  $variables['reset_url'] = Url::fromRoute('<current>')->toString();
}

/**
 * Preprocess Culturefeed agenda search result count summary.
 *
 * @param array $variables
 *   List of current known variables.
 */
function template_preprocess_culturefeed_agenda_search_result_count_summary(array &$variables) {
  $variables['summary'] = NULL;

  $total = $variables['total'];
  $currentPage = $variables['current_page'];
  $itemsPerPage = $variables['items_per_page'];

  // Don't show the text if there is only one result item.
  if ($total > 1) {
    $variables['summary'] = t('@from-@to of @count results',
      [
        '@from' => $currentPage * $itemsPerPage + 1,
        '@to' => $total < 20 ? $total : $currentPage * $itemsPerPage + 20,
        '@count' => $total,
      ],
      [
        'context' => 'culturefeed_agenda',
      ]
    );
  }
}
