<?php
require_once $CFG->dirroot . '/local/obf/class/backpack.php';
require_once $CFG->dirroot . '/local/obf/class/badge.php';
require_once $CFG->dirroot . '/local/obf/renderer.php';

class block_obf_displayer extends block_base {
    public function init() {
        $this->title = get_string('obf_displayer', 'block_obf_displayer');
    }

    public function instance_allow_multiple() {
      return true;
    }

    public function get_content() {
        global $DB, $PAGE;
        if ($this->content !== null) {
            return $this->content;
        }
        $context = $PAGE->context;

        if ($context->contextlevel !== CONTEXT_USER || $PAGE->pagetype !== 'user-profile') {
            return false;
        }

        $userid = $context->instanceid;

        $assertions = $this->get_assertions($userid, $DB);

        if (!empty($this->config)) {
            if ($this->config->showmoz == false && $this->config->showobp == false && $this->config->showobf == true) {
                $this->title = get_string('blocktitleobf', 'block_obf_displayer');
            } else if ($this->config->showmoz == false && $this->config->showobp == true && $this->config->showobf == false) {
                $this->title = get_string('blocktitleobp', 'block_obf_displayer');
            } else if ($this->config->showmoz == true && $this->config->showobp == false && $this->config->showobf == false) {
                $this->title = get_string('blocktitlemoz', 'block_obf_displayer');
            } else {
                $this->title = get_string('blocktitle', 'block_obf_displayer');
            }
        } else {
            $this->title = get_string('blocktitle', 'block_obf_displayer');
        }

        $this->content =  new stdClass;
        $this->content->text = '';
        $renderer = $PAGE->get_renderer('local_obf');
        $large = !empty($this->config) && property_exists($this->config, 'largebadges') && $this->config->largebadges == true;
        if ($assertions !== false && count($assertions) > 0) {
            $this->content->text .= $renderer->render_user_assertions($assertions, $large);
        }
        $providers = obf_backpack::get_providers();
        foreach ($providers as $provider) {
            $assertions = $this->get_backpack_assertions($userid, $DB, $provider);
            if (count($assertions) > 0) {
                $this->content->text .= $renderer->render_user_assertions($assertions, $large);
            }
        }

        return $this->content;
    }
    private function get_assertions($userid, $db) {
        if (empty($this->config) || !property_exists($this->config, 'showobf') || $this->config->showobf) {
            $cache = cache::make('block_obf_displayer', 'obf_assertions');
            $assertions = $cache->get($userid);

            if (!$assertions) {
                // Get user's badges in OBF
                $assertions = new obf_assertion_collection();
                try {
                    $client = obf_client::get_instance();
                    $blacklist = new obf_blacklist($userid);
                    $assertions->add_collection(obf_assertion::get_assertions($client, null, $db->get_record('user', array('id' => $userid))->email ));
                    $assertions->apply_blacklist($blacklist);
                } catch(Exception $e) {
                    debugging('Getting OBF assertions for user id: ' . $userid . ' failed: ' . $e->getMessage());
                }

                $assertions->toArray(); // This makes sure issuer objects are populated and cached
                $cache->set($userid, $assertions );
            }
        } else {
            $assertions = new obf_assertion_collection();
        }
        return $assertions;
    }
    private function get_backpack_assertions($userid, $db, $provider) {
        $backpack = obf_backpack::get_instance_by_userid($userid, $db, $provider);
        if ($backpack === false || count($backpack->get_group_ids()) == 0) {
            return new obf_assertion_collection();
        }
        $showprop = 'show'.$backpack->get_providershortname();
        if (empty($this->config) || !property_exists($this->config, $showprop) || $this->config->{$showprop}) {
            $cache = cache::make('block_obf_displayer', 'obf_assertions_' . $backpack->get_providershortname());
            $assertions = $cache->get($userid);

            if (!$assertions) {
                // Get user's badges in OBF
                $assertions = new obf_assertion_collection();
                try {
                    // Also get user's badges in Backpack, if user has backpack settings
                    if ($backpack !== false && count($backpack->get_group_ids()) > 0) {
                        $assertions->add_collection( $backpack->get_assertions() );
                    }
                } catch(Exception $e) {
                    debugging('Getting backpack assertions for user id: ' . $userid . ' failed: ' . $e->getMessage());
                }

                $assertions->toArray(); // This makes sure issuer objects are populated and cached
                $cache->set($userid, $assertions );
            }
        } else {
            $assertions = new obf_assertion_collection();
        }
        return $assertions;
    }
    public function html_attributes() {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' local-obf';
        return $attributes;
    }
}
