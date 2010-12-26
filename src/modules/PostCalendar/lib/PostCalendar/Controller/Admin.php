<?php
/**
 * @package     PostCalendar
 * @copyright   Copyright (c) 2002, The PostCalendar Team
 * @copyright   Copyright (c) 2009, Craig Heydenburg, Sound Web Development
 * @license     http://www.gnu.org/copyleft/gpl.html GNU General Public License
 */

class PostCalendar_Controller_Admin extends Zikula_Controller
{
    /**
     * This function is the default function, and is called whenever the
     * module is initiated without defining arguments.
     */
    public function main()
    {
        return $this->modifyconfig();
    }
    
    /**
     * @desc present administrator options to change module configuration
     * @return string config template
     */
    public function modifyconfig()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
    
        $modinfo = ModUtil::getInfo(ModUtil::getIdFromName('PostCalendar'));
        $this->view->assign('postcalendarversion', $modinfo['version']);
    
        $this->view->assign('pcFilterYearStart', ModUtil::getVar('PostCalendar', 'pcFilterYearStart', 1));
        $this->view->assign('pcFilterYearEnd', ModUtil::getVar('PostCalendar', 'pcFilterYearEnd', 2));
    
        return $this->view->fetch('admin/modifyconfig.tpl');
    }
    
    /**
     * @desc list events as requested/filtered
     *              send list to template
     * @return string showlist template
     */
    public function listevents(array $args)
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_DELETE)) {
            return LogUtil::registerPermissionError();
        }
    
        $listtype = isset($args['listtype']) ? $args['listtype'] : FormUtil::getPassedValue('listtype', _EVENT_APPROVED);
        switch ($listtype) {
            case _EVENT_HIDDEN:
                $functionname = "hidden";
                $title = $this->__('Hidden events administration');
                break;
            case _EVENT_QUEUED:
                $functionname = "queued";
                $title = $this->__('Queued events administration');
                break;
            case _EVENT_APPROVED:
            default:
                $functionname = "approved";
                $title = $this->__('Approved events administration');
            }
    
        $offset_increment = _SETTING_HOW_MANY_EVENTS;
        if (empty($offset_increment)) {
            $offset_increment = 15;
        }
        $sortcolclasses = array(
            'title' => 'z-order-unsorted',
            'time'  => 'z-order-unsorted');
    
        $offset = FormUtil::getPassedValue('offset', 0);
        $sort   = FormUtil::getPassedValue('sort', 'time');
        $sdir   = FormUtil::getPassedValue('sdir', 1);
        $original_sdir = $sdir;
        $sdir = $sdir ? 0 : 1; //if true change to false, if false change to true
        if ($sdir == 0) {
            $sortcolclasses[$sort] = 'z-order-desc';
            $sort .= ' DESC';
        }
        if ($sdir == 1) {
            $sortcolclasses[$sort] = 'z-order-asc';
            $sort .= ' ASC';
        }
        $this->view->assign('sortcolclasses', $sortcolclasses);
    
        $events = DBUtil::selectObjectArray('postcalendar_events', "WHERE pc_eventstatus=" . $listtype, $sort, $offset, $offset_increment, false);
        $events = $this->_appendObjectActions($events);
    
        $this->view->assign('title', $title);
        $this->view->assign('functionname', $functionname);
        $this->view->assign('events', $events);
        $this->view->assign('title_sort_url', ModUtil::url('PostCalendar', 'admin', 'listevents', array(
            'listtype' => $listtype,
            'sort' => 'title',
            'sdir' => $sdir)));
        $this->view->assign('time_sort_url', ModUtil::url('PostCalendar', 'admin', 'listevents', array(
            'listtype' => $listtype,
            'sort' => 'time',
            'sdir' => $sdir)));
        $this->view->assign('formactions', array(
            _ADMIN_ACTION_VIEW    => $this->__('View'),
            _ADMIN_ACTION_APPROVE => $this->__('Approve'),
            _ADMIN_ACTION_HIDE    => $this->__('Hide'),
            _ADMIN_ACTION_DELETE  => $this->__('Delete')));
        $this->view->assign('actionselected', _ADMIN_ACTION_VIEW);
        $this->view->assign('listtypes', array(
            _EVENT_APPROVED => $this->__('Approved Events'),
            _EVENT_HIDDEN   => $this->__('Hidden Events'),
            _EVENT_QUEUED   => $this->__('Queued Events')));
        $this->view->assign('listtypeselected', $listtype);
        if ($offset > 1) {
            $prevlink = ModUtil::url('PostCalendar', 'admin', 'listevents', array(
                'listtype' => $listtype,
                'offset' => $offset - $offset_increment,
                'sort' => $sort,
                'sdir' => $original_sdir));
        } else {
            $prevlink = false;
        }
        $this->view->assign('prevlink', $prevlink);
        if (count($events) >= $offset_increment) {
            $nextlink = ModUtil::url('PostCalendar', 'admin', 'listevents', array(
                'listtype' => $listtype,
                'offset' => $offset + $offset_increment,
                'sort' => $sort,
                'sdir' => $original_sdir));
        } else {
            $nextlink = false;
        }
        $this->view->assign('nextlink', $nextlink);
        $this->view->assign('offset_increment', $offset_increment);
    
        return $this->view->fetch('admin/showlist.tpl');
    }
    
    /**
     * @desc allows admin to revue selected events then take action
     * @return string html template adminrevue template
     */
    public function adminevents()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_DELETE)) {
            return LogUtil::registerPermissionError();
        }
    
        $action = FormUtil::getPassedValue('action');
        $events = FormUtil::getPassedValue('events'); // could be an array or single val
    
        if (!isset($events)) {
            LogUtil::registerError($this->__('Please select an event.'));
            // return to where we came from
            return $this->listevents(array('listtype' => _EVENT_QUEUED));
        }
    
        if (!is_array($events)) {
            $events = array(
                $events);
        } //create array if not already
    
        foreach ($events as $eid) {
            // get event info
            $eventitems = DBUtil::selectObjectByID('postcalendar_events', $eid, 'eid');
            $eventitems = ModUtil::apiFunc('PostCalendar', 'event', 'formateventarrayfordisplay', $eventitems);
            $alleventinfo[$eid] = $eventitems;
        }
    
        $count = count($events);
        $texts = array(
            _ADMIN_ACTION_VIEW => "view",
            _ADMIN_ACTION_APPROVE => "approve",
            _ADMIN_ACTION_HIDE => "hide",
            _ADMIN_ACTION_DELETE => "delete");

        $this->view->assign('actiontext', $texts[$action]);
        $this->view->assign('action', $action);
        $are_you_sure_text = $this->_fn('Do you really want to %s this event?', 'Do you really want to %s these events?', $count, $texts[$action]);
        $this->view->assign('areyousure', $are_you_sure_text);
        $this->view->assign('alleventinfo', $alleventinfo);
    
        return $this->view->fetch("admin/eventrevue.tpl");
    }
    
    /**
     * @desc reset all module variables to default values as defined in pninit.php
     * @return      status/error ->back to modify config page
     */
    public function resetDefaults()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
    
        $defaults = PostCalendar_Util::getdefaults();
        if (!count($defaults)) {
            return LogUtil::registerError($this->__('Error! Could not load default values.'));
        }
    
        // delete all the old vars
        ModUtil::delVar('PostCalendar');
    
        // set the new variables
        ModUtil::setVars('PostCalendar', $defaults);
    
        // clear the cache
        $this->view->clear_cache();
    
        LogUtil::registerStatus($this->__('Done! PostCalendar configuration reset to use default values.'));
        return $this->modifyconfig();
    }
    
    /**
     * @desc sets module variables as requested by admin
     * @return      status/error ->back to modify config page
     */
    public function updateconfig()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
    
        $defaults = PostCalendar_Util::getdefaults();
        if (!count($defaults)) {
            return LogUtil::registerError($this->__('Error! Could not load default values.'));
        }
    
        $settings = array(
            'pcTime24Hours'           => FormUtil::getPassedValue('pcTime24Hours',               0),
            'pcEventsOpenInNewWindow' => FormUtil::getPassedValue('pcEventsOpenInNewWindow',     0),
            'pcFirstDayOfWeek'        => FormUtil::getPassedValue('pcFirstDayOfWeek',            $defaults['pcFirstDayOfWeek']),
            'pcUsePopups'             => FormUtil::getPassedValue('pcUsePopups',                 0),
            'pcAllowDirectSubmit'     => FormUtil::getPassedValue('pcAllowDirectSubmit',         0),
            'pcListHowManyEvents'     => FormUtil::getPassedValue('pcListHowManyEvents',         $defaults['pcListHowManyEvents']),
            'pcEventDateFormat'       => FormUtil::getPassedValue('pcEventDateFormat',           $defaults['pcEventDateFormat']),
            'pcAllowUserCalendar'     => FormUtil::getPassedValue('pcAllowUserCalendar',         0),
            'pcTimeIncrement'         => FormUtil::getPassedValue('pcTimeIncrement',             $defaults['pcTimeIncrement']),
            'pcDefaultView'           => FormUtil::getPassedValue('pcDefaultView',               $defaults['pcDefaultView']),
            'pcNotifyAdmin'           => FormUtil::getPassedValue('pcNotifyAdmin',               0),
            'pcNotifyEmail'           => FormUtil::getPassedValue('pcNotifyEmail',               $defaults['pcNotifyEmail']),
            'pcListMonths'            => abs((int) FormUtil::getPassedValue('pcListMonths',      $defaults['pcListMonths'])),
            'pcNotifyAdmin2Admin'     => FormUtil::getPassedValue('pcNotifyAdmin2Admin',         0),
            'pcAllowCatFilter'        => FormUtil::getPassedValue('pcAllowCatFilter',            0),
            'enablecategorization'    => FormUtil::getPassedValue('enablecategorization',        0),
            'enablenavimages'         => FormUtil::getPassedValue('enablenavimages',             0),
            'enablelocations'         => FormUtil::getPassedValue('enablelocations',             0),
            'pcFilterYearStart'       => abs((int) FormUtil::getPassedValue('pcFilterYearStart', $defaults['pcFilterYearStart'])), // ensures positive value
            'pcFilterYearEnd'         => abs((int) FormUtil::getPassedValue('pcFilterYearEnd',   $defaults['pcFilterYearEnd'])), // ensures positive value
            'pcNotifyPending'         => FormUtil::getPassedValue('pcNotifyPending',             0),
        );
        $settings['pcNavDateOrder'] = ModUtil::apiFunc('PostCalendar', 'admin', 'getdateorder', $settings['pcEventDateFormat']);
        // save out event default settings so they are not cleared
        $settings['pcEventDefaults'] = ModUtil::getVar('PostCalendar', 'pcEventDefaults');
    
        // delete all the old vars
        ModUtil::delVar('PostCalendar');
    
        // set the new variables
        ModUtil::setVars('PostCalendar', $settings);
    
        // Let any other modules know that the modules configuration has been updated
        //ModUtil::callHooks('module', 'updateconfig', 'PostCalendar', array(
        //    'module' => 'PostCalendar'));
        //$this->notifyHooks('postcalendar.hook.process.updateconfig', $this);
    
        // clear the cache
        $this->view->clear_cache();
    
        LogUtil::registerStatus($this->__('Done! Updated the PostCalendar configuration.'));
        return $this->modifyconfig();
    }
    
    /**
     * update status of events to approve, hide or delete
     * @return string html template
     */
    public function updateevents()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADD)) {
            return LogUtil::registerPermissionError();
        }

        $pc_eid = FormUtil::getPassedValue('pc_eid');
        $action = FormUtil::getPassedValue('action');
        if (!is_array($pc_eid)) {
            return $this->__("Error! An the eid must be passed as an array.");
        }
        $state = array (
            _ADMIN_ACTION_APPROVE => _EVENT_APPROVED,
            _ADMIN_ACTION_HIDE => _EVENT_HIDDEN,
            _ADMIN_ACTION_DELETE => 5); // just a random value for deleted

        // structure array for DB interaction
        $eventarray = array();
        foreach ($pc_eid as $eid) {
            $eventarray[$eid] = array(
                'eid' => $eid,
                'eventstatus' => $state[$action]); // field not used in delete action
        }
        $count = count($pc_eid);

        // update the DB
        switch ($action) {
            case _ADMIN_ACTION_APPROVE:
                $res = DBUtil::updateObjectArray($eventarray, 'postcalendar_events', 'eid');
                $words = array('approve', 'approved');
                break;
            case _ADMIN_ACTION_HIDE:
                $res = DBUtil::updateObjectArray($eventarray, 'postcalendar_events', 'eid');
                $words = array('hide', 'hidden');
                break;
            case _ADMIN_ACTION_DELETE:
                $res = DBUtil::deleteObjectsFromKeyArray($eventarray, 'postcalendar_events', 'eid');
                $words = array('delete', 'deleted');
                break;
        }
        if ($res) {
            LogUtil::registerStatus($this->_fn('Done! %1$s event %2$s.', 'Done! %1$s events %2$s.', $count, array($count, $words[1])));
        } else {
            LogUtil::registerError($this->_fn("Error! Could not %s event.", "Error! Could not %s events.", $count, $words[0]));
        }

        $this->view->clear_cache();
        return $this->listevents(array(
            'listtype' => _EVENT_APPROVED));
    }

    /**
     * @desc present administrator options to change event default values
     * @return string html template
     */
    public function modifyeventdefaults()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        $eventDefaults = ModUtil::getVar('PostCalendar', 'pcEventDefaults');
    
        // load the category registry util
        $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('PostCalendar', 'postcalendar_events');
        $this->view->assign('catregistry', $catregistry);
    
        $props = array_keys($catregistry);
        $this->view->assign('firstprop', $props[0]);
        $selectedDefaultCategories = $eventDefaults['categories'];
        $this->view->assign('selectedDefaultCategories', $selectedDefaultCategories);
    
        // convert duration to HH:MM
        $eventDefaults['endTime']  = ModUtil::apiFunc('PostCalendar', 'event', 'computeendtime', $eventDefaults);
    
        // sharing selectbox
        $this->view->assign('sharingselect', ModUtil::apiFunc('PostCalendar', 'event', 'sharingselect'));
    
        $this->view->assign('Selected',  ModUtil::apiFunc('PostCalendar', 'event', 'alldayselect', $eventDefaults['alldayevent']));
    
        $this->view->assign('postcalendar_eventdefaults', $eventDefaults);
    
        return $this->view->fetch('admin/eventdefaults.tpl');
    }
    
    /**
     * @desc sets module variables as requested by admin
     * @return      status/error ->back to event defaults config page
     */
    public function seteventdefaults()
    {
        if (!SecurityUtil::checkPermission('PostCalendar::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }
    
        $eventDefaults = FormUtil::getPassedValue('postcalendar_eventdefaults'); //array

        // filter through locations translator
        $eventDefaults = ModUtil::apiFunc('PostCalendar', 'event', 'correctlocationdata', $eventDefaults);
    
        //convert times to storable values
        $eventDefaults['duration'] = ModUtil::apiFunc('PostCalendar', 'event', 'computeduration', $eventDefaults);
        $eventDefaults['duration'] = ($eventDefaults['duration'] > 0) ? $eventDefaults['duration'] : 3600; //disallow duration < 0
    
        $startTime = $eventDefaults['startTime'];
        unset($eventDefaults['startTime']); // clears the whole array
        $eventDefaults['startTime'] = ModUtil::apiFunc('PostCalendar', 'event', 'convertstarttime', $startTime);

        // save the new values
        ModUtil::setVar('PostCalendar', 'pcEventDefaults', $eventDefaults);
    
        LogUtil::registerStatus($this->__('Done! Updated the PostCalendar event default values.'));
        return $this->modifyeventdefaults();
    }

    private function _appendObjectActions($events)
    {
        foreach($events as $key => $event) {
            $options = array();
            $truncated_title = StringUtil::getTruncatedString($event['title'], 25);
            $options[] = array('url' => ModUtil::url('PostCalendar', 'user', 'display', array('viewtype' => 'details', 'eid' => $event['eid'])),
                    'image' => '14_layer_visible.gif',
                    'title' => $this->__f('View \'%s\'', $truncated_title));

            if (SecurityUtil::checkPermission('PostCalendar::Event', "{$event['title']}::{$event['eid']}", ACCESS_EDIT)) {
                if ($event['eventstatus'] == _EVENT_APPROVED) {
                    $options[] = array('url' => ModUtil::url('PostCalendar', 'admin', 'adminevents', array('action' => _ADMIN_ACTION_HIDE, 'events' => $event['eid'])),
                            'image' => 'db_remove.gif',
                            'title' => $this->__f('Hide \'%s\'', $truncated_title));
                } else {
                    $options[] = array('url' => ModUtil::url('PostCalendar', 'admin', 'adminevents', array('action' => _ADMIN_ACTION_APPROVE, 'events' => $event['eid'])),
                            'image' => 'ok.gif',
                            'title' => $this->__f('Approve \'%s\'', $truncated_title));
                }
                $options[] = array('url' => ModUtil::url('PostCalendar', 'event', 'edit', array('eid' => $event['eid'])),
                        'image' => 'xedit.gif',
                        'title' => $this->__f('Edit \'%s\'', $truncated_title));
            }

            if (SecurityUtil::checkPermission('PostCalendar::Event', "{$event['title']}::{$event['eid']}", ACCESS_DELETE)) {
                $options[] = array('url' => ModUtil::url('PostCalendar', 'event', 'delete', array('eid' => $event['eid'])),
                    'image' => '14_layer_deletelayer.gif',
                    'title' => $this->__f('Delete \'%s\'', $truncated_title));
            }
            $events[$key]['options'] = $options;
        }
        return $events;
    }

} // end class def