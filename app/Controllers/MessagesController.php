<?php

declare(strict_types=1);

namespace Flextype;

use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Session\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use function date;
use function Flextype\Component\I18n\__;

/**
 * @property twig $twig
 * @property Router $router
 * @property Cache $cache
 * @property Mailboxes $mailboxes
 * @property Slugify $slugify
 * @property Flash $flash
 */
class MessagesController extends Container
{

    protected $plugin_path = 'plugins/mailboxes-admin';
    protected $template_path = '/templates/extends/mailboxes';
    protected $mailboxes_path = '/mailboxes';
    protected $mailbox_filepath = '/mailbox.md';
    protected $message_filepath = '/message.md';
    protected $message_cleancopy = true;

    /**
     * Index page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function index(/** @scrutinizer ignore-unused */ Request $request, Response $response) : Response
    {
        // Get mailbox from request query params
        $mailbox = $request->getQueryParams()['mailbox'];
        $mailbox_data['title'] = $mailbox;

        if (!empty($mailbox)) {
          $mailbox_file = PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $this->mailbox_filepath;
          if (Filesystem::has($mailbox_file))
            $mailbox_data = $this->serializer->decode(Filesystem::read($mailbox_file), 'frontmatter');
        }

        $messages = [];
        $messages_list = Filesystem::listContents(PATH['project'] . $this->mailboxes_path . '/' . $mailbox);

        if (count($messages_list) > 0) {
            foreach ($messages_list as $message) {
                $file = $message['path'] . $this->message_filepath;
                if ($message['type'] == 'dir' && Filesystem::has($file)) {
                    $data = Filesystem::read($file);
                    $mess = $this->serializer->decode($data, 'frontmatter');
                    $message = array_merge($message,$mess);
                    $messages[] = $message;
                }
            }
        }

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/messages/index.html',
            [
                'menu_item' => 'mailboxes',
                'mailbox' => $mailbox,
                'messages_list' => $messages,
                'links' =>  [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),
                    ],
                    'messages' => [
                        'link' => $this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_messages'),
                        'active' => true
                    ],
                    'mailbox' => [
                        'link' => $this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_active_mailbox') . ': ' . $mailbox_data['title'],
                        'active' => false
                    ],
                ],
                'buttons' => [
                    'messages_create' => [
                        'link' => $this->router->pathFor('admin.messages.add') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_create_new_message'),

                    ],
                ],
            ]
        );
    }

    /**
     * Add message
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function add(/** @scrutinizer ignore-unused */ Request $request, Response $response) : Response
    {
        // Get mailbox from request query params
        $mailbox = $request->getQueryParams()['mailbox'];

        $uuid = Uuid::uuid4()->toString();

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/messages/add.html',
            [
                'menu_item' => 'mailboxes',
                'mailbox' => $mailbox,
                'uuid' => $uuid,
                'links' =>  [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),

                    ],
                    'messages' => [
                        'link' => $this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_messages'),

                    ],
                    'messages_add' => [
                        'link' => $this->router->pathFor('admin.messages.add') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_create_new_message'),
                        'active' => true
                    ],
                ],
            ]
        );
    }

    /**
     * Add message process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function addProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $post_data = $request->getParsedBody();
        // Get request query params
        $params = $request->getQueryParams();

        $id  = (!empty($post_data['id']))?$post_data['id']:$params['id'];
        $mailbox = (!empty($post_data['mailbox']))?$post_data['mailbox']:$params['mailbox'];

        // Generate UUID
        if (!empty($id)) $message['uuid'] = $id; else $message['uuid'] = Uuid::uuid4()->toString();
        $path = PATH['project'] . $this->mailboxes_path . '/' . $mailbox. '/' . $message['uuid'];

        if (! Filesystem::has($path)) Filesystem::createDir($path);
        else {
            $message['uuid'] = Uuid::uuid4()->toString();
            $path = PATH['project'] . $this->mailboxes_path . '/' . $mailbox. '/' . $message['uuid'];
        }

        $message['created_at'] = (string) date($this->registry->get('flextype.settings.date_format'), time());
        $message['created_by'] = Session::get('uuid');

        $md = $this->serializer->encode($message, 'frontmatter');
        $file = $path . $this->message_filepath;

        if (! Filesystem::has($file)) {
            if (Filesystem::write($file,$md)) {
                $this->flash->addMessage('success', __('mailboxes_admin_message_msg_created'));
            } else {
                $this->flash->addMessage('error', __('mailboxes_admin_message_msg_was_not_created'));
            }
        } else {
            $this->flash->addMessage('error', __('mailboxes_admin_message_msg_was_not_created'));
        }

        if (isset($post_data['create-and-edit'])) {
            return $response->withRedirect($this->router->pathFor('admin.messages.edit') . '?mailbox=' . $mailbox . '&uuid=' . $message['uuid']);
        }

        return $response->withRedirect($this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox);
    }

    /**
     * Edit message
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function edit(Request $request, Response $response) : Response
    {
        // Get request query params
        $params = $request->getQueryParams();

        $id  = (!empty($params['uuid']))?$params['uuid']:$params['id'];
        $mailbox = $params['mailbox'];

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/messages/edit.html',
            [
                'menu_item' => 'mailboxes',
                'mailbox' => $mailbox,
                'id' => $id,
                'data' => Filesystem::read(PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $id . $this->message_filepath),
                'type' => 'message',
                'links' => [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),
                    ],
                    'messages' => [
                        'link' => $this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_messages'),

                    ],
                    'messages_editor' => [
                        'link' => $this->router->pathFor('admin.messages.edit') . '?id=' . $id . '&mailbox=' . $mailbox,
                        'title' => __('admin_editor'),
                        'active' => true
                    ],
                ],
                'buttons' => [
                    'save_message' => [
                        'link'       => 'javascript:;',
                        'title'      => __('admin_save'),
                        'type' => 'action',
                    ],
                ],
            ]
        );
    }

    /**
     * Edit message process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function editProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $post_data = $request->getParsedBody();
        // Get request query params
        $params = $request->getQueryParams();

        $data = $post_data['data'];
        $id  = (!empty($post_data['id']))?$post_data['id']:$params['id'];
        $mailbox = (!empty($post_data['mailbox']))?$post_data['mailbox']:$params['mailbox'];

        if (Filesystem::write(PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $id . $this->message_filepath, $data)) {
            $this->flash->addMessage('success', __('mailboxes_admin_message_msg_saved'));
        } else {
            $this->flash->addMessage('error', __('mailboxes_admin_message_msg_was_not_saved'));
        }

        return $response->withRedirect($this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox);
    }


    /**
     * Delete messages process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function deleteProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $post_data = $request->getParsedBody();
        // Get request query params
        $params = $request->getQueryParams();

        $id  = (!empty($post_data['id']))?$post_data['id']:$params['id'];
        $mailbox = (!empty($post_data['mailbox']))?$post_data['mailbox']:$params['mailbox'];

        $path = PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $id;
        $file_path = $path . $this->message_filepath;

        if (Filesystem::has($file_path) && Filesystem::delete($file_path) && Filesystem::has($path) && Filesystem::deleteDir($path)) {
            $this->flash->addMessage('success', __('mailboxes_admin_message_msg_deleted'));
        } else {
            $this->flash->addMessage('error', __('mailboxes_admin_message_msg_was_not_deleted'));
        }

        return $response->withRedirect($this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox);
    }

    /**
     * Duplicate message process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function duplicateProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $post_data = $request->getParsedBody();
        // Get request query params
        $params = $request->getQueryParams();

        $id  = (!empty($post_data['id']))?$post_data['id']:$params['id'];
        $mailbox = (!empty($post_data['mailbox']))?$post_data['mailbox']:$params['mailbox'];

        $file_path = PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $id . $this->message_filepath;
        $data = Filesystem::read($file_path);

        $message = $this->serializer->decode($data, 'frontmatter');
        $message['uuid'] = Uuid::uuid4()->toString();
        $message['created_at'] = (string) date($this->registry->get('flextype.settings.date_format'), time());
        $message['created_by'] = Session::get('uuid');
        $md = $this->serializer->encode($message, 'frontmatter');

        $path = PATH['project'] . $this->mailboxes_path . '/' . $mailbox . '/' . $id . '-duplicate-' . date('Ymd_His');
        $file_path_new = $path . $this->message_filepath;

        if (! Filesystem::has($path)) Filesystem::createDir($path);

        if ($this->message_cleancopy && Filesystem::write($file_path_new, $md)) {
            $this->flash->addMessage('success', __('mailboxes_admin_message_msg_duplicated'));
        } elseif (Filesystem::copy($file_path, $file_path_new)) {
            $this->flash->addMessage('success', __('mailboxes_admin_message_msg_duplicated'));
        } else {
            $this->flash->addMessage('error', __('mailboxes_admin_message_msg_was_not_duplicated'));
        }

        return $response->withRedirect($this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox);
    }

}
