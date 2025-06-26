<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    UnaBaseView UNA Base Representation Classes
 * @{
 */

/**
 * @see BxBaseConnection
 */
class BxBaseConnection extends BxDolConnection
{
    protected $_oTemplate;
    protected $_oFunctions;

    protected $_sStylePrefix;
    protected $_sJsObjName;

    protected $_sHp;
    protected $_aHtmlIds;
    
    protected $_aT;

    protected $_sTmplContentElement;
    protected $_sTmplContentCounter;

    protected $_aElementDefaults;

    public function __construct($aObject)
    {
        parent::__construct($aObject);

        $this->_oTemplate = BxDolTemplate::getInstance();
        $this->_oFunctions = BxTemplFunctions::getInstanceWithTemplate($this->_oTemplate);

        $this->_sStylePrefix = 'bx-conn';
        $this->_sJsObjName = 'oConn' . bx_gen_method_name($this->_sObject, ['_' , '-']) . '';

        $this->_sHp = str_replace('_', '-', $this->_sObject);
        $this->_aHtmlIds = [
            'main' => 'bx-conn-' . $this->_sHp . '-',
            'do_popup' => 'bx-conn-do-popup-' . $this->_sHp . '-',
            'do_menu' => 'bx-conn-do-menu-' . $this->_sHp . '-',
            'counter' => 'bx-conn-counter-' . $this->_sHp . '-',
            'by_popup' => 'bx-conn-by-popup-' . $this->_sHp . '-',
        ];

        $this->_aT = [
            'do_by' => '_sys_conn_do_by'
        ];

        $this->_sTmplContentElement = $this->_oTemplate->getHtml('connection_element.html');
        $this->_sTmplContentCounter = $this->_oTemplate->getHtml('connection_counter.html');

        $this->_aElementDefaults = [
            'dynamic_mode' => true,
            'show_do_as_button_small' => false,
            'show_do_as_button' => false,
            'show_do_icon' => true,
            'show_do_label' => true,
            'show_counter' => false,
            'show_counter_label_with_profiles' => true,
            'show_script' => true
        ];
    }

    public function actionGetConnected ($iStart = 0, $iPerPage = 0)
    {
        $sContentType = bx_process_input(bx_get('content_type'));
        $iProfileId = (int)bx_get('id');
        $bIsMutual = (bool)bx_get('mutual');
        return $this->_getConnected ($sContentType, $iProfileId, $bIsMutual, $iStart, $iPerPage);
    }
    
    public function actionGetUsers ($iStart = 0, $iPerPage = 0)
    {
        $sContentType = bx_process_input(bx_get('content_type'));
        $iProfileId = (int)bx_get('id');
        $bIsMutual = (bool)bx_get('mutual');
        $iStart = (int)bx_get('start');
        $iPerPage = (int)bx_get('per_page');

        return [
            'content' => $this->_getConnected($sContentType, $iProfileId, $bIsMutual, $iStart, $iPerPage),
            'eval' => $this->getJsObjectName($iProfileId) . '.onGetUsers(oData)'
        ];
    }
    
    public function getJsObjectName($iProfileId)
    {
        return $this->_sJsObjName . $iProfileId;
    }

    public function getElement($iContent, $iInitiator = false, $aParams = [])
    {
    	$aParams = array_merge($this->_aElementDefaults, $aParams);
    	$bDynamicMode = isset($aParams['dynamic_mode']) && $aParams['dynamic_mode'] === true;

        $bShowDo = !isset($aParams['show_do']) || (bool)$aParams['show_do'] === true;
        $bShowDoAsButtonSmall = isset($aParams['show_do_as_button_small']) && $aParams['show_do_as_button_small'] == true;
        $bShowDoAsButton = !$bShowDoAsButtonSmall && isset($aParams['show_do_as_button']) && $aParams['show_do_as_button'] == true;
        $bShowDoLabel = isset($aParams['show_do_label']) && $aParams['show_do_label'] === true;
        $bShowCounter = isset($aParams['show_counter']) && $aParams['show_counter'] === true;

        if(!$iInitiator)
            $iInitiator = bx_get_logged_profile_id();

        $aActions = $this->_getActions($iInitiator, $iContent, $aParams);

        $iCount = $this->_getCounterCount($iContent, $this->_bMutual, $aParams);

        if((empty($aActions['items']) || !is_array($aActions['items'])) && (!$bShowCounter || !$iCount))
            return '';        

        $sClassDo = '';
        if($bShowDoAsButton)
            $sClassDo = ' bx-btn';
        else if ($bShowDoAsButtonSmall)
            $sClassDo = ' bx-btn bx-btn-small';

        $aTmplVarsAction = $aTmplVarsActions = [];
        if(count($aActions['items']) == 1) {
            $aAction = reset($aActions['items']);

            $aTmplVarsAction = array_merge([
                'style_prefix' => $this->_sStylePrefix,
                'class' => $sClassDo,
                'object' => $this->_sObject, 
                'action' => $aAction['name'],
                'profile_id' => $iContent,
                'title' => $bShowDoLabel && !empty($aAction['title']) ? _t($aAction['title']) : ''
            ], $this->_getTmplVarsAction($aAction));
        }
        else {
            $sHtmlIdDoPopup = $this->_aHtmlIds['do_popup'] . $iContent;
            $sHtmlIdDoMenu = $this->_aHtmlIds['do_menu'] . $iContent;

            $aMenuItems = [];
            foreach($aActions['items'] as $aItem)
                if(!empty($aItem) && is_array($aItem))
                    $aMenuItems[] = [
                        'id' => $aItem['name'], 
                        'name' => $aItem['name'], 
                        'class' => '', 
                        'icon' => $this->_getActionIcon($aItem, $aParams), 
                        'link' => 'javascript:void(0)', 
                        'onclick' => 'javascript:bx_conn_action(this, \'' . $this->_sObject . '\', \'' . $aItem['name'] . '\', ' . $iContent . ')', 
                        'target' => '_self', 
                        'title' => !empty($aItem['title']) ? _t($aItem['title']) : '', 
                        'active' => 1
                    ];

            $oMenu = new BxTemplMenu(['template' => 'menu_vertical.html', 'menu_id'=> $sHtmlIdDoMenu, 'menu_items' => $aMenuItems]);

            $aTmplVarsActions = array_merge([
                'style_prefix' => $this->_sStylePrefix,
                'class' => $sClassDo,
                'html_id_popup' => $sHtmlIdDoPopup,
                'do_popup' => $this->_oFunctions->transBox($sHtmlIdDoPopup, $oMenu->getCode(), true),
            ], $this->_getTmplVarsAction([
                'name' => $aActions['name'],
                'title' => $bShowDoLabel && !empty($aActions['title']) ? $aActions['title'] : ''
            ]));
        }

        $bTmplVarsAction = !empty($aTmplVarsAction) && is_array($aTmplVarsAction);
        $bTmplVarsActions = !empty($aTmplVarsActions) && is_array($aTmplVarsActions);

        return $this->_oTemplate->parseHtmlByContent($this->_getTmplContentElement(), [
            'style_prefix' => $this->_sStylePrefix,
            'html_id' => $this->_aHtmlIds['main'] . $iContent,
            'class' => $this->_sStylePrefix . ($bShowDoAsButton ? '-button' : '-link') . ($bShowDoAsButtonSmall ? '-button-small' : ''),
            'bx_if:show_do' => [
                'condition' => $bShowDo && ($bTmplVarsAction || $bTmplVarsActions),
                'content' => [
                    'style_prefix' => $this->_sStylePrefix,
                    'bx_if:show_action' => [
                        'condition' => $bTmplVarsAction,
                        'content' => $aTmplVarsAction
                    ],
                    'bx_if:show_actions' => [
                        'condition' => $bTmplVarsActions,
                        'content' => $aTmplVarsActions
                    ],
                ]
            ],
            'bx_if:show_counter' => [
                'condition' => $bShowCounter && $iCount,
                'content' => [
                    'style_prefix' => $this->_sStylePrefix,
                    'counter' => $this->getCounter($iContent, $this->_bMutual, array_merge($aParams, ['count' => $iCount]))
                ]
            ],
            'script' => $this->_getJsScript($iContent, $this->_bMutual, $aParams)
        ]);
    }

    public function getCounter($iProfileId, $bIsMutual = false, $aParams = [])
    {
        if(!$iProfileId)
            $iProfileId = bx_get_logged_profile_id();

        if($this->_aObject['type'] != 'mutual')
            $bIsMutual = false;

        $aParams = array_merge($this->_aElementDefaults, (!empty($aParams) ? $aParams : []));

        $iCount = !empty($aParams['count']) ? $aParams['count'] : $this->_getCounterCount($iProfileId, $bIsMutual, $aParams);
        if(!$iCount)
            return '';

        $sJsObject = $this->getJsObjectName($iProfileId);
        $sHtmlIdCounter = $this->_aHtmlIds['counter'] . $iProfileId;

        $bDynamicMode = isset($aParams['dynamic_mode']) && $aParams['dynamic_mode'] === true;
        $bShowDoAsButtonSmall = isset($aParams['show_do_as_button_small']) && $aParams['show_do_as_button_small'] === true;
        $bShowDoAsButton = !$bShowDoAsButtonSmall && isset($aParams['show_do_as_button']) && $aParams['show_do_as_button'] === true;
        $bShowScript = !isset($aParams['show_script']) || $aParams['show_script'] == true;

        $sClass = $this->_sStylePrefix . '-counter';
        if($bShowDoAsButtonSmall)
            $sClass .= ' bx-btn-small-height';
        if($bShowDoAsButton)
            $sClass .= ' bx-btn-height';

        $sContent = $this->_getCounterLabel($iCount, $iProfileId, $bIsMutual, $aParams);

        $this->_oTemplate->addCss(['connection.css']);
        return $this->_oTemplate->parseHtmlByContent($this->_getTmplContentCounter(), array(
            'html_id' => $sHtmlIdCounter,
            'style_prefix' => $this->_sStylePrefix,
            'bx_if:show_text' => array(
                'condition' => false,
                'content' => array(
                    'class' => $sClass,
                    'bx_repeat:attrs' => array(
                        array('key' => 'id', 'value' => $sHtmlIdCounter),
                    ),
                    'content' => $sContent
                )
            ),
            'bx_if:show_link' => array(
                'condition' => true,
                'content' => array(
                    'class' => $sClass,
                    'bx_repeat:attrs' => array(
                        array('key' => 'id', 'value' => $sHtmlIdCounter),
                        array('key' => 'href', 'value' => 'javascript:void(0)'),
                        array('key' => 'onclick', 'value' => 'javascript:' . $sJsObject . '.toggleByPopup(this)'),
                        array('key' => 'title', 'value' => _t('_sys_conn_do_by'))
                    ),
                    'content' => $sContent
                )
            ),
            'script' => $bShowScript ? $this->_getJsScript($iProfileId, $bIsMutual, $aParams) : ''
        ));
    }

    public function getCounterAPI($iProfileId, $bIsMutual = false, $aParams = [])
    {
        $sContentType = ($sKey = 'content_type') && isset($aParams[$sKey]) ? $aParams[$sKey] : BX_CONNECTIONS_CONTENT_TYPE_INITIATORS;
        $iCount = $this->{'getConnected' . ($sContentType == BX_CONNECTIONS_CONTENT_TYPE_INITIATORS ? 'Initiators' : 'Content') . 'Count'}($iProfileId, $bIsMutual);

        return [
            'count' => $iCount,
            'countf' => _t(isset($aParams['caption']) ? $aParams['caption'] : $this->_aT['counter'], $iCount)
        ];
    }

    public function getConnectedListAPI($iProfileId, $bIsMutual, $sContentType, $iCount = BX_CONNECTIONS_LIST_COUNTER)
    {
        $aProfiles = [];
        $aIds = $this->{BX_CONNECTIONS_CONTENT_TYPE_INITIATORS == $sContentType ? 'getConnectedInitiators' : 'getConnectedContent'}($iProfileId, $bIsMutual, 0, $iCount);
        foreach($aIds as $iId)
            if(($oProfile = BxDolProfile::getInstanceMagic($iId)) !== false)
                $aProfiles[] = $oProfile->getUnitAPI(0, ['template' => 'unit_wo_info']);
        
        return $aProfiles;
    }

    public function getCommonListAPI($iProfileId1, $iProfileId2, $bIsMutual, $iCount = BX_CONNECTIONS_LIST_COUNTER)
    {
        $aProfiles = [];
        $aIds = $this->getCommonContent($iProfileId1, $iProfileId2, $bIsMutual, 0, $iCount);
        foreach($aIds as $iId)
            if(($oProfile = BxDolProfile::getInstanceMagic($iId)) !== false)
                $aProfiles[] = $oProfile->getUnitAPI(0, ['template' => 'unit_wo_info']);
        
        return $aProfiles;
    }

    protected function _getJsScript($iProfileId, $bIsMutual = false, $aParams = [])
    {
        $sJsObject = $this->getJsObjectName($iProfileId);

        $aParamsJs = [
            'sSystem' => $this->_sObject,
            'iObjId' => $iProfileId,
            'bIsMutual' => $bIsMutual ? 1 : 0,
            'sObjName' => $sJsObject,
            'sRootUrl' => BX_DOL_URL_ROOT,
            'sStylePrefix' => $this->_sStylePrefix,
            'aHtmlIds' => $this->_aHtmlIds
        ];
        if(($sKey = 'content_type') && !empty($aParams[$sKey]))
            $aParamsJs['sContentType'] = $aParams[$sKey];

        $sCode = "if(window['" . $sJsObject . "'] == undefined) var " . $sJsObject . " = new BxDolConnection(" . json_encode($aParamsJs) . ");";

        return $this->_oTemplate->_wrapInTagJsCode($sCode);
    }

    protected function _getCounterCount($iProfileId, $bIsMutual, $aParams = [])
    {
        $sContentType = ($sKey = 'content_type') && isset($aParams[$sKey]) ? $aParams[$sKey] : BX_CONNECTIONS_CONTENT_TYPE_INITIATORS;
        return $this->{'getConnected' . ($sContentType == BX_CONNECTIONS_CONTENT_TYPE_INITIATORS ? 'Initiators' : 'Content') . 'Count'}($iProfileId, $bIsMutual);
    }

    protected function _getCounterLabel($iCount, $iProfileId, $bIsMutual, $aParams)
    {
        if ('mutual' != $this->_aObject['type'])
            $bIsMutual = false;

        $sContentType = ($sKey = 'content_type') && isset($aParams[$sKey]) ? $aParams[$sKey] : BX_CONNECTIONS_CONTENT_TYPE_INITIATORS;

        $bShowWithProfiles = isset($aParams['show_counter_label_with_profiles']) && $aParams['show_counter_label_with_profiles'] === true;
        $bShowWithIcon = (!isset($aParams['show_counter_label_icon']) || $aParams['show_counter_label_icon'] === true) && !$bShowWithProfiles;

        $aTmplVarsWithProfiles = [];
        if($bShowWithProfiles) {
            $aTmplVarsWithProfiles = [
                'style_prefix' => $this->_sStylePrefix,
                'bx_repeat:profiles' => []
            ];

            $aIds = $this->{'getConnected' . (BX_CONNECTIONS_CONTENT_TYPE_INITIATORS == $sContentType ? 'Initiators' : 'Content')}($iProfileId, $bIsMutual, 0, BX_CONNECTIONS_LIST_COUNTER);
            foreach($aIds as $iId)
                if(($oProfile = BxDolProfile::getInstanceMagic($iId)) !== false)
                    $aTmplVarsWithProfiles['bx_repeat:profiles'][] = [
                        'style_prefix' => $this->_sStylePrefix,
                        'unit' => $oProfile->getUnit(0, ['template' => ['name' => 'unit_wo_info_links', 'size' => 'icon']])
                    ];
        }

        return $this->_oTemplate->parseHtmlByName('connection_counter_label.html', array(
            'style_prefix' => $this->_sStylePrefix,
            'bx_if:show_icon' => array(
                'condition' => $bShowWithIcon,
                'content' => array(
                    'style_prefix' => $this->_sStylePrefix,
                    'name' => isset($aParams['custom_icon']) && $aParams['custom_icon'] != '' ? $aParams['custom_icon'] : $this->_oFunctions->getFontIconAsHtml($this->_getIconDo())
                )
            ),
            'bx_if:show_profiles' => array(
                'condition' => $bShowWithProfiles,
                'content' => $aTmplVarsWithProfiles
            ),
            'bx_if:show_text' => array(
                'condition' => !isset($aParams['show_counter_label_text']) || $aParams['show_counter_label_text'] === true,
                'content' => array(
                    'style_prefix' => $this->_sStylePrefix,
                    'text' => _t(isset($aParams['caption']) ? $aParams['caption'] : $this->_aT['counter'], $iCount)
                )
            )
        ));
    }

    protected function _getIconDo()
    {
        if ($this->_sObject == 'sys_profiles_subscriptions')
            return 'user-friends';
        
        if (strpos($this->_sObject, 'fan'))
            return 'users';
    }
    
    public function _getConnected ($sContentType, $iProfileId, $bIsMutual, $iStart = 0, $iPerPage = 0)
    {
        if ('mutual' != $this->_aObject['type'])
            $bIsMutual = false;

        if(empty($iPerPage))
            $iPerPage = $this->_aObject['per_page_default'];

        $aUsers = $this->getConnectionsAsArray($sContentType, $iProfileId, null, $bIsMutual, $iStart, $iPerPage + 1);

        $oPaginate = new BxTemplPaginate([
            'on_change_page' => $this->getJsObjectName($iProfileId) . '.getUsers(this, {start}, {per_page})',
            'start' => $iStart,
            'per_page' => $iPerPage,
        ]);
        $oPaginate->setNumFromDataArray($aUsers);

        foreach($aUsers as $iProfile) {
            $oProfile = BxDolProfile::getInstanceMagic($iProfile);
            $aTmplUsers[] = [
                'style_prefix' => $this->_sStylePrefix,
                'user_unit' => $oProfile->getUnit(0, ['template' => 'unit_wo_info']),
                'user_url' => $oProfile->getUrl(),
            	'user_title' => bx_html_attribute($oProfile->getDisplayName()),
            	'user_name' => $oProfile->getDisplayName()
            ];
        }

        if(empty($aTmplUsers))
            $aTmplUsers = MsgBox(_t('_Empty'));

        return $this->_oTemplate->parseHtmlByName('connected_by_list.html', [
            'style_prefix' => $this->_sStylePrefix,
            'bx_repeat:list' => $aTmplUsers,
            'paginate' => $oPaginate->getSimplePaginate()
        ]);
    }
    
    protected function _getAuthorInfo($iAuthorId = 0)
    {
        $oProfile = $this->_getAuthorObject($iAuthorId);

        return array(
            $oProfile->getDisplayName(),
            $oProfile->getUrl(),
            $oProfile->getThumb(),
            $oProfile->getUnit(),
            $oProfile->getUnit(0, array('template' => 'unit_wo_info'))
        );
    }
    
    protected function _getActions($iInitiator, $iContent, $aParams = [])
    {
        return [];
    }

    protected function _getActionIcon($aAction, $aParams = [])
    {
        return !empty($aAction['name']) ? $this->{'_getActionIconAs' . bx_gen_method_name($this->_useIconAs($aParams))}($aAction['name']) : '';
    }

    protected function _useIconAs($aParams = [])
    {
        if(!empty($aParams['use_icon_as']) && in_array($aParams['use_icon_as'], ['icon', 'emoji', 'image']))
            return $aParams['use_icon_as'];

        return 'icon';
    }

    protected function _getTmplContentElement()
    {
        return $this->_sTmplContentElement;
    }
    
    protected function _getTmplContentCounter()
    {
        return $this->_sTmplContentCounter;
    }

    protected function _getTmplVarsAction($aAction, $aParams = [])
    {
        $sIcon = $this->_getActionIcon($aAction, $aParams);

        return [
            'bx_if:show_icon' => [
                'condition' => !empty($sIcon),
                'content' => [
                    'style_prefix' => $this->_sStylePrefix,
                    'icon' => $this->_oTemplate->getImageAuto($sIcon)
                ]
            ],
            'bx_if:show_text' => [
                'condition' => !empty($aAction['title']),
                'content' => [
                    'style_prefix' => $this->_sStylePrefix,
                    'text' => _t($aAction['title'])
                ]
            ]
        ];
    }
}
