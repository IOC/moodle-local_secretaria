<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'local/secretaria:manage' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(),
    ),

);
