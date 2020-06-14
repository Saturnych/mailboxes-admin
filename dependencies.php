<?php

declare(strict_types=1);

/**
 * @link http://digital.flextype.org
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flextype;

use Slim\Flash\Messages;
use Slim\Http\Environment;
use Slim\Http\Uri;
use Flextype\Component\I18n\I18n;
use function Flextype\Component\I18n\__;

// Add Admin Navigation
$flextype->registry->set('plugins.admin.settings.navigation.extends.mailboxes', ['title' => __('mailboxes_admin_mailboxes'),'icon' => 'fas fa-envelope', 'link' => $flextype->router->pathFor('admin.mailboxes.index')]);

/**
 * Add mailboxes service to Flextype container
 */
$flextype['mailboxes'] = static function ($container) use ($flextype, $app) {
    return new Mailboxes($flextype, $app);
};

/**
 * Init mailboxes
 */
$flextype['mailboxes']->init($flextype, $app);

// Add MailboxesController
$flextype['MailboxesController'] = static function ($container) {
    return new MailboxesController($container);
};

// Add MessagesController
$flextype['MessagesController'] = static function ($container) {
    return new MessagesController($container);
};

$_flextype_menu = ($flextype['registry']->has('plugins.admin.settings.flextype_menu')) ? $flextype['registry']->get('plugins.admin.settings.flextype_menu') : [];

if ($flextype['registry']->has('flextype.settings.url') && $flextype['registry']->get('flextype.settings.url') != '') {
    $site_url = $flextype['registry']->get('flextype.settings.url');
} else {
    $site_url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
}

