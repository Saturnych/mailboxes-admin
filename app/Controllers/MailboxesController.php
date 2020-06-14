<?php

declare(strict_types=1);

namespace Flextype;

use Flextype\Component\Arr\Arr;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Session\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use function array_merge;
use function array_replace_recursive;
use function count;
use function Flextype\Component\I18n\__;
use function is_array;
use function trim;

/**
 * @property twig $twig
 * @property Router $router
 * @property Cache $cache
 * @property Mailboxes $mailboxes
 */
class MailboxesController extends Container
{
    protected $plugin_path = 'plugins/mailboxes-admin';
    protected $template_path = '/templates/extends/mailboxes';
    protected $mailboxes_path = '/mailboxes';
    protected $mailbox_filepath = '/mailbox.md';

    /**
     * Index page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function index(/** @scrutinizer ignore-unused */ Request $request, Response $response) : Response
    {
        $mailboxes = [];
        $mailboxes_list = Filesystem::listContents(PATH['project'] . $this->mailboxes_path);

        if (count($mailboxes_list) > 0) {
            foreach ($mailboxes_list as $mailbox) {
                $file = $mailbox['path'] . $this->mailbox_filepath;
                if ($mailbox['type'] == 'dir' && Filesystem::has($file)) {
                    $data = Filesystem::read($file);
                    $mess = $this->serializer->decode($data, 'frontmatter');
                    $mailbox = array_merge($mailbox,$mess);
                    $mailboxes[] = $mailbox;
                }
            }
        }

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/index.html',
            [
                'menu_item' => 'mailboxes',
                'mailboxes_list' => $mailboxes,
                'links' =>  [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),
                        'active' => true
                    ],
                ],
                'buttons' => [
                    'mailboxes_get_more' => [
                        'link' => $this->router->pathFor('admin.mailboxes.add'),
                        'title' => __('mailboxes_admin_mailboxes_add'),
                        'active' => true
                    ],
                ],
            ]
        );
    }
    /**
     * Add mailbox
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function add(/** @scrutinizer ignore-unused */ Request $request, Response $response) : Response
    {

        return $this->twig->render(
            $response,
            $this->plugin_path . $this->template_path. '/add.html',
            [
                'menu_item' => 'mailboxes',
                'links' =>  [
                    'mailboxes' => [
                        'link' => $this->router->pathFor('admin.mailboxes.index'),
                        'title' => __('mailboxes_admin_mailboxes'),
                    ],
                    'mailboxes_add' => [
                        'link' => $this->router->pathFor('admin.mailboxes.add'),
                        'title' => __('mailboxes_admin_create_new_mailbox'),
                        'active' => true
                    ],
                ],
            ]
        );
    }

    /**
     * Add mailbox process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function addProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $post_data = $request->getParsedBody();
        $id = mb_strtolower($post_data['id']);

        // Generate UUID
        $mailbox['uuid'] = Uuid::uuid4()->toString();
        $mailbox['title'] = $post_data['title'];
        $mailbox['created_at'] = (string) date($this->registry->get('flextype.settings.date_format'), time());
        $mailbox['created_by'] = Session::get('uuid');

        $path = PATH['project'] . $this->mailboxes_path . '/' . $id;

        if (! Filesystem::has($path)) Filesystem::createDir($path);

        $md = $this->serializer->encode($mailbox, 'frontmatter');
        $file = $path . $this->mailbox_filepath;

        if (! Filesystem::has($file)) {
            if (Filesystem::write($file,$md)) {
                $this->flash->addMessage('success', __('mailboxes_admin_message_mailbox_created'));
            } else {
                $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_created'));
            }
        } else {
            $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_created'));
        }

        if (isset($post_data['create-and-edit'])) {
            return $response->withRedirect($this->router->pathFor('admin.mailboxes.edit') . '?id=' . $id);
        }

        return $response->withRedirect($this->router->pathFor('admin.mailboxes.index'));
    }


        /**
         * Edit message
         *
         * @param Request  $request  PSR7 request
         * @param Response $response PSR7 response
         */
        public function edit(Request $request, Response $response) : Response
        {
            // Get type and mailbox from request query params
            $params = $request->getQueryParams();
            $id  = $params['id'];

            return $this->twig->render(
                $response,
                $this->plugin_path . $this->template_path. '/edit.html',
                [
                    'menu_item' => 'mailboxes',
                    'id' => $id,
                    'data' => Filesystem::read(PATH['project'] . $this->mailboxes_path . '/' . $id . $this->mailbox_filepath),
                    'type' => 'mailbox',
                    'links' => [
                        'mailboxes' => [
                            'link' => $this->router->pathFor('admin.mailboxes.index'),
                            'title' => __('mailboxes_admin_mailboxes'),
                        ],
                        'mailboxes_editor' => [
                            'link' => $this->router->pathFor('admin.mailboxes.edit') . '?id=' . $id,
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

            $id  = (!empty($post_data['id']))?$post_data['id']:$params['id'];
            $data = $post_data['data'];
            $mailbox = $this->serializer->decode($data, 'frontmatter');

            if (!empty($mailbox['id']) && $id != $mailbox['id']) {
              Filesystem::rename(PATH['project'] . $this->mailboxes_path . '/' . $id, PATH['project']. $this->mailboxes_path . '/' . $mailbox['id']);
              $id = $mailbox['id'];
            }

            if (Filesystem::write(PATH['project'] . $this->mailboxes_path . '/' . $id . $this->mailbox_filepath, $data)) {
                $this->flash->addMessage('success', __('mailboxes_admin_message_mailbox_saved'));
            } else {
                $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_saved'));
            }

            return $response->withRedirect($this->router->pathFor('admin.mailboxes.index'));
        }

        /**
         * Rename mailbox
         *
         * @param Request  $request  PSR7 request
         * @param Response $response PSR7 response
         */
        public function rename(Request $request, Response $response) : Response
        {
            // Get mailbox from request query params
            $id = $request->getQueryParams()['id'];

            return $this->twig->render(
                $response,
                $this->plugin_path . $this->template_path. '/rename.html',
                [
                    'menu_item' => 'mailboxes',
                    'id_current' => $id,
                    'links' => [
                        'mailboxes' => [
                            'link' => $this->router->pathFor('admin.mailboxes.index'),
                            'title' => __('mailboxes_admin_mailboxes'),
                        ],
                        'mailboxes_rename' => [
                            'link' => $this->router->pathFor('admin.mailboxes.rename') . '?id=' . $id,
                            'title' => __('admin_rename'),
                            'active' => true
                        ],
                    ],
                ]
            );
        }

        /**
         * Rename message process
         *
         * @param Request  $request  PSR7 request
         * @param Response $response PSR7 response
         */
        public function renameProcess(Request $request, Response $response) : Response
        {
            // Get data from POST
            $post_data = $request->getParsedBody();

            $id = mb_strtolower($post_data['id']);
            $id_current = $post_data['id_current'];

            if (Filesystem::has(PATH['project'] . $this->mailboxes_path . '/' . $id_current) && !Filesystem::has(PATH['project'] . '/mailboxes/' . $id)) {
                if (Filesystem::rename(PATH['project'] . $this->mailboxes_path . '/' .$id_current.'/', PATH['project']. $this->mailboxes_path . '/' . $id . '/')) {
                    $this->flash->addMessage('success', __('mailboxes_admin_message_mailbox_renamed'));
                } else {
                    $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_renamed'));
                }
            } else {
                $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_renamed !'));
            }

            return $response->withRedirect($this->router->pathFor('admin.mailboxes.index'));
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

            $path = PATH['project'] . $this->mailboxes_path . '/' . $id;
            $file_path = $path . $this->mailbox_filepath;

            $files_list = Filesystem::listContents($path);
            if (count($files_list) > 0) {
                foreach ($files_list as $dir) {
                    $file = $dir['path'] . $this->mailbox_filepath;
                    if ($dir['type'] == 'dir' && Filesystem::has($file))
                      Filesystem::delete($file);
                    elseif (Filesystem::has($dir['path']))
                      Filesystem::deleteDir($dir['path']);
                }
            }

            if (Filesystem::delete($file_path) && Filesystem::deleteDir($path)) {
                $this->flash->addMessage('success', __('mailboxes_admin_message_mailbox_deleted'));
            } else {
                $this->flash->addMessage('error', __('mailboxes_admin_message_mailbox_was_not_deleted'));
            }

            return $response->withRedirect($this->router->pathFor('admin.mailboxes.index'));
        }

}
