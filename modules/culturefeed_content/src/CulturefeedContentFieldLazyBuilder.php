<?php

namespace Drupal\culturefeed_content;

use CultuurNet\SearchV3\Parameter\AudienceType;
use CultuurNet\SearchV3\Parameter\Query;
use CultuurNet\SearchV3\SearchQuery;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface;

/**
 * Provides a lazy builder for Culturfeed content fields.
 */
class CulturefeedContentFieldLazyBuilder {

  use StringTranslationTrait;

  /**
   * The Culturefeed search client.
   *
   * @var \Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface
   */
  protected $searchClient;

  /**
   * The Pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The current pager element.
   *
   * @var int
   */
  protected $pagerElement;

  /**
   * CulturefeedContentFieldLazyBuilder constructor.
   *
   * @param \Drupal\culturefeed_search_api\DrupalCulturefeedSearchClientInterface $searchClient
   *   The Culturefeed search client.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The Pager manager service.
   */
  public function __construct(DrupalCulturefeedSearchClientInterface $searchClient, PagerManagerInterface $pager_manager) {
    $this->searchClient = $searchClient;
    $this->pagerManager = $pager_manager;
    $this->pagerElement = 0;
  }

  /**
   * Build a Culturefeed content field.
   *
   * @param string $title
   *   The title to display.
   * @param string $query
   *   The search query to execute.
   * @param string $viewMode
   *   The view mode of the items to display.
   * @param int $limit
   *   Limit the number of items displayed.
   * @param string $sort
   *   The field to sort on.
   * @param string $sortDirection
   *   The sorting direction.
   * @param bool $defaultMoreLink
   *   Use default link or custom.
   * @param string $moreLink
   *   Custom show more link url.
   * @param bool $showPager
   *   Show a pager when the results are limited using $limit.
   *   Defaults to false.
   *
   * @return array
   *   Render array.
   */
  #[TrustedCallback]
  public function buildCulturefeedContent(string $title = '', string $query = '', string $viewMode = '', int $limit = 10, string $sort = NULL, string $sortDirection = 'desc', bool $defaultMoreLink = TRUE, string $moreLink = '', bool $showPager = FALSE) {
    if (!empty($query)) {
      $query = str_replace(',', ' AND ', '(' . rtrim($query . ')', ','));
    }

    if ($defaultMoreLink) {
      $moreLink = Link::createFromRoute($this->t('Show all events'), 'culturefeed_agenda.agenda', [], ['query' => array_filter(['q' => $query])]);
    }
    else {
      try {
        $moreLink = Link::fromTextAndUrl($this->t('Show all events'), Url::fromUserInput($moreLink ?? '/'));
      }
      catch (\InvalidArgumentException $e) {
        $moreLink = NULL;
      }
    }

    $build = [
      '#theme' => 'culturefeed_content_formatter',
      '#items' => [],
      '#view_mode' => $viewMode ?? 'teaser',
      '#title' => $title ?? '',
      '#more_link' => $moreLink,
      '#cache' => [
        'tags' => [
          'culturefeed_search',
        ],
        'max-age' => strtotime('+2 hours'),
      ],
    ];

    // Query the search API.
    try {
      $searchQuery = new SearchQuery(TRUE);
      $searchQuery->addParameter(new Query($query));
      $searchQuery->addParameter(new AudienceType('*'));
      $searchQuery->setLimit($limit);

      // Add pager support.
      if ($showPager && $limit) {
        $searchQuery->setStart($this->pagerManager->findPage($this->pagerElement) * $limit);
      }

      if ($sort) {
        $searchQuery->addSort($sort, $sortDirection);
      }

      $results = $this->searchClient->searchEvents($searchQuery);
      if (!empty($results->getMember()->getItems())) {
        $build['#items'] = $results->getMember()->getItems();
      }

      if ($showPager && $limit) {
        // Initialize the pager.
        $this->pagerManager->createPager($results->getTotalItems(), $limit, $this->pagerElement);

        $build['#pager'] = [
          '#type' => 'pager',
          '#quantity' => 5,
          '#element' => $this->pagerElement,
        ];

        // Support multiple pagers on one page.
        $this->pagerElement++;
      }
    }
    catch (\Exception $e) {
      $build['cache']['max-age'] = 0;
    }

    return $build;
  }

}
