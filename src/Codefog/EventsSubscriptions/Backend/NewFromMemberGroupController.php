<?php

namespace Codefog\EventsSubscriptions\Backend;

use Codefog\EventsSubscriptions\EventConfig;
use Codefog\EventsSubscriptions\Exporter;
use Codefog\EventsSubscriptions\Model\SubscriptionModel;
use Codefog\EventsSubscriptions\Services;
use Codefog\EventsSubscriptions\Subscription\MemberSubscription;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Haste\Util\Format;
use NotificationCenter\Model\Notification;

class NewFromMemberGroupController
{
    /**
     * Run the controller
     *
     * @return string
     */
    public function run()
    {
        if (Input::get('key') !== 'subscriptions_newFromMemberGroup' || ($eventModel = CalendarEventsModel::findByPk(Input::get('id'))) === null) {
            Controller::redirect('contao?act=error');
        }

        System::loadLanguageFile('tl_calendar_events_subscription');

        $formSubmit = 'events-subscriptions-new-from-member-group';
        $memberGroups = $this->getMemberGroups($eventModel);

        // Process the form
        if (Input::post('FORM_SUBMIT') === $formSubmit) {
            $this->processForm($eventModel, $memberGroups);
        }

        return $this->createTemplate($eventModel, $formSubmit, $memberGroups)->parse();
    }

    /**
     * Create the template
     *
     * @param CalendarEventsModel $eventModel
     * @param string              $formSubmit
     * @param array               $memberGroups
     *
     * @return BackendTemplate
     */
    protected function createTemplate(CalendarEventsModel $eventModel, $formSubmit, array $memberGroups)
    {
        $eventData = [];

        // Format the event data
        foreach ($eventModel->row() as $k => $v) {
            $eventData[$k] = Format::dcaValue($eventModel::getTable(), $k, $v);;
        }

        $template = new BackendTemplate('be_events_subscriptions_new_from_member_group');
        $template->backUrl = Backend::getReferer();
        $template->message = Message::generate();
        $template->event = $eventData;
        $template->eventRaw = $eventModel->row();
        $template->action = Environment::get('request');
        $template->formSubmit = $formSubmit;

        $template->subscribableMemberGroups = new $GLOBALS['BE_FFL']['checkbox'](Widget::getAttributesFromDca([
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_subscription']['notification.subscribableMemberGroups'],
            'options' => $memberGroups['subscribable'],
            'eval' => ['multiple' => true],
        ], 'subscribable-member-groups', Input::post('subscribable-member-groups')));

        if (count($memberGroups['other']) > 0) {
            $template->otherMemberGroups = new $GLOBALS['BE_FFL']['checkbox'](Widget::getAttributesFromDca([
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_subscription']['notification.otherMemberGroups'],
                'options' => $memberGroups['other'],
                'eval' => ['multiple' => true],
            ], 'other-member-groups', Input::post('other-member-groups')));
        }

        return $template;
    }

    /**
     * Process the form
     *
     * @param CalendarEventsModel $eventModel
     * @param array               $memberGroups
     */
    protected function processForm(CalendarEventsModel $eventModel, array $memberGroups)
    {
        $memberGroupIds = [];

        // Assign subscribable member groups
        if (is_array($subscribableMemberGroupIds = Input::post('subscribable-member-groups')) && count($subscribableMemberGroupIds) > 0) {
            $memberGroupIds = array_merge($memberGroupIds, array_intersect(array_map('\intval', $subscribableMemberGroupIds), array_keys($memberGroups['subscribable'])));
        }

        // Assign subscribable other groups
        if (is_array($otherMemberGroupIds = Input::post('other-member-groups')) && count($otherMemberGroupIds) > 0) {
            $memberGroupIds = array_merge($memberGroupIds, array_intersect(array_map('\intval', $otherMemberGroupIds), array_keys($memberGroups['other'])));
        }

        if (count($memberGroupIds) === 0) {
            Message::addError($GLOBALS['TL_LANG']['tl_calendar_events_subscription']['newFromMemberGroup.noMembers']);
            Controller::reload();
        }

        $members = [];
        $time = Date::floorToMinute();
        $memberRecords = Database::getInstance()
            ->prepare("SELECT * FROM tl_member WHERE login=? AND (start='' OR start<=?) AND (stop='' OR stop>?) AND disable='' AND id NOT IN (SELECT member FROM tl_calendar_events_subscription WHERE pid=?)")
            ->execute(1, $time, $time + 60, $eventModel->id)
        ;

        // Get only the members that belong to selected groups
        while ($memberRecords->next()) {
            if (count(array_intersect($memberGroupIds, StringUtil::deserialize($memberRecords->groups, true))) > 0) {
                $members[] = $memberRecords->row();
            }
        }

        // No recipients
        if (count($members) === 0) {
            Message::addInfo($GLOBALS['TL_LANG']['tl_calendar_events_subscription']['newFromMemberGroup.noMembers']);
            Controller::reload();
        }

        $count = 0;
        $eventConfig = new EventConfig($eventModel->getRelated('pid'), $eventModel);
        $factory = Services::getSubscriptionFactory();
        $subscriber = Services::getSubscriber();

        // Create the subscriptions
        foreach ($members as $member) {
            $memberModel = new MemberModel();
            $memberModel->setRow($member);

            /** @var MemberSubscription $subscription */
            $subscription = $factory->create('member');
            $subscription->setMemberModel($memberModel);

            $subscriber->subscribe($eventConfig, $subscription);

            $count++;
        }

        Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['tl_calendar_events_subscription']['newFromMemberGroup.confirmation'], $count));
        Controller::redirect(Backend::getReferer());
    }

    /**
     * Get the member groups
     *
     * @param CalendarEventsModel $eventModel
     *
     * @return array
     */
    protected function getMemberGroups(CalendarEventsModel $eventModel)
    {
        $groups = ['subscribable' => [], 'other' => []];
        $eventConfig = new EventConfig($eventModel->getRelated('pid'), $eventModel);
        $subscribableGroups = $eventConfig->hasMemberGroupsLimit() ? $eventConfig->getMemberGroups() : null;

        if (($models = MemberGroupModel::findAllActive(['order' => 'name'])) !== null) {
            /** @var MemberGroupModel $model */
            foreach ($models as $model) {
                $key = 'subscribable';

                if (isset($subscribableGroups) && !in_array($model->id, $subscribableGroups)) {
                    $key = 'other';
                }

                $groups[$key][(int) $model->id] = $model->name;
            }
        }

        return $groups;
    }
}
