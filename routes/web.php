<?php

declare(strict_types=1);

namespace Flextype;

$app->group('/' . $admin_route, function () use ($app, $flextype) : void {

    // MailboxesController
    $app->get('/mailboxes', 'MailboxesController:index')->setName('admin.mailboxes.index');
    $app->get('/mailboxes/add', 'MailboxesController:add')->setName('admin.mailboxes.add');
    $app->post('/mailboxes/add', 'MailboxesController:addProcess')->setName('admin.mailboxes.addProcess');
    $app->get('/mailboxes/edit', 'MailboxesController:edit')->setName('admin.mailboxes.edit');
    $app->post('/mailboxes/edit', 'MailboxesController:editProcess')->setName('admin.mailboxes.editProcess');
    $app->get('/mailboxes/rename', 'MailboxesController:rename')->setName('admin.mailboxes.rename');
    $app->post('/mailboxes/rename', 'MailboxesController:renameProcess')->setName('admin.mailboxes.renameProcess');
    $app->post('/mailboxes/duplicate', 'MailboxesController:duplicateProcess')->setName('admin.mailboxes.duplicateProcess');
    $app->post('/mailboxes/delete', 'MailboxesController:deleteProcess')->setName('admin.mailboxes.deleteProcess');

    // MessagesController
    $app->get('/mailboxes/messages', 'MessagesController:index')->setName('admin.messages.index');
    $app->get('/mailboxes/messages/preview', 'MessagesController:preview')->setName('admin.messages.preview');
    $app->post('/mailboxes/messages/delete', 'MessagesController:deleteProcess')->setName('admin.messages.deleteProcess');

})->add(new AclIsUserLoggedInMiddleware(['container' => $flextype, 'redirect' => 'admin.accounts.login']))
  ->add(new AclIsUserLoggedInRolesInMiddleware(['container' => $flextype,
                                                'redirect' => ($flextype->acl->isUserLoggedIn() ? 'admin.accounts.no-access' : 'admin.accounts.login'),
                                                'roles' => 'admin']))
  ->add('csrf');
