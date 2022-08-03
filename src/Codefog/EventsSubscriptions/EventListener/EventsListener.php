<?php

/**
 * events_subscriptions extension for Contao Open Source CMS
 *
 * @copyright Copyright (c) 2011-2017, Codefog
 * @author    Codefog <https://codefog.pl>
 * @license   http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link      http://github.com/codefog/contao-events_subscriptions
 */

namespace Codefog\EventsSubscriptions\EventListener;

use Codefog\EventsSubscriptions\EventConfig;
use Codefog\EventsSubscriptions\FrontendModule\SubscriptionTrait;
use Codefog\EventsSubscriptions\Model\SubscriptionModel;
use Codefog\EventsSubscriptions\Services;
use Codefog\EventsSubscriptions\Subscription\MemberSubscription;
use Contao\FrontendUser;
use Contao\Module;

class EventsListener
{
    use SubscriptionTrait;

    /**
     * On get all events
     */
    public function onGetAllEvents(array $allEvents, array $calendars, $start, $end, Module $module)
    {
        $factory = Services::getSubscriptionFactory();

        foreach ($allEvents as $k => $periods) {
            foreach ($periods as $kk => $events) {
                foreach ($events as $kkk => $event) {
                    if (!isset($event['id'])) {
                        continue;
                    }

                    $config = EventConfig::create($event['id']);

                    // Set the subscription template data for calendar module
                    if ($module->type === 'calendar') {
                        foreach ($this->getSubscriptionTemplateData($config, $module->getModel()->row()) as $field => $value) {
                            $allEvents[$k][$kk][$kkk][$field] = $value;
                        }
                    }

                    // Add extra CSS classes
                    foreach ($config->getAllowedSubscriptionTypes() as $type) {
                        try {
                            $subscription = $factory->create($type);
                        } catch (\InvalidArgumentException $e) {
                            continue;
                        }

                        $class = '';

                        // Add CSS class if user is subscribed
                        if ($subscription->isSubscribed($config)) {
                            $class .= ' subscribed';

                            // Add CSS class if user is on a waiting list
                            if ($subscription instanceof MemberSubscription && FE_USER_LOGGED_IN) {
                                $subscriptionModel = SubscriptionModel::findByPidAndMember($event['id'], FrontendUser::getInstance()->id);

                                if ($subscriptionModel !== null) {
                                    $subscription->setSubscriptionModel($subscriptionModel);

                                    if ($subscription->isOnWaitingList()) {
                                        $class .= ' subscribed-waiting-list';
                                    }
                                }
                            }
                        }

                        // Add CSS class if user can subscribe
                        if ($subscription->canSubscribe($config)) {
                            $class .= ' can-subscribe';
                        }

                        $allEvents[$k][$kk][$kkk]['class'] .= $class;
                    }
                }
            }
        }

        return $allEvents;
    }
}
