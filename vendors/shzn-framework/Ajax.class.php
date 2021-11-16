<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace SHZN\core;

/**
 * Generic Ajax Handler for all WOModules
 *
 * Required parameters:
 * mod-action: action to execute
 * mod: module slug
 */
class Ajax
{
    private $context;

    public function __construct($context)
    {
        $this->context = $context;

        add_action("wp_ajax_{$this->context}", array($this, 'ajax_handler'), 10, 1);
    }

    /**
     * Note that nonce needs to be verified by each module
     */
    public function ajax_handler()
    {
        if (!isset($_REQUEST['mod']))
            return;

        $request = array_merge(array(
            'mod'        => 'none',
            'mod_nonce'  => '',
            'mod_action' => 'none',
            'mod_args'   => '',
            'mod_form'   => ''
        ), $_REQUEST);

        if (!empty($request['mod_nonce']) and !UtilEnv::verify_nonce("{$this->context}-ajax-nonce", $request['mod_nonce'])) {
            wp_send_json_error(array(
                'response' => __('Ajax Error: It seems that you are not allowed to do this request.', $this->context),
            ));
        }

        $action = sanitize_text_field($request['mod_action']);

        $object = shzn($this->context)->moduleHandler->get_module_instance(sanitize_text_field($request['mod']));

        $args = array(
            'action'    => $action,
            'nonce'     => $request['mod_nonce'],
            'options'   => $request['mod_args'],
            'form_data' => $request['mod_form'],
        );

        if (!is_null($object)) {

            if ($object->restricted_access('ajax')) {
                wp_send_json_error(array(
                    'response' => __('Ajax Error: It seems that you are not allowed to do this request.', $this->context),
                ));
            }

            $object->ajax_handler($args);
        }
        else {
            wp_send_json_error(
                array(
                    'error' => __('Ajax Error: wrong ajax request.', $this->context),
                )
            );
        }

    }
}