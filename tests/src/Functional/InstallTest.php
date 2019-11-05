<?php

namespace Drupal\Tests\commerce_bluesnap\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests module installation.
 *
 * @group commerce_bluesnap
 */
class InstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [];

  /**
   * Admin user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
  }

  /**
   * Tests module installation.
   */
  public function testInstall() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet(Url::fromRoute('system.modules_list')->toString());
    $page->checkField('modules[commerce_bluesnap][enable]');
    $page->pressButton('Install');
    $assert_session->pageTextNotContains(
      'Commerce BlueSnap requires the shabananavas/php-bluesnap-sdk library.'
    );
  }

}
