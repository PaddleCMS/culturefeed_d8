<?php

namespace Drupal\culturefeed_agenda\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\culturefeed_search\SearchPageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'AgendaSearchPagerBlock' block.
 *
 * @Block(
 *  id = "culturefeed_agenda_search_page_pager",
 *  admin_label = @Translation("Agenda search page pager")
 * )
 */
class AgendaSearchPagerBlock extends AgendaSearchPageBlockBase {

  /**
   * Maximum number of pager links to show.
   */
  const PAGER_MAX_LINKS = 5;

  /**
   * The Pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('culturefeed_agenda.search_page_service'),
      $container->get('form_builder'),
      $container->get('pager.manager')
    );
  }

  /**
   * AgendaSearchPagerBlock constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\culturefeed_search\SearchPageServiceInterface $searchPageService
   *   The search page service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SearchPageServiceInterface $searchPageService, FormBuilderInterface $formBuilder, PagerManagerInterface $pager_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $searchPageService, $formBuilder);

    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Initialize the pager.
    $this->pagerManager->createPager($this->searchPageService->getTotalResults(), $this->searchPageService->getItemsPerPage());

    return [
      '#theme' => 'culturefeed_agenda_search_pager',
      '#pager' => [
        '#type' => 'pager',
        '#quantity' => self::PAGER_MAX_LINKS,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

}
