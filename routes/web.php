<?php

declare(strict_types=1);

namespace Flextype;

$app->group('/' . $admin_route, function () use ($app) : void {

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
    $app->get('/mailboxes/messages/add', 'MessagesController:add')->setName('admin.messages.add');
    $app->post('/mailboxes/messages/add', 'MessagesController:addProcess')->setName('admin.messages.addProcess');
    $app->get('/mailboxes/messages/edit', 'MessagesController:edit')->setName('admin.messages.edit');
    $app->post('/mailboxes/messages/edit', 'MessagesController:editProcess')->setName('admin.messages.addProcess');
    $app->post('/mailboxes/messages/duplicate', 'MessagesController:duplicateProcess')->setName('admin.messages.duplicateProcess');
    $app->post('/mailboxes/messages/delete', 'MessagesController:deleteProcess')->setName('admin.messages.deleteProcess');

})->add(new AdminPanelAuthMiddleware($flextype));
