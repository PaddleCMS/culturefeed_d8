<?php

namespace Drupal\culturefeed_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Drupal\culturefeed_api\DrupalCultureFeedClient;
use Drupal\culturefeed_user\CultureFeedCurrentUserInterface;
use Drupal\culturefeed_api\CultureFeedUserContextManagerInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AuthenticationController.
 */
class AuthenticationController extends ControllerBase {

  /**
   * The Drupal CultureFeed client.
   *
   * @var \Drupal\culturefeed_api\DrupalCultureFeedClient
   */
  protected $cultureFeedClient;

  /**
   * The drupal external auth service.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * CultureFeed user context manager service.
   *
   * @var \Drupal\culturefeed_api\CultureFeedUserContextManagerInterface
   */
  protected $userContextManager;

  /**
   * The current Drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The culturefeed current user.
   *
   * @var \Drupal\culturefeed_user\CultureFeedCurrentUserInterface
   */
  protected $culturefeedCurrentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('culturefeed_api.client'),
      $container->get('externalauth.externalauth'),
      $container->get('culturefeed_api.user_context_manager'),
      $container->get('current_user'),
      $container->get('culturefeed_user.current_user')
    );
  }

  /**
   * AuthenticationController constructor.
   *
   * @param \Drupal\culturefeed_api\DrupalCultureFeedClient $cultureFeedClient
   *   The CultureFeed API client.
   * @param \Drupal\externalauth\ExternalAuthInterface $externalAuth
   *   The drupal external auth service.
   * @param \Drupal\culturefeed_api\CultureFeedUserContextManagerInterface $userContextManager
   *   The CultureFeed "UiTID" user context manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\culturefeed_user\CultureFeedCurrentUserInterface $cultureFeedCurrentUser
   *   The Culturefeed current user.
   */
  public function __construct(
    DrupalCultureFeedClient $cultureFeedClient,
    ExternalAuthInterface $externalAuth,
    CultureFeedUserContextManagerInterface $userContextManager,
    AccountProxyInterface $currentUser,
    CultureFeedCurrentUserInterface $cultureFeedCurrentUser
  ) {
    $this->cultureFeedClient = $cultureFeedClient;
    $this->externalAuth = $externalAuth;
    $this->userContextManager = $userContextManager;
    $this->currentUser = $currentUser;
    $this->culturefeedCurrentUser = $cultureFeedCurrentUser;
  }

  /**
   * Connect.
   *
   * @return string
   *   Return Connect string.
   */
  public function connect(Request $request) {

    $language = $this->languageManager()->getCurrentLanguage();

    $options = ['absolute' => TRUE];
    if ($request->query->get('destination')) {
      $options['query']['destination'] = $request->query->get('destination');
      $request->query->remove('destination');
    }

    $callback_url = $this->getUrlGenerator()->generateFromRoute('culturefeed_user.authorize', [], $options, TRUE);

    // Fetch the request token.
    try {
      $token = $this->cultureFeedClient->getRequestToken($callback_url->getGeneratedUrl());
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while logging in. Please try again later.'));
      Error::logException($this->getLogger('culturefeed'), $e);
      return new RedirectResponse($this->getUrlGenerator()->generateFromRoute('<front>'), 302);
    }
    if (!$token) {
      $this->messenger()->addError($this->t('An error occurred while logging in. Please try again later.'));
      return new RedirectResponse($this->getUrlGenerator()->generateFromRoute('<front>'), 302);
    }

    $_SESSION['oauth_token'] = $token['oauth_token'];
    $_SESSION['oauth_token_secret'] = $token['oauth_token_secret'];

    $skip_confirmation = $request->query->has('skipConfirmation');

    $auth_url = $this->cultureFeedClient->getUrlAuthorize($token, $callback_url->getGeneratedUrl(), \CultureFeed::AUTHORIZE_TYPE_REGULAR, $skip_confirmation, NULL, NULL, $language->getId());

    $redirect = new TrustedRedirectResponse($auth_url, 302);
    $metadata = $redirect->getCacheableMetadata();
    $metadata->setCacheMaxAge(0);

    return $redirect;

  }

  /**
   * Authorize.
   *
   * @return string
   *   Return Authorize string.
   */
  public function authorize(Request $request) {
    $query = $request->query;

    if ($query->get('oauth_token') && $query->get('oauth_verifier')) {
      try {
        $this->cultureFeedClient->updateClient($query->get('oauth_token'), $_SESSION['oauth_token_secret']);
        $token = $this->cultureFeedClient->getAccessToken($query->get('oauth_verifier'));

        unset($_SESSION['oauth_token']);
        unset($_SESSION['oauth_token_secret']);

        $this->cultureFeedClient->updateClient($token['oauth_token'], $token['oauth_token_secret']);
        $account = $this->cultureFeedClient->getUser($token['userId']);
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('An error occurred while logging in. Please try again later.'));
        Error::logException($this->getLogger('culturefeed'), $e);
        return new RedirectResponse($this->getUrlGenerator()->generateFromRoute('<front>'), 302);
      }

      $accountData = [
        'name' => $account->nick,
      ];

      // Login/register through externalauth service.
      if ($account = $this->externalAuth->loginRegister($account->id, 'culturefeed_uitid', $accountData)) {

        // Update the user context.
        $this->userContextManager->setUserAccessSecret($token['oauth_token_secret']);
        $this->userContextManager->setUserAccessToken($token['oauth_token']);
        $this->userContextManager->setUserId($token['userId']);

        if ($request->get('destination')) {
          try {
            $this->redirect($request->get('destination'));
          }
          catch (\Exception $e) {
            return new RedirectResponse($request->get('destination'), 302);
          }
        }

        return new RedirectResponse($this->getUrlGenerator()->generateFromRoute('<front>'), 302);
      }
    }
  }

  /**
   * Authenticated check.
   *
   * @return mixed
   *   Return Authorize string.
   */
  public function authenticated(Request $request) {
    if ($this->currentUser->isAuthenticated() && $this->culturefeedCurrentUser->isCultureFeedUser()) {

      if ($request->query->has('_exception_statuscode') && $request->query->get('_exception_statuscode') === 403) {
        return [
          '#markup' => $this->t('You are not authorized to access this page.'),
          '#title' => $this->t('Access denied'),
        ];
      }

      // Redirect to homepage (or destination if there is one).
      return new RedirectResponse($this->getUrlGenerator()->generateFromRoute('<front>'), 302);
    }

    return [
      '#theme' => 'culturefeed_user_authenticated_page',
    ];
  }

}
