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
    protected $mailbox_filepath = '/mailbox.yaml';
    protected $message_filepath = '/message.yaml';
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
            $mailbox_data = $this->serializer->decode(Filesystem::read($mailbox_file), 'yaml');
        }

        $messages = [];
        $messages_list = Filesystem::listContents(PATH['project'] . $this->mailboxes_path . '/' . $mailbox);

        if (count($messages_list) > 0) {
            foreach ($messages_list as $message) {
                $file = $message['path'] . $this->message_filepath;
                if ($message['type'] == 'dir' && Filesystem::has($file)) {
                    $data = Filesystem::read($file);
                    $mess = $this->serializer->decode($data, 'yaml');
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
                    ]
                ]
            ]
        );
    }

    /**
     * Preview page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function preview(/** @scrutinizer ignore-unused */ Request $request, Response $response) : Response
    {
        // Get mailbox from request query params
        $mailbox    = $request->getQueryParams()['mailbox'];
        $message_id = $request->getQueryParams()['id'];

        $mailbox_data = $this->serializer->decode(Filesystem::read(PATH['project'] . '/mailboxes/' . $mailbox . '/' . $message_id . '/message.yaml'), 'yaml');

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/messages/preview.html',
            [
                'menu_item' => 'mailboxes',
                'mailbox' => $request->getQueryParams()['mailbox'],
                'id' => $request->getQueryParams()['id'],
                'mailbox_data' => $mailbox_data,
                'links' =>  [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),
                    ],
                    'messages' => [
                        'link' => $this->router->pathFor('admin.messages.index') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_messages')
                    ],
                    'preview' => [
                        'link' => $this->router->pathFor('admin.messages.preview') . '?mailbox=' . $mailbox,
                        'title' => __('mailboxes_admin_messages_preview'),
                        'active' => true
                    ],
                ]
            ]
        );
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
}
