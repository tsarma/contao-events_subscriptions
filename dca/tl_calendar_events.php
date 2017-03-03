<?php

/**
 * events_subscriptions extension for Contao Open Source CMS
 *
 * Copyright (C) 2013 Codefog
 *
 * @package events_subscriptions
 * @author  Codefog <http://codefog.pl>
 * @author  Kamil Kuzminski <kamil.kuzminski@codefog.pl>
 * @license LGPL
 */

/**
 * Add a child table
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ctable'][] = 'tl_calendar_events_subscription';

/**
 * Register global callbacks
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = [
    'Codefog\EventsSubscriptions\DataContainer\EventsContainer',
    'extendPalette',
];

/**
 * Add list operations
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['subscriptions'] = [
    'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['subscriptions'],
    'href'            => 'table=tl_calendar_events_subscription',
    'icon'            => 'mgroup.gif',
    'button_callback' => ['Codefog\EventsSubscriptions\DataContainer\EventsContainer', 'getSubscriptionsButton'],
];

/**
 * Add palettes
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][]           = 'subscription_override';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['subscription_override'] = 'subscription_maximum,subscription_subscribeEndTime,subscription_unsubscribeEndTime';

/**
 * Add fields
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_override'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['subscription_override'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_maximum'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['subscription_maximum'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_subscribeEndTime'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['subscription_subscribeEndTime'],
    'exclude'   => true,
    'inputType' => 'timePeriod',
    'options'   => ['seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years'],
    'reference' => &$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_timeRef'],
    'eval'      => ['rgxp' => 'natural', 'minval' => 1, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_unsubscribeEndTime'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['subscription_unsubscribeEndTime'],
    'exclude'   => true,
    'inputType' => 'timePeriod',
    'options'   => ['seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years'],
    'reference' => &$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['subscription_timeRef'],
    'eval'      => ['rgxp' => 'natural', 'minval' => 1, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];
