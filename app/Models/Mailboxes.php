<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Filesystem\Filesystem;
use RuntimeException;
use function array_merge;
use function array_replace_recursive;
use function count;
use function filemtime;
use function is_array;
use function md5;

class Mailboxes
{
    /**
     * Flextype Dependency Container
     */
    private $flextype;
    protected $config_path = '/mailboxes';
    protected $mailboxes_path = '/mailboxes';
    protected $mailbox_filepath = '/mailbox.md';
    protected $message_filepath = '/message.md';
    protected $settings_filepath = '/settings.yaml';

    /**
     * Private construct method to enforce singleton behavior.
     *
     * @access private
     */
    public function __construct($flextype)
    {
        $this->flextype = $flextype;
    }

    /**
     * Init Mailboxes
     */
    public function init($flextype, $app) : void
    {
        // Set empty Mailboxes list item
        $this->flextype['registry']->set('mailboxes', []);

        // Get Mailboxes list
        $mailboxes_list = $this->getMailboxes();

        // If Mailboxes List isnt empty then continue
        if (! is_array($mailboxes_list) || count($mailboxes_list) <= 0) {
            return;
        }

        // Get Mailboxes cache ID
        $mailboxes_cache_id = $this->getMailboxesCacheID($mailboxes_list);

        // Get mailboxes list from cache or scan mailboxes folder and create new mailboxes cache item in the registry
        if ($this->flextype['cache']->contains($mailboxes_cache_id)) {
            $this->flextype['registry']->set('mailboxes', $this->flextype['cache']->fetch($mailboxes_cache_id));
        } else {
            $mailboxes                  = [];
            $mailboxes_settings         = [];
            $mailboxes_manifest         = [];
            $default_mailbox_settings   = [];
            $site_mailbox_settings      = [];
            $default_mailbox_manifest   = [];

            // Go through the mailboxes list...
            foreach ($mailboxes_list as $mailbox) {

                // Set default mailbox settings and manifest files
                $default_mailbox_manifest_file = PATH['project'] . $this->mailboxes_path . '/' . $mailbox['dirname'] . $this->mailbox_filepath;

                // Check if default mailbox manifest file exists
                if (! Filesystem::has($default_mailbox_manifest_file)) {
                    RuntimeException('Load ' . $mailbox['dirname'] . ' mailbox manifest - failed!');
                }

                // Get default mailbox manifest content
                $default_mailbox_manifest_file_content = Filesystem::read($default_mailbox_manifest_file);
                $default_mailbox_manifest              = $this->flextype['serializer']->decode($default_mailbox_manifest_file_content, 'md');

                // Merge mailbox settings and manifest data
                $mailboxes[$mailbox['dirname']]['manifest'] = $default_mailbox_manifest;
                $mailboxes[$mailbox['dirname']]['settings'] = $default_mailbox_settings;

            }

            // Save parsed mailboxes list in the registry mailboxes
            $this->flextype['registry']->set('mailboxes', $mailboxes);

            // Save parsed mailboxes list in the cache
            $this->flextype['cache']->save($mailboxes_cache_id, $mailboxes);
        }

        // Emit onMailboxesInitialized
        $this->flextype['emitter']->emit('onMailboxesInitialized');
    }

    /**
     * Get Mailboxes Cache ID
     *
     * @param  array $mailboxes_list Mailboxes list
     *
     * @access protected
     */
    private function getMailboxesCacheID(array $mailboxes_list) : string
    {
        // Mailboxes Cache ID
        $_mailboxes_cache_id = '';

        // Go through...
        if (is_array($mailboxes_list) && count($mailboxes_list) > 0) {
            foreach ($mailboxes_list as $mailbox) {
                $default_mailbox_settings_file = PATH['project'] . $this->mailboxes_path . '/' . $mailbox['dirname'] . $this->settings_filepath;
                $default_mailbox_manifest_file = PATH['project'] . $this->mailboxes_path . '/' . $mailbox['dirname'] . $this->mailbox_filepath;
                $site_mailbox_settings_file    = PATH['project'] . $this->config_path. '/' . $mailbox['dirname'] . $this->settings_filepath;

                $f1 = Filesystem::has($default_mailbox_settings_file) ? filemtime($default_mailbox_settings_file) : '';
                $f2 = Filesystem::has($default_mailbox_manifest_file) ? filemtime($default_mailbox_manifest_file) : '';
                $f3 = Filesystem::has($site_mailbox_settings_file) ? filemtime($site_mailbox_settings_file) : '';

                $_mailboxes_cache_id .= $f1 . $f2 . $f3;
            }
        }

        // Create Unique Cache ID for Mailboxes
        $mailboxes_cache_id = md5('mailboxes' . PATH['project'] . $this->mailboxes_path . '/' . $_mailboxes_cache_id);

        // Return mailboxes cache id
        return $mailboxes_cache_id;
    }

    /**
     * Get list of mailboxes
     *
     * @return array
     *
     * @access public
     */
    public function getMailboxes() : array
    {
        // Init mailboxes list
        $mailboxes_list = [];

        // Get mailboxes list
        $_mailboxes_list = Filesystem::listContents(PATH['project'] . $this->mailboxes_path);

        // Go through found mailboxes
        foreach ($_mailboxes_list as $mailbox) {
            if ($mailbox['type'] !== 'dir' || ! Filesystem::has($mailbox['path'] . $this->mailbox_filepath)) {
                continue;
            }
            $mailboxes_list[] = $mailbox;
        }

        return $mailboxes_list;
    }

    /**
     * Get messages for mailbox
     *
     * @param string $mailbox Mailbox id
     *
     * @return array
     *
     * @access public
     */
    public function getMessages(string $mailbox) : array
    {
        // Init messages list
        $messages_list = [];

        // Get messages files
        $_messages_list = Filesystem::listContents(PATH['project'] . $this->mailboxes_path. '/' . $mailbox);

        // If there is any message file then go...
        if (count($_messages_list) > 0) {
            foreach ($_messages_list as $message) {
                if ($message['type'] !== 'dir' || ! Filesystem::has($message['path'] . $this->message_filepath)) {
                    continue;
                }
                $messages_list[] = $message;
            }
        }

        // return messages
        return $messages_list;
    }
}
