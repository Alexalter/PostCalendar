{nocache}{ajaxheader module="PostCalendar" ui=true}{pc_pagejs_init}{/nocache}
{pc_queued_events_notify}
<form action="{modurl modname='PostCalendar' type='user' func='display'}" id='pcnav-form' method="post" enctype="application/x-www-form-urlencoded">
<div class="z-clearfix">
    {if $navigationObj->navBarType == 'buttonbar'}
    <div id="pcnav_buttonbar">
        {if $navigationObj->useFilter}
        <input type='text' readonly="readonly" id='pcnav_filterpicker' name='pcnav_filterpicker' value='{gt text='inactive'}' />
        <input id="pcnav_filterpicker_button" type="image" alt="filter" title='filter categories' src='images/icons/extrasmall/filter.png' />
        {/if}
        {if $navigationObj->useJumpDate}
        {assign value='overcast' var='jquerytheme'}
        {jquery_datepicker defaultdate=$navigationObj->requestedDate displayelement='pcnav_datepicker' valuestorageelement='date' valuestorageformat='Ymd' theme=$jquerytheme submitonselect=true mindate="-"|cat:$modvars.PostCalendar.pcFilterYearStart|cat:"Y" maxdate="+"|cat:$modvars.PostCalendar.pcFilterYearEnd|cat:"Y"}
        <input id="pcnav_datepicker_button" type="image" alt="jump" title='jump to date' src='modules/PostCalendar/images/icon-calendar.jpg' />
        {/if}
        {if $navigationObj->useNavBar}
        {foreach from=$navigationObj->getNavItems() item='navItem'}
            {$navItem->renderRadio()}
        {/foreach}
        {/if}
    </div>
    <!-- This is a pop up dialog box -->
    <div id='pcnav_filterpicker_dialog' title='{gt text='Filter view'}'>
        <h5>{gt text='Categories'}</h5>
        <ul>
        {foreach from=$pcCategories key='regname' item='categories'}
            {foreach from=$categories item='category'}
            <li class='pccategories_selector_{$category.id} pccategories_selector' id='pccat_{$category.id}'>{$category.display_name.en}</li>
            {/foreach}
        {/foreach}
        </ul>
        {checkgroup gid=$modvars.PostCalendar.pcAllowUserCalendar}
        <h5>{gt text='Visibility'}</h5>
        <ul>
            <li class='pcvisibility_selector' id='pcviz_private'>{gt text='Private events'}</li>
            <li class='pcvisibility_selector' id='pcviz_global'>{gt text='Global events'}</li>
        </ul>
        {/checkgroup}
    </div>
    <!-- end dialog -->
    {else}{*else if $navigationObj->navBarType != 'buttonbar'*}
    {if $navigationObj->useNavBar}
    <div id="postcalendar_nav_right">
        <ul>
            {foreach from=$navigationObj->getNavItems() item='navItem'}
            <li>{$navItem->renderAnchorTag()|safehtml}</li>
            {/foreach}    
        </ul>
    </div>
    {/if}
    {if $navigationObj->useFilter || $navigationObj->useJumpDate}
    <div id="postcalendar_nav_left">
        <ul>
            {if $navigationObj->useFilter}
            {gt text="Filter" assign="lbltxt"}
            <li>{pc_filter label=$lbltxt userfilter=$navigationObj->getUserFilter() selectedCategories=$navigationObj->getSelectedCategories()}</li>
            {/if}
            {if $navigationObj->useJumpDate}
            <li>
                {pc_html_select_date time=$navigationObj->requestedDate->format('Y-m-d') prefix="jump" start_year="-"|cat:$modvars.PostCalendar.pcFilterYearStart end_year="+"|cat:$modvars.PostCalendar.pcFilterYearEnd day_format="%d" day_value_format="%02d" month_format='%B' field_order=$modvars.PostCalendar.pcEventDateFormat}
                {if !empty($navigationObj->viewtypeselector)}
                    {html_options name='_viewtype' options=$navigationObj->viewtypeselector selected=$navigationObj->getViewtype()}
                {/if}
                <input type="submit" name="pc_submit" value="{gt text="Jump"}" />
            </li>
            {/if}
        </ul>
    </div>
    {/if}
    {/if}{*end if $navigationObj->navBarType == 'buttonbar'*}
</div>
</form>
<div>{insert name="getstatusmsg"}</div>